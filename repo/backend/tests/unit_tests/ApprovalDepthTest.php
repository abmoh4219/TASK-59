<?php

namespace App\Tests\UnitTests;

use App\Entity\ApprovalStep;
use App\Entity\ExceptionRequest;
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
 * Locks in the approval step-depth matrix per Prompt:
 *   - CORRECTION / OUTING   -> 1 step (Supervisor)
 *   - PTO                   -> 2 steps (Supervisor, HR Admin)
 *   - LEAVE / BUSINESS_TRIP -> 3 steps (Supervisor, HR Admin, System Admin)
 *
 * Also asserts that step 1 is routed to the REQUESTER'S supervisor via
 * supervisorId, not a global-first lookup.
 */
class ApprovalDepthTest extends TestCase
{
    private ApprovalWorkflowService $service;
    private EntityManagerInterface $em;
    private UserRepository $userRepo;
    /** @var array<int, object> objects persist()ed during a call */
    private array $persisted = [];
    private ?ExceptionRequest $savedRequest = null;

    protected function setUp(): void
    {
        $this->persisted = [];
        $this->savedRequest = null;

        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->method('persist')->willReturnCallback(function ($obj) {
            $this->persisted[] = $obj;
            if ($obj instanceof ExceptionRequest) {
                $this->savedRequest = $obj;
                // Assign an id so createRequest's post-persist logic has something to store.
                $ref = new \ReflectionProperty(ExceptionRequest::class, 'id');
                $ref->setAccessible(true);
                $ref->setValue($obj, 123);
            }
        });

        $exceptionRepo = $this->createMock(ExceptionRequestRepository::class);
        $stepRepo = $this->createMock(ApprovalStepRepository::class);
        $idempRepo = $this->createMock(IdempotencyKeyRepository::class);
        $this->userRepo = $this->createMock(UserRepository::class);
        $slaService = $this->createMock(SlaService::class);
        $slaService->method('calculateSlaDeadline')->willReturn(new \DateTimeImmutable('+1 day'));
        $audit = $this->createMock(AuditService::class);

        $this->service = new ApprovalWorkflowService(
            $this->em,
            $exceptionRepo,
            $stepRepo,
            $idempRepo,
            $this->userRepo,
            $slaService,
            $audit,
        );
    }

    private function makeUser(int $id, string $role, ?int $supervisorId = null): User
    {
        $u = new User();
        $u->setUsername("u$id");
        $u->setEmail("u$id@t.invalid");
        $u->setFirstName('F');
        $u->setLastName('L');
        $u->setRole($role);
        $u->setIsActive(true);
        $u->setPasswordHash('x');
        if ($supervisorId !== null) {
            $u->setSupervisorId($supervisorId);
        }
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($u, $id);
        return $u;
    }

    /**
     * @return ApprovalStep[] steps persisted during createRequest, ordered by stepNumber
     */
    private function runCreate(User $requester, string $requestType): array
    {
        $data = [
            'startDate' => (new \DateTimeImmutable())->format('Y-m-d'),
            'endDate' => (new \DateTimeImmutable())->format('Y-m-d'),
            'reason' => 'test',
        ];
        $this->service->createRequest($requester, $requestType, $data, null);
        $steps = array_values(array_filter(
            $this->persisted,
            fn($o) => $o instanceof ApprovalStep,
        ));
        usort($steps, fn($a, $b) => $a->getStepNumber() <=> $b->getStepNumber());
        return $steps;
    }

    public function testCorrectionFollowsOneStepPath(): void
    {
        $supervisor = $this->makeUser(10, 'ROLE_SUPERVISOR');
        $requester = $this->makeUser(20, 'ROLE_EMPLOYEE', 10);

        $this->userRepo->method('find')->willReturnMap([[10, $supervisor]]);
        $this->userRepo->method('findByRole')->willReturn([$supervisor]);

        $steps = $this->runCreate($requester, 'CORRECTION');

        $this->assertCount(1, $steps);
        $this->assertSame(1, $steps[0]->getStepNumber());
        $this->assertSame(10, $steps[0]->getApprover()->getId());
    }

    public function testPtoFollowsTwoStepPath(): void
    {
        $supervisor = $this->makeUser(10, 'ROLE_SUPERVISOR');
        $hrAdmin = $this->makeUser(30, 'ROLE_HR_ADMIN');
        $requester = $this->makeUser(20, 'ROLE_EMPLOYEE', 10);

        $this->userRepo->method('find')->willReturnMap([[10, $supervisor]]);
        $this->userRepo->method('findByRole')->willReturnCallback(
            fn(string $role) => match ($role) {
                'ROLE_SUPERVISOR' => [$supervisor],
                'ROLE_HR_ADMIN' => [$hrAdmin],
                default => [],
            },
        );

        $steps = $this->runCreate($requester, 'PTO');

        $this->assertCount(2, $steps);
        $this->assertSame('ROLE_SUPERVISOR', $steps[0]->getApprover()->getRole());
        $this->assertSame('ROLE_HR_ADMIN', $steps[1]->getApprover()->getRole());
    }

    public function testBusinessTripFollowsThreeStepPath(): void
    {
        $supervisor = $this->makeUser(10, 'ROLE_SUPERVISOR');
        $hrAdmin = $this->makeUser(30, 'ROLE_HR_ADMIN');
        $sysAdmin = $this->makeUser(40, 'ROLE_ADMIN');
        $requester = $this->makeUser(20, 'ROLE_EMPLOYEE', 10);

        $this->userRepo->method('find')->willReturnMap([[10, $supervisor]]);
        $this->userRepo->method('findByRole')->willReturnCallback(
            fn(string $role) => match ($role) {
                'ROLE_SUPERVISOR' => [$supervisor],
                'ROLE_HR_ADMIN' => [$hrAdmin],
                'ROLE_ADMIN' => [$sysAdmin],
                default => [],
            },
        );

        $steps = $this->runCreate($requester, 'BUSINESS_TRIP');

        $this->assertCount(3, $steps);
        $this->assertSame(1, $steps[0]->getStepNumber());
        $this->assertSame(2, $steps[1]->getStepNumber());
        $this->assertSame(3, $steps[2]->getStepNumber());
        $this->assertSame('ROLE_SUPERVISOR', $steps[0]->getApprover()->getRole());
        $this->assertSame('ROLE_HR_ADMIN', $steps[1]->getApprover()->getRole());
        $this->assertSame('ROLE_ADMIN', $steps[2]->getApprover()->getRole());
    }

    public function testLeaveFollowsThreeStepPath(): void
    {
        $supervisor = $this->makeUser(10, 'ROLE_SUPERVISOR');
        $hrAdmin = $this->makeUser(30, 'ROLE_HR_ADMIN');
        $sysAdmin = $this->makeUser(40, 'ROLE_ADMIN');
        $requester = $this->makeUser(20, 'ROLE_EMPLOYEE', 10);

        $this->userRepo->method('find')->willReturnMap([[10, $supervisor]]);
        $this->userRepo->method('findByRole')->willReturnCallback(
            fn(string $role) => match ($role) {
                'ROLE_SUPERVISOR' => [$supervisor],
                'ROLE_HR_ADMIN' => [$hrAdmin],
                'ROLE_ADMIN' => [$sysAdmin],
                default => [],
            },
        );

        $steps = $this->runCreate($requester, 'LEAVE');

        $this->assertCount(3, $steps);
    }

    public function testStep1RoutedToRequesterOwnSupervisorNotGlobalFirst(): void
    {
        $unrelated = $this->makeUser(5, 'ROLE_SUPERVISOR');
        $ownSupervisor = $this->makeUser(11, 'ROLE_SUPERVISOR');
        $requester = $this->makeUser(21, 'ROLE_EMPLOYEE', 11); // supervisorId=11

        // The directory contains the unrelated supervisor FIRST; the previous
        // global-first bug would return id=5. The fix must return id=11.
        $this->userRepo->method('find')->willReturnMap([[11, $ownSupervisor]]);
        $this->userRepo->method('findByRole')->willReturnCallback(
            fn(string $role) => $role === 'ROLE_SUPERVISOR'
                ? [$unrelated, $ownSupervisor]
                : [],
        );

        $steps = $this->runCreate($requester, 'CORRECTION');

        $this->assertCount(1, $steps);
        $this->assertSame(
            11,
            $steps[0]->getApprover()->getId(),
            'Step 1 must route to the requester\'s own supervisor, not global-first',
        );
    }

    public function testFallbackToFirstActiveSupervisorWhenNoLinkage(): void
    {
        // Legacy account with no supervisorId: fallback kicks in.
        $supervisor = $this->makeUser(77, 'ROLE_SUPERVISOR');
        $requester = $this->makeUser(22, 'ROLE_EMPLOYEE', null);

        $this->userRepo->method('find')->willReturn(null);
        $this->userRepo->method('findByRole')->willReturnCallback(
            fn(string $role) => $role === 'ROLE_SUPERVISOR' ? [$supervisor] : [],
        );

        $steps = $this->runCreate($requester, 'CORRECTION');
        $this->assertCount(1, $steps);
        $this->assertSame(77, $steps[0]->getApprover()->getId());
    }
}
