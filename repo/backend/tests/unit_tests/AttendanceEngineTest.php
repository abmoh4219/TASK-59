<?php

namespace App\Tests\UnitTests;

use App\Service\ExceptionDetectionService;
use PHPUnit\Framework\TestCase;

class AttendanceEngineTest extends TestCase
{
    private ExceptionDetectionService $service;

    protected function setUp(): void
    {
        $this->service = new ExceptionDetectionService();
    }

    public function testLateArrivalDetected(): void
    {
        $punches = [
            ['time' => new \DateTimeImmutable('09:12'), 'type' => 'IN'],
            ['time' => new \DateTimeImmutable('17:00'), 'type' => 'OUT'],
        ];
        $schedule = [
            'shiftStart' => new \DateTimeImmutable('09:00'),
            'shiftEnd'   => new \DateTimeImmutable('17:00'),
        ];
        $rules = ['toleranceMinutes' => 5];

        $result = $this->service->detectExceptions($punches, $schedule, $rules);

        $types = array_column($result, 'type');
        $this->assertContains('LATE_ARRIVAL', $types, 'Expected LATE_ARRIVAL for 09:12 punch with 5-min tolerance');
    }

    public function testOnTimeNotFlagged(): void
    {
        $punches = [
            ['time' => new \DateTimeImmutable('08:58'), 'type' => 'IN'],
            ['time' => new \DateTimeImmutable('17:00'), 'type' => 'OUT'],
        ];
        $schedule = [
            'shiftStart' => new \DateTimeImmutable('09:00'),
            'shiftEnd'   => new \DateTimeImmutable('17:00'),
        ];
        $rules = ['toleranceMinutes' => 5];

        $result = $this->service->detectExceptions($punches, $schedule, $rules);

        $types = array_column($result, 'type');
        $this->assertNotContains('LATE_ARRIVAL', $types, 'Should NOT flag LATE_ARRIVAL for 08:58 punch with 5-min tolerance');
    }

    public function testMissedPunchDetected(): void
    {
        // Only one IN punch far past the window — no OUT punch
        $punches = [
            ['time' => new \DateTimeImmutable('10:00'), 'type' => 'IN'],
        ];
        $schedule = [
            'shiftStart' => new \DateTimeImmutable('09:00'),
            'shiftEnd'   => new \DateTimeImmutable('17:00'),
        ];
        $rules = ['missedPunchWindowMinutes' => 30];

        $result = $this->service->detectExceptions($punches, $schedule, $rules);

        $types = array_column($result, 'type');
        $this->assertContains('MISSED_PUNCH', $types, 'Expected MISSED_PUNCH when IN punch is 60 min after shift start (window=30)');
    }

    public function testAbsenceDetected(): void
    {
        $schedule = [
            'shiftStart' => new \DateTimeImmutable('09:00'),
            'shiftEnd'   => new \DateTimeImmutable('17:00'),
        ];

        $result = $this->service->detectExceptions([], $schedule, []);

        $types = array_column($result, 'type');
        $this->assertContains('ABSENCE', $types, 'Expected ABSENCE when no punches are recorded');
    }

    public function testApprovedOffsiteNotFlagged(): void
    {
        $schedule = [
            'shiftStart' => new \DateTimeImmutable('09:00'),
            'shiftEnd'   => new \DateTimeImmutable('17:00'),
        ];

        $result = $this->service->detectExceptions([], $schedule, [], true);

        $types = array_column($result, 'type');
        $this->assertContains('APPROVED_OFFSITE', $types, 'Expected APPROVED_OFFSITE when hasApprovedOffsite=true');
        $this->assertNotContains('ABSENCE', $types, 'Should NOT flag ABSENCE when offsite is approved');
    }

    public function testEngineIsDeterministic(): void
    {
        $punches = [
            ['time' => new \DateTimeImmutable('09:12'), 'type' => 'IN'],
            ['time' => new \DateTimeImmutable('16:55'), 'type' => 'OUT'],
        ];
        $schedule = [
            'shiftStart' => new \DateTimeImmutable('09:00'),
            'shiftEnd'   => new \DateTimeImmutable('17:00'),
        ];
        $rules = ['toleranceMinutes' => 5, 'missedPunchWindowMinutes' => 30];

        $first  = $this->service->detectExceptions($punches, $schedule, $rules);
        $second = $this->service->detectExceptions($punches, $schedule, $rules);

        $this->assertSame(
            array_column($first, 'type'),
            array_column($second, 'type'),
            'detectExceptions must be deterministic: same inputs must produce the same output'
        );
    }

    public function testToleranceConfigurable(): void
    {
        // 09:08 — should NOT be late with toleranceMinutes=10
        $punchesEarly = [
            ['time' => new \DateTimeImmutable('09:08'), 'type' => 'IN'],
            ['time' => new \DateTimeImmutable('17:00'), 'type' => 'OUT'],
        ];
        $schedule = [
            'shiftStart' => new \DateTimeImmutable('09:00'),
            'shiftEnd'   => new \DateTimeImmutable('17:00'),
        ];

        $resultWith10 = $this->service->detectExceptions($punchesEarly, $schedule, ['toleranceMinutes' => 10]);
        $typesWith10  = array_column($resultWith10, 'type');
        $this->assertNotContains(
            'LATE_ARRIVAL',
            $typesWith10,
            '09:08 should NOT be late when toleranceMinutes=10'
        );

        // 09:08 — SHOULD be late with toleranceMinutes=5
        $resultWith5 = $this->service->detectExceptions($punchesEarly, $schedule, ['toleranceMinutes' => 5]);
        $typesWith5  = array_column($resultWith5, 'type');
        $this->assertContains(
            'LATE_ARRIVAL',
            $typesWith5,
            '09:08 SHOULD be late when toleranceMinutes=5'
        );
    }
}
