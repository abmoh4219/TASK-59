<?php

namespace App\Service;

/**
 * ExceptionDetectionService — deterministic exception detection for attendance records.
 *
 * Pure function: same inputs always produce the same exception set.
 * No database access — all data passed as arguments.
 *
 * Exception detection rules:
 * - LATE_ARRIVAL: first punch > shift_start + tolerance (default 5 min)
 * - EARLY_LEAVE: last punch < shift_end - tolerance (default 5 min)
 * - MISSED_PUNCH: no punch event within 30 minutes of shift start
 * - ABSENCE: no punch events at all for the day
 * - APPROVED_OFFSITE: has approved business_trip or outing request covering the date
 */
class ExceptionDetectionService
{
    /**
     * Detect attendance exceptions for a user on a given date.
     *
     * @param array $punches Array of punch data: [['time' => DateTimeImmutable, 'type' => 'IN'|'OUT'], ...]
     * @param array $schedule ['shiftStart' => DateTimeImmutable, 'shiftEnd' => DateTimeImmutable] or null if no schedule
     * @param array $rules ['toleranceMinutes' => int, 'missedPunchWindowMinutes' => int]
     * @param bool  $hasApprovedOffsite Whether user has approved trip/outing covering this date
     * @return array Array of exception type strings with details
     */
    public function detectExceptions(
        array $punches,
        ?array $schedule,
        array $rules = [],
        bool $hasApprovedOffsite = false,
    ): array {
        $exceptions = [];
        $toleranceMinutes = $rules['toleranceMinutes'] ?? 5;
        $missedPunchWindowMinutes = $rules['missedPunchWindowMinutes'] ?? 30;

        // If user has approved offsite coverage, mark it and skip other checks
        if ($hasApprovedOffsite) {
            $exceptions[] = [
                'type' => 'APPROVED_OFFSITE',
                'detail' => 'Approved business trip or outing covers this date',
            ];
            return $exceptions;
        }

        // No schedule means we can't detect exceptions
        if ($schedule === null) {
            return $exceptions;
        }

        $shiftStart = $schedule['shiftStart'];
        $shiftEnd = $schedule['shiftEnd'];

        // ABSENCE: no punch events at all
        if (empty($punches)) {
            $exceptions[] = [
                'type' => 'ABSENCE',
                'detail' => 'No punch events recorded for this day',
            ];
            return $exceptions;
        }

        // Sort punches by time
        $sorted = $punches;
        usort($sorted, fn($a, $b) => $a['time'] <=> $b['time']);

        // Get IN punches and OUT punches
        $inPunches = array_filter($sorted, fn($p) => $p['type'] === 'IN');
        $outPunches = array_filter($sorted, fn($p) => $p['type'] === 'OUT');

        $firstIn = !empty($inPunches) ? reset($inPunches) : null;
        $lastOut = !empty($outPunches) ? end($outPunches) : null;

        // MISSED_PUNCH: no IN punch within missedPunchWindowMinutes of shift start
        if ($firstIn !== null) {
            $shiftStartTimestamp = $this->timeToMinutes($shiftStart);
            $firstInTimestamp = $this->timeToMinutes($firstIn['time']);
            $diffFromStart = $firstInTimestamp - $shiftStartTimestamp;

            if ($diffFromStart > $missedPunchWindowMinutes) {
                $exceptions[] = [
                    'type' => 'MISSED_PUNCH',
                    'detail' => sprintf(
                        'No punch within %d minutes of shift start (%s)',
                        $missedPunchWindowMinutes,
                        $shiftStart->format('h:i A')
                    ),
                ];
            }
        } else {
            // No IN punch at all
            $exceptions[] = [
                'type' => 'MISSED_PUNCH',
                'detail' => 'No clock-in punch recorded',
            ];
        }

        // LATE_ARRIVAL: first IN punch > shift_start + tolerance
        if ($firstIn !== null) {
            $shiftStartTimestamp = $this->timeToMinutes($shiftStart);
            $firstInTimestamp = $this->timeToMinutes($firstIn['time']);
            $lateThreshold = $shiftStartTimestamp + $toleranceMinutes;

            if ($firstInTimestamp > $lateThreshold) {
                $exceptions[] = [
                    'type' => 'LATE_ARRIVAL',
                    'detail' => sprintf(
                        'Arrived at %s, shift starts at %s (tolerance: %d min)',
                        $firstIn['time']->format('h:i A'),
                        $shiftStart->format('h:i A'),
                        $toleranceMinutes
                    ),
                ];
            }
        }

        // EARLY_LEAVE: last OUT punch < shift_end - tolerance
        if ($lastOut !== null) {
            $shiftEndTimestamp = $this->timeToMinutes($shiftEnd);
            $lastOutTimestamp = $this->timeToMinutes($lastOut['time']);
            $earlyThreshold = $shiftEndTimestamp - $toleranceMinutes;

            if ($lastOutTimestamp < $earlyThreshold) {
                $exceptions[] = [
                    'type' => 'EARLY_LEAVE',
                    'detail' => sprintf(
                        'Left at %s, shift ends at %s (tolerance: %d min)',
                        $lastOut['time']->format('h:i A'),
                        $shiftEnd->format('h:i A'),
                        $toleranceMinutes
                    ),
                ];
            }
        }

        return $exceptions;
    }

    /**
     * Convert a DateTimeImmutable (used as time) to minutes since midnight.
     */
    private function timeToMinutes(\DateTimeImmutable $time): int
    {
        return (int) $time->format('H') * 60 + (int) $time->format('i');
    }
}
