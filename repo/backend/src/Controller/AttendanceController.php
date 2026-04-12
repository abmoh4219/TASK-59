<?php

namespace App\Controller;

use App\Entity\AttendanceRecord;
use App\Entity\PunchEvent;
use App\Entity\User;
use App\Repository\AttendanceRecordRepository;
use App\Repository\ExceptionRuleRepository;
use App\Repository\PunchEventRepository;
use App\Repository\ShiftScheduleRepository;
use App\Service\AuditService;
use App\Service\MaskingService;
use App\Service\RateLimitService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/attendance')]
class AttendanceController extends AbstractController
{
    public function __construct(
        private readonly AttendanceRecordRepository $attendanceRecordRepository,
        private readonly PunchEventRepository $punchEventRepository,
        private readonly ShiftScheduleRepository $shiftScheduleRepository,
        private readonly ExceptionRuleRepository $exceptionRuleRepository,
        private readonly RateLimitService $rateLimitService,
        private readonly AuditService $auditService,
        private readonly MaskingService $maskingService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * GET /api/attendance/today — current user's attendance card for today.
     */
    #[Route('/today', name: 'api_attendance_today', methods: ['GET'], priority: 10)]
    public function today(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->rateLimitService->checkStandardLimit($user->getId())) {
            return $this->json(['error' => 'Rate limit exceeded'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $today = new \DateTimeImmutable('today');
        return $this->getAttendanceCardResponse($user, $today);
    }

    /**
     * GET /api/attendance/{date} — attendance card for a specific date.
     */
    #[Route('/{date}', name: 'api_attendance_date', methods: ['GET'], requirements: ['date' => '\d{4}-\d{2}-\d{2}'])]
    public function byDate(string $date, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->rateLimitService->checkStandardLimit($user->getId())) {
            return $this->json(['error' => 'Rate limit exceeded'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $dateObj = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if ($dateObj === false) {
            return $this->json(['error' => 'Invalid date format'], Response::HTTP_BAD_REQUEST);
        }

        return $this->getAttendanceCardResponse($user, $dateObj->setTime(0, 0, 0));
    }

    /**
     * GET /api/attendance/history — paginated attendance history.
     */
    #[Route('/history', name: 'api_attendance_history', methods: ['GET'], priority: 10)]
    public function history(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->rateLimitService->checkStandardLimit($user->getId())) {
            return $this->json(['error' => 'Rate limit exceeded'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 20)));

        $qb = $this->attendanceRecordRepository->createQueryBuilder('a')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.recordDate', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $from = $request->query->get('from');
        $to = $request->query->get('to');
        if ($from) {
            $fromDate = \DateTimeImmutable::createFromFormat('Y-m-d', $from);
            if ($fromDate) {
                $qb->andWhere('a.recordDate >= :from')->setParameter('from', $fromDate->setTime(0, 0, 0));
            }
        }
        if ($to) {
            $toDate = \DateTimeImmutable::createFromFormat('Y-m-d', $to);
            if ($toDate) {
                $qb->andWhere('a.recordDate <= :to')->setParameter('to', $toDate->setTime(23, 59, 59));
            }
        }

        $records = $qb->getQuery()->getResult();

        $countQb = $this->attendanceRecordRepository->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.user = :user')
            ->setParameter('user', $user);
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        $data = array_map(fn(AttendanceRecord $r) => $this->serializeRecord($r), $records);

        return $this->json([
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * GET /api/attendance/rules — get active exception rules (for policy hints).
     */
    #[Route('/rules', name: 'api_attendance_rules', methods: ['GET'], priority: 10)]
    public function rules(): JsonResponse
    {
        $rules = $this->exceptionRuleRepository->findActiveRules();
        $data = array_map(fn($r) => [
            'ruleType' => $r->getRuleType(),
            'toleranceMinutes' => $r->getToleranceMinutes(),
            'missedPunchWindowMinutes' => $r->getMissedPunchWindowMinutes(),
            'filingWindowDays' => $r->getFilingWindowDays(),
        ], $rules);

        return $this->json($data);
    }

    private function getAttendanceCardResponse(User $user, \DateTimeImmutable $date): JsonResponse
    {
        // Get attendance record
        $record = $this->attendanceRecordRepository->findOneBy([
            'user' => $user,
            'recordDate' => $date,
        ]);

        // Get punch events
        $punches = $this->punchEventRepository->findBy(
            ['user' => $user, 'eventDate' => $date],
            ['eventTime' => 'ASC'],
        );

        // Get shift schedule
        $dayOfWeek = (int) $date->format('w');
        $schedules = $this->shiftScheduleRepository->findBy([
            'user' => $user,
            'dayOfWeek' => $dayOfWeek,
            'isActive' => true,
        ]);
        $schedule = $schedules[0] ?? null;

        // Get rules for policy hints
        $rules = $this->exceptionRuleRepository->findActiveRules();
        $ruleData = [];
        foreach ($rules as $rule) {
            $ruleData[] = [
                'ruleType' => $rule->getRuleType(),
                'toleranceMinutes' => $rule->getToleranceMinutes(),
                'missedPunchWindowMinutes' => $rule->getMissedPunchWindowMinutes(),
                'filingWindowDays' => $rule->getFilingWindowDays(),
            ];
        }

        $punchData = array_map(fn(PunchEvent $p) => [
            'id' => $p->getId(),
            'eventTime' => $p->getEventTime()->format('H:i:s'),
            'eventType' => $p->getEventType(),
        ], $punches);

        return $this->json([
            'recordDate' => $date->format('Y-m-d'),
            'shiftStart' => $schedule?->getShiftStart()?->format('H:i'),
            'shiftEnd' => $schedule?->getShiftEnd()?->format('H:i'),
            'firstPunchIn' => $record?->getFirstPunchIn()?->format('H:i'),
            'lastPunchOut' => $record?->getLastPunchOut()?->format('H:i'),
            'totalMinutes' => $record?->getTotalMinutes() ?? 0,
            'exceptions' => $record?->getExceptions() ?? [],
            'punches' => $punchData,
            'rules' => $ruleData,
        ]);
    }

    private function serializeRecord(AttendanceRecord $record): array
    {
        return [
            'id' => $record->getId(),
            'recordDate' => $record->getRecordDate()->format('Y-m-d'),
            'firstPunchIn' => $record->getFirstPunchIn()?->format('H:i'),
            'lastPunchOut' => $record->getLastPunchOut()?->format('H:i'),
            'totalMinutes' => $record->getTotalMinutes(),
            'exceptions' => $record->getExceptions(),
        ];
    }
}
