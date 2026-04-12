<?php

namespace App\Tests\UnitTests;

use App\Service\ExceptionDetectionService;
use PHPUnit\Framework\TestCase;

class ExceptionDetectionTest extends TestCase
{
    private ExceptionDetectionService $service;

    protected function setUp(): void
    {
        $this->service = new ExceptionDetectionService();
    }

    /**
     * LATE_ARRIVAL boundary:
     *   NOT late  → firstIn == shiftStart + tolerance   (exactly at threshold)
     *   IS late   → firstIn == shiftStart + tolerance + 1 min
     */
    public function testLateArrivalThreshold(): void
    {
        $schedule = [
            'shiftStart' => new \DateTimeImmutable('09:00'),
            'shiftEnd'   => new \DateTimeImmutable('17:00'),
        ];
        $rules = ['toleranceMinutes' => 5];

        // Punch at exactly 09:05 — on the boundary, NOT late
        $punchesOnBoundary = [
            ['time' => new \DateTimeImmutable('09:05'), 'type' => 'IN'],
            ['time' => new \DateTimeImmutable('17:00'), 'type' => 'OUT'],
        ];
        $resultOnBoundary = $this->service->detectExceptions($punchesOnBoundary, $schedule, $rules);
        $this->assertNotContains(
            'LATE_ARRIVAL',
            array_column($resultOnBoundary, 'type'),
            'Punch at exactly shiftStart + tolerance (09:05) must NOT be flagged as LATE_ARRIVAL'
        );

        // Punch at 09:06 — one minute over the threshold, IS late
        $punchesOverBoundary = [
            ['time' => new \DateTimeImmutable('09:06'), 'type' => 'IN'],
            ['time' => new \DateTimeImmutable('17:00'), 'type' => 'OUT'],
        ];
        $resultOverBoundary = $this->service->detectExceptions($punchesOverBoundary, $schedule, $rules);
        $this->assertContains(
            'LATE_ARRIVAL',
            array_column($resultOverBoundary, 'type'),
            'Punch at shiftStart + tolerance + 1 min (09:06) MUST be flagged as LATE_ARRIVAL'
        );
    }

    /**
     * EARLY_LEAVE boundary:
     *   NOT early → lastOut == shiftEnd - tolerance     (exactly at threshold)
     *   IS early  → lastOut == shiftEnd - tolerance - 1 min
     */
    public function testEarlyLeaveThreshold(): void
    {
        $schedule = [
            'shiftStart' => new \DateTimeImmutable('09:00'),
            'shiftEnd'   => new \DateTimeImmutable('17:00'),
        ];
        $rules = ['toleranceMinutes' => 5];

        // OUT at exactly 16:55 (17:00 - 5 min) — on the boundary, NOT early leave
        $punchesOnBoundary = [
            ['time' => new \DateTimeImmutable('09:00'), 'type' => 'IN'],
            ['time' => new \DateTimeImmutable('16:55'), 'type' => 'OUT'],
        ];
        $resultOnBoundary = $this->service->detectExceptions($punchesOnBoundary, $schedule, $rules);
        $this->assertNotContains(
            'EARLY_LEAVE',
            array_column($resultOnBoundary, 'type'),
            'OUT at exactly shiftEnd - tolerance (16:55) must NOT be flagged as EARLY_LEAVE'
        );

        // OUT at 16:54 — one minute below the threshold, IS early leave
        $punchesUnderBoundary = [
            ['time' => new \DateTimeImmutable('09:00'), 'type' => 'IN'],
            ['time' => new \DateTimeImmutable('16:54'), 'type' => 'OUT'],
        ];
        $resultUnderBoundary = $this->service->detectExceptions($punchesUnderBoundary, $schedule, $rules);
        $this->assertContains(
            'EARLY_LEAVE',
            array_column($resultUnderBoundary, 'type'),
            'OUT at shiftEnd - tolerance - 1 min (16:54) MUST be flagged as EARLY_LEAVE'
        );
    }

    /**
     * MISSED_PUNCH with 30-minute window:
     *   IN at 09:29 (29 min after 09:00) — within window → no missed punch
     *   IN at 09:31 (31 min after 09:00) — outside window → missed punch
     */
    public function testMissedPunchWindow30Min(): void
    {
        $schedule = [
            'shiftStart' => new \DateTimeImmutable('09:00'),
            'shiftEnd'   => new \DateTimeImmutable('17:00'),
        ];
        $rules = ['missedPunchWindowMinutes' => 30];

        // 09:29 — within the 30-min window, no MISSED_PUNCH
        $punchesWithin = [
            ['time' => new \DateTimeImmutable('09:29'), 'type' => 'IN'],
            ['time' => new \DateTimeImmutable('17:00'), 'type' => 'OUT'],
        ];
        $resultWithin = $this->service->detectExceptions($punchesWithin, $schedule, $rules);
        $this->assertNotContains(
            'MISSED_PUNCH',
            array_column($resultWithin, 'type'),
            'IN punch at 09:29 (within 30-min window) must NOT trigger MISSED_PUNCH'
        );

        // 09:31 — outside the 30-min window, MISSED_PUNCH expected
        $punchesOutside = [
            ['time' => new \DateTimeImmutable('09:31'), 'type' => 'IN'],
            ['time' => new \DateTimeImmutable('17:00'), 'type' => 'OUT'],
        ];
        $resultOutside = $this->service->detectExceptions($punchesOutside, $schedule, $rules);
        $this->assertContains(
            'MISSED_PUNCH',
            array_column($resultOutside, 'type'),
            'IN punch at 09:31 (outside 30-min window) MUST trigger MISSED_PUNCH'
        );
    }

    /**
     * LATE_ARRIVAL and EARLY_LEAVE can coexist in the same result set.
     * Punch in late AND punch out early with enough deviation to exceed both thresholds.
     */
    public function testAllExceptionsCanCoexist(): void
    {
        $schedule = [
            'shiftStart' => new \DateTimeImmutable('09:00'),
            'shiftEnd'   => new \DateTimeImmutable('17:00'),
        ];
        $rules = ['toleranceMinutes' => 5, 'missedPunchWindowMinutes' => 30];

        // IN at 09:15 → late (> 09:05 threshold)
        // OUT at 16:30 → early (< 16:55 threshold)
        $punches = [
            ['time' => new \DateTimeImmutable('09:15'), 'type' => 'IN'],
            ['time' => new \DateTimeImmutable('16:30'), 'type' => 'OUT'],
        ];

        $result = $this->service->detectExceptions($punches, $schedule, $rules);
        $types  = array_column($result, 'type');

        $this->assertContains('LATE_ARRIVAL', $types, 'Expected LATE_ARRIVAL in the combined exception set');
        $this->assertContains('EARLY_LEAVE',  $types, 'Expected EARLY_LEAVE in the combined exception set');
    }
}
