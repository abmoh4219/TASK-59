<?php

namespace App\Service;

use App\Entity\ApprovalStep;
use App\Entity\User;
use App\Repository\UserRepository;

/**
 * SlaService — calculates SLA deadlines using business hours.
 *
 * Business hours: Mon-Fri 8:00 AM – 6:00 PM (configurable).
 * SLA: 24 business hours per approval step.
 * Escalation: if step not acted on within SLA + 2 hours → assign backup approver.
 */
class SlaService
{
    private int $businessStartHour = 8;
    private int $businessEndHour = 18;
    private int $businessMinutesPerDay; // 600 = 10 hours * 60

    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
        $this->businessMinutesPerDay = ($this->businessEndHour - $this->businessStartHour) * 60;
    }

    /**
     * Calculate SLA deadline by adding N business hours from a start time.
     * Skips weekends and non-business hours.
     */
    public function calculateSlaDeadline(
        \DateTimeImmutable $startTime,
        int $slaHours = 24,
    ): \DateTimeImmutable {
        $remainingMinutes = $slaHours * 60;
        $current = \DateTime::createFromImmutable($startTime);

        while ($remainingMinutes > 0) {
            $dayOfWeek = (int) $current->format('N'); // 1=Mon, 7=Sun

            // Skip weekends
            if ($dayOfWeek >= 6) {
                $current->modify('next Monday');
                $current->setTime($this->businessStartHour, 0);
                continue;
            }

            $currentHour = (int) $current->format('G');
            $currentMinute = (int) $current->format('i');
            $currentMinuteOfDay = $currentHour * 60 + $currentMinute;
            $businessStartMinute = $this->businessStartHour * 60;
            $businessEndMinute = $this->businessEndHour * 60;

            // Before business hours: jump to start
            if ($currentMinuteOfDay < $businessStartMinute) {
                $current->setTime($this->businessStartHour, 0);
                $currentMinuteOfDay = $businessStartMinute;
            }

            // After business hours: jump to next business day
            if ($currentMinuteOfDay >= $businessEndMinute) {
                $current->modify('+1 day');
                $current->setTime($this->businessStartHour, 0);
                continue;
            }

            // Minutes remaining today
            $minutesLeftToday = $businessEndMinute - $currentMinuteOfDay;

            if ($remainingMinutes <= $minutesLeftToday) {
                $current->modify("+{$remainingMinutes} minutes");
                $remainingMinutes = 0;
            } else {
                $remainingMinutes -= $minutesLeftToday;
                $current->modify('+1 day');
                $current->setTime($this->businessStartHour, 0);
            }
        }

        return \DateTimeImmutable::createFromMutable($current);
    }

    /**
     * Check if an approval step is overdue (past SLA deadline).
     */
    public function isOverdue(ApprovalStep $step): bool
    {
        if ($step->getSlaDeadline() === null || $step->getActedAt() !== null) {
            return false;
        }

        return new \DateTimeImmutable() > $step->getSlaDeadline();
    }

    /**
     * Get remaining business minutes until SLA deadline.
     * Returns negative if overdue.
     */
    public function getRemainingMinutes(ApprovalStep $step): int
    {
        if ($step->getSlaDeadline() === null) {
            return 0;
        }

        $now = new \DateTimeImmutable();
        $deadline = $step->getSlaDeadline();

        if ($now >= $deadline) {
            // Overdue — return negative minutes (at least -1 to signal overdue)
            $minutes = $this->countBusinessMinutes($deadline, $now);
            return -max(1, $minutes);
        }

        return $this->countBusinessMinutes($now, $deadline);
    }

    /**
     * Check if step is past escalation threshold (SLA + 2 hours overdue).
     */
    public function shouldEscalate(ApprovalStep $step): bool
    {
        if ($step->getSlaDeadline() === null || $step->getActedAt() !== null || $step->getEscalatedAt() !== null) {
            return false;
        }

        $escalationDeadline = $this->calculateSlaDeadline($step->getSlaDeadline(), 2);

        return new \DateTimeImmutable() > $escalationDeadline;
    }

    /**
     * Get backup approver for a user. Falls back to HR Admin.
     */
    public function getBackupApprover(int $userId): ?User
    {
        $user = $this->userRepository->find($userId);
        if ($user === null) {
            return null;
        }

        // Check designated backup
        $backupId = $user->getBackupApproverId();
        if ($backupId !== null) {
            $backup = $this->userRepository->find($backupId);
            if ($backup !== null && $backup->isActive() && !$backup->isOut()) {
                return $backup;
            }
        }

        // Fallback to first available HR Admin
        $hrAdmins = $this->userRepository->findByRole('ROLE_HR_ADMIN');
        foreach ($hrAdmins as $admin) {
            if ($admin->isActive() && !$admin->isOut() && $admin->getId() !== $userId) {
                return $admin;
            }
        }

        return null;
    }

    /**
     * Count business minutes between two DateTimeImmutable instances.
     * Counts only minutes within Mon-Fri business hours (default 8-18).
     */
    private function countBusinessMinutes(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        if ($from >= $to) {
            return 0;
        }

        $total = 0;
        $current = \DateTime::createFromImmutable($from);
        $endTs = $to->getTimestamp();
        $businessStartMin = $this->businessStartHour * 60;
        $businessEndMin = $this->businessEndHour * 60;

        // Safety limit: 30 business days max
        for ($safety = 0; $safety < 1000 && $current->getTimestamp() < $endTs; $safety++) {
            $dayOfWeek = (int) $current->format('N'); // 1=Mon, 7=Sun

            // Skip weekends
            if ($dayOfWeek >= 6) {
                $current->setTime($this->businessStartHour, 0);
                $current->modify('+1 day');
                continue;
            }

            $currentMin = (int) $current->format('G') * 60 + (int) $current->format('i');

            // Before business hours: jump to start
            if ($currentMin < $businessStartMin) {
                $current->setTime($this->businessStartHour, 0);
                continue;
            }

            // After business hours: jump to next day's start
            if ($currentMin >= $businessEndMin) {
                $current->modify('+1 day');
                $current->setTime($this->businessStartHour, 0);
                continue;
            }

            // Compute end-of-business-today as timestamp
            $eodToday = clone $current;
            $eodToday->setTime($this->businessEndHour, 0);
            $eodTs = $eodToday->getTimestamp();

            // Take minimum of end-of-day, $endTs
            $limitTs = min($eodTs, $endTs);
            $minutesToAdd = (int) (($limitTs - $current->getTimestamp()) / 60);
            if ($minutesToAdd > 0) {
                $total += $minutesToAdd;
            }

            // Advance to next business day start
            $current->modify('+1 day');
            $current->setTime($this->businessStartHour, 0);
        }

        return $total;
    }
}
