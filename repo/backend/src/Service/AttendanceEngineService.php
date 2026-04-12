<?php

namespace App\Service;

use App\Entity\AttendanceRecord;
use App\Entity\PunchEvent;
use App\Entity\User;
use App\Repository\AttendanceRecordRepository;
use App\Repository\ExceptionRequestRepository;
use App\Repository\ExceptionRuleRepository;
use App\Repository\PunchEventRepository;
use App\Repository\ShiftScheduleRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * AttendanceEngineService — processes attendance for all users on a given date.
 *
 * Runs nightly at 2:00 AM via Symfony Console command + cron.
 * Also triggerable via: php bin/console app:process-attendance --date=YYYY-MM-DD
 *
 * Deterministic: calling processDate() twice with the same data produces the same result.
 * Upserts AttendanceRecord — creates if missing, updates if exists.
 */
class AttendanceEngineService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly ShiftScheduleRepository $shiftScheduleRepository,
        private readonly PunchEventRepository $punchEventRepository,
        private readonly AttendanceRecordRepository $attendanceRecordRepository,
        private readonly ExceptionRuleRepository $exceptionRuleRepository,
        private readonly ExceptionDetectionService $exceptionDetectionService,
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * Process attendance for all active users on the given date.
     * Deterministic: same inputs always produce the same exception set.
     *
     * @return array Summary of processing results
     */
    public function processDate(\DateTimeImmutable $date): array
    {
        $summary = ['processed' => 0, 'exceptions_found' => 0, 'records_created' => 0, 'records_updated' => 0];

        // Load active exception rules
        $ruleEntities = $this->exceptionRuleRepository->findActiveRules();
        $rules = [
            'toleranceMinutes' => 5,
            'missedPunchWindowMinutes' => 30,
        ];
        foreach ($ruleEntities as $rule) {
            $rules['toleranceMinutes'] = $rule->getToleranceMinutes();
            $rules['missedPunchWindowMinutes'] = $rule->getMissedPunchWindowMinutes();
        }

        // Get day of week (1=Monday ... 7=Sunday in PHP, but we store 0=Sunday ... 6=Saturday)
        $dayOfWeek = (int) $date->format('w'); // 0=Sunday

        // Process each active user
        $users = $this->userRepository->findBy(['isActive' => true]);

        foreach ($users as $user) {
            $this->processUserDate($user, $date, $dayOfWeek, $rules, $summary);
        }

        $this->entityManager->flush();

        return $summary;
    }

    private function processUserDate(
        User $user,
        \DateTimeImmutable $date,
        int $dayOfWeek,
        array $rules,
        array &$summary,
    ): void {
        $summary['processed']++;

        // Get shift schedule for this day
        $schedules = $this->shiftScheduleRepository->findBy([
            'user' => $user,
            'dayOfWeek' => $dayOfWeek,
            'isActive' => true,
        ]);

        if (empty($schedules)) {
            return; // No schedule for this day — skip
        }

        $schedule = $schedules[0];
        $scheduleData = [
            'shiftStart' => $schedule->getShiftStart(),
            'shiftEnd' => $schedule->getShiftEnd(),
        ];

        // Get punch events for this user on this date
        $punchEntities = $this->punchEventRepository->findBy([
            'user' => $user,
            'eventDate' => $date,
        ]);

        $punches = array_map(fn(PunchEvent $p) => [
            'time' => $p->getEventTime(),
            'type' => $p->getEventType(),
        ], $punchEntities);

        // Check for approved offsite requests covering this date
        $hasApprovedOffsite = $this->hasApprovedOffsiteRequest($user, $date);

        // Detect exceptions (pure, deterministic)
        $exceptions = $this->exceptionDetectionService->detectExceptions(
            $punches,
            $scheduleData,
            $rules,
            $hasApprovedOffsite,
        );

        $exceptionTypes = array_map(fn($e) => $e['type'], $exceptions);
        $summary['exceptions_found'] += count($exceptionTypes);

        // Calculate total hours
        $totalMinutes = $this->calculateTotalMinutes($punches);

        // Get first IN and last OUT
        $inPunches = array_filter($punches, fn($p) => $p['type'] === 'IN');
        $outPunches = array_filter($punches, fn($p) => $p['type'] === 'OUT');
        usort($inPunches, fn($a, $b) => $a['time'] <=> $b['time']);
        usort($outPunches, fn($a, $b) => $a['time'] <=> $b['time']);
        $firstIn = !empty($inPunches) ? reset($inPunches)['time'] : null;
        $lastOut = !empty($outPunches) ? end($outPunches)['time'] : null;

        // Upsert AttendanceRecord
        $existing = $this->attendanceRecordRepository->findOneBy([
            'user' => $user,
            'recordDate' => $date,
        ]);

        if ($existing !== null) {
            $oldExceptions = $existing->getExceptions();
            $existing->setFirstPunchIn($firstIn);
            $existing->setLastPunchOut($lastOut);
            $existing->setTotalMinutes($totalMinutes);
            $existing->setExceptions($exceptionTypes);
            $summary['records_updated']++;

            if ($oldExceptions !== $exceptionTypes) {
                $this->auditService->log(
                    null,
                    'ATTENDANCE_UPDATED',
                    'AttendanceRecord',
                    $existing->getId(),
                    ['exceptions' => $oldExceptions],
                    ['exceptions' => $exceptionTypes],
                );
            }
        } else {
            $record = new AttendanceRecord();
            $record->setUser($user);
            $record->setRecordDate($date);
            $record->setFirstPunchIn($firstIn);
            $record->setLastPunchOut($lastOut);
            $record->setTotalMinutes($totalMinutes);
            $record->setExceptions($exceptionTypes);
            $this->entityManager->persist($record);
            $summary['records_created']++;
        }
    }

    /**
     * Calculate total worked minutes from punch pairs.
     */
    private function calculateTotalMinutes(array $punches): int
    {
        $sorted = $punches;
        usort($sorted, fn($a, $b) => $a['time'] <=> $b['time']);

        $total = 0;
        $lastIn = null;

        foreach ($sorted as $punch) {
            if ($punch['type'] === 'IN') {
                $lastIn = $punch['time'];
            } elseif ($punch['type'] === 'OUT' && $lastIn !== null) {
                $inMinutes = (int) $lastIn->format('H') * 60 + (int) $lastIn->format('i');
                $outMinutes = (int) $punch['time']->format('H') * 60 + (int) $punch['time']->format('i');
                $total += max(0, $outMinutes - $inMinutes);
                $lastIn = null;
            }
        }

        return $total;
    }

    /**
     * Check if user has an approved BUSINESS_TRIP or OUTING request covering the date.
     */
    private function hasApprovedOffsiteRequest(User $user, \DateTimeImmutable $date): bool
    {
        $repo = $this->entityManager->getRepository(\App\Entity\ExceptionRequest::class);
        $qb = $repo->createQueryBuilder('r')
            ->where('r.user = :user')
            ->andWhere('r.status = :status')
            ->andWhere('r.requestType IN (:types)')
            ->andWhere('r.startDate <= :date')
            ->andWhere('r.endDate >= :date')
            ->setParameter('user', $user)
            ->setParameter('status', 'APPROVED')
            ->setParameter('types', ['BUSINESS_TRIP', 'OUTING'])
            ->setParameter('date', $date);

        return (int) $qb->select('COUNT(r.id)')->getQuery()->getSingleScalarResult() > 0;
    }
}
