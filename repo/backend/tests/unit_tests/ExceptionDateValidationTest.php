<?php

namespace App\Tests\UnitTests;

use App\Entity\User;
use App\Repository\ApprovalStepRepository;
use App\Repository\ExceptionRequestRepository;
use App\Repository\IdempotencyKeyRepository;
use App\Repository\UserRepository;
use App\Service\ApprovalWorkflowService;
use App\Service\AuditService;
use App\Service\SlaService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Strict server-side temporal validation on exception request creation.
 *
 * Every assertion here exercises ApprovalWorkflowService::createRequest —
 * the same code path the controller calls. The controller then translates
 * any \InvalidArgumentException to HTTP 400.
 */
class ExceptionDateValidationTest extends TestCase
{
    private ApprovalWorkflowService $service;
    private UserRepository $userRepo;

    protected function setUp(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $exceptionRepo = $this->createMock(ExceptionRequestRepository::class);
        $stepRepo = $this->createMock(ApprovalStepRepository::class);
        $idempRepo = $this->createMock(IdempotencyKeyRepository::class);
        $this->userRepo = $this->createMock(UserRepository::class);
        $sla = $this->createMock(SlaService::class);
        $sla->method('calculateSlaDeadline')->willReturn(new \DateTimeImmutable('+1 day'));
        $audit = $this->createMock(AuditService::class);

        // No supervisor available — step 1 creation short-circuits, which is
        // fine: date validation runs before createApprovalSteps() does.
        $this->userRepo->method('findByRole')->willReturn([]);
        $this->userRepo->method('find')->willReturn(null);

        $this->service = new ApprovalWorkflowService(
            $em,
            $exceptionRepo,
            $stepRepo,
            $idempRepo,
            $this->userRepo,
            $sla,
            $audit,
        );
    }

    private function makeUser(): User
    {
        $u = new User();
        $u->setUsername('emp');
        $u->setEmail('emp@t.invalid');
        $u->setFirstName('E');
        $u->setLastName('E');
        $u->setRole('ROLE_EMPLOYEE');
        $u->setIsActive(true);
        $u->setPasswordHash('x');
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($u, 1);
        return $u;
    }

    public function testMalformedStartDateThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('startDate must be a valid');

        $this->service->createRequest($this->makeUser(), 'CORRECTION', [
            'startDate' => 'not-a-date',
            'endDate' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'reason' => 'test',
        ]);
    }

    public function testEndBeforeStartThrows(): void
    {
        $today = new \DateTimeImmutable('today');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('endDate must be on or after startDate');

        $this->service->createRequest($this->makeUser(), 'CORRECTION', [
            'startDate' => $today->format('Y-m-d'),
            'endDate' => $today->modify('-1 day')->format('Y-m-d'),
            'reason' => 'test',
        ]);
    }

    public function testCorrectionCannotTargetFutureDate(): void
    {
        $future = (new \DateTimeImmutable('today'))->modify('+3 days')->format('Y-m-d');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CORRECTION requests cannot target future');

        $this->service->createRequest($this->makeUser(), 'CORRECTION', [
            'startDate' => $future,
            'endDate' => $future,
            'reason' => 'test',
        ]);
    }

    public function testPtoCannotCoverPastDate(): void
    {
        $past = (new \DateTimeImmutable('today'))->modify('-3 days')->format('Y-m-d');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot cover a date in the past');

        $this->service->createRequest($this->makeUser(), 'PTO', [
            'startDate' => $past,
            'endDate' => $past,
            'reason' => 'test',
        ]);
    }

    public function testFilingWindowExpiredForCorrection(): void
    {
        // startDate 10 days ago -> filing window (7 days) expired.
        $old = (new \DateTimeImmutable('today'))->modify('-10 days')->format('Y-m-d');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Filing window expired');

        $this->service->createRequest($this->makeUser(), 'CORRECTION', [
            'startDate' => $old,
            'endDate' => $old,
            'reason' => 'test',
        ]);
    }

    public function testSameDayEndTimeBeforeStartTimeThrows(): void
    {
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('endTime must be after startTime');

        $this->service->createRequest($this->makeUser(), 'CORRECTION', [
            'startDate' => $today,
            'endDate' => $today,
            'startTime' => '17:00',
            'endTime' => '09:00',
            'reason' => 'test',
        ]);
    }

    public function testMalformedTimeThrows(): void
    {
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('startTime must be');

        $this->service->createRequest($this->makeUser(), 'CORRECTION', [
            'startDate' => $today,
            'endDate' => $today,
            'startTime' => 'nope',
            'reason' => 'test',
        ]);
    }

    public function testParseHelperAcceptsMmDdYyyy(): void
    {
        $date = $this->service->parseDate('04/13/2026', 'startDate');
        $this->assertSame('2026-04-13', $date->format('Y-m-d'));
    }

    public function testParseHelperRejectsInvalidCalendarDate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->parseDate('02/30/2026', 'startDate');
    }
}
