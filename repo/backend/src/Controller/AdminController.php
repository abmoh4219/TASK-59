<?php

namespace App\Controller;

use App\Entity\ExceptionRule;
use App\Entity\FailedLoginAttempt;
use App\Entity\User;
use App\Repository\ExceptionRuleRepository;
use App\Repository\FailedLoginAttemptRepository;
use App\Repository\UserRepository;
use App\Service\AuditService;
use App\Service\EncryptionService;
use App\Service\MaskingService;
use App\Service\RateLimitService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * AdminController — system administration endpoints.
 * All routes require ROLE_ADMIN (enforced by security.yaml access_control).
 */
#[Route('/api/admin')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly ExceptionRuleRepository $ruleRepository,
        private readonly FailedLoginAttemptRepository $failedLoginRepository,
        private readonly AuditService $auditService,
        private readonly RateLimitService $rateLimitService,
        private readonly EncryptionService $encryptionService,
        private readonly MaskingService $maskingService,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/health', name: 'api_admin_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'role' => 'ROLE_ADMIN',
        ]);
    }

    /**
     * GET /api/admin/users — list all users (full identity visible).
     */
    #[Route('/users', name: 'api_admin_users_list', methods: ['GET'])]
    public function listUsers(Request $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $this->getUser();

        if (!$this->rateLimitService->checkStandardLimit($admin->getId())) {
            return $this->json(['error' => 'Rate limit exceeded'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $users = $this->userRepository->findAll();

        return $this->json(array_map([$this, 'serializeUserFull'], $users));
    }

    /**
     * POST /api/admin/users — create new user.
     */
    #[Route('/users', name: 'api_admin_users_create', methods: ['POST'])]
    public function createUser(Request $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $this->getUser();

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['error' => 'Invalid request body'], Response::HTTP_BAD_REQUEST);
        }

        $required = ['username', 'email', 'password', 'firstName', 'lastName', 'role'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->json(['error' => "Missing field: $field"], Response::HTTP_BAD_REQUEST);
            }
        }

        $user = new User();
        $user->setUsername($data['username']);
        $user->setEmail($data['email']);
        $user->setFirstName($data['firstName']);
        $user->setLastName($data['lastName']);
        $user->setRole($data['role']);
        $user->setIsActive(true);

        // Hash password
        $hashed = $this->passwordHasher->hashPassword($user, $data['password']);
        $user->setPasswordHash($hashed);

        // Encrypt phone if provided
        if (!empty($data['phone'])) {
            $user->setPhoneEncrypted($this->encryptionService->encrypt($data['phone']));
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->auditService->log(
            $admin,
            'CREATE',
            'User',
            $user->getId(),
            null,
            ['username' => $user->getUsername(), 'role' => $user->getRole()],
            $request,
        );

        return $this->json($this->serializeUserFull($user), Response::HTTP_CREATED);
    }

    /**
     * PUT /api/admin/users/{id} — update user (role, active status, etc.).
     */
    #[Route('/users/{id}', name: 'api_admin_users_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateUser(int $id, Request $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $this->getUser();

        $user = $this->userRepository->find($id);
        if ($user === null) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $oldValues = [
            'role' => $user->getRole(),
            'isActive' => $user->isActive(),
            'isOut' => $user->isOut(),
        ];

        if (isset($data['role'])) {
            $user->setRole($data['role']);
        }
        if (isset($data['isActive'])) {
            $user->setIsActive((bool) $data['isActive']);
        }
        if (isset($data['isOut'])) {
            $user->setIsOut((bool) $data['isOut']);
        }
        if (isset($data['firstName'])) {
            $user->setFirstName($data['firstName']);
        }
        if (isset($data['lastName'])) {
            $user->setLastName($data['lastName']);
        }
        if (!empty($data['phone'])) {
            $user->setPhoneEncrypted($this->encryptionService->encrypt($data['phone']));
        }

        $this->entityManager->flush();

        $this->auditService->log(
            $admin,
            'UPDATE',
            'User',
            $user->getId(),
            $oldValues,
            ['role' => $user->getRole(), 'isActive' => $user->isActive(), 'isOut' => $user->isOut()],
            $request,
        );

        return $this->json($this->serializeUserFull($user));
    }

    /**
     * POST /api/admin/users/{id}/delete-data — anonymize PII (soft delete).
     * Does NOT delete audit log records (retained 7 years) or attendance records.
     */
    #[Route('/users/{id}/delete-data', name: 'api_admin_users_delete_data', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deleteUserData(int $id, Request $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $this->getUser();

        $user = $this->userRepository->find($id);
        if ($user === null) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        if ($user->getDeletedAt() !== null) {
            return $this->json(['error' => 'User data already deleted'], Response::HTTP_BAD_REQUEST);
        }

        // Anonymize PII — DO NOT delete the record itself
        $oldValues = [
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'email' => $user->getEmail(),
        ];

        $user->setFirstName('Deleted');
        $user->setLastName('User');
        $user->setEmail('deleted_' . $user->getId() . '@removed.invalid');
        $user->setPhoneEncrypted(null);
        $user->setIsActive(false);
        $user->setDeletedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        // Write audit log — this record will NEVER be deleted (7-year retention)
        $this->auditService->log(
            $admin,
            'DATA_DELETION',
            'User',
            $user->getId(),
            $oldValues,
            ['anonymized' => true, 'deletedAt' => $user->getDeletedAt()->format(\DateTimeInterface::ATOM)],
            $request,
        );

        return $this->json([
            'message' => 'User data anonymized. Audit log and attendance records preserved per retention policy.',
        ]);
    }

    /**
     * GET /api/admin/config — get system config (exception rules).
     */
    #[Route('/config', name: 'api_admin_config_get', methods: ['GET'])]
    public function getConfig(): JsonResponse
    {
        $rules = $this->ruleRepository->findActiveRules();

        $config = [
            'rules' => array_map(fn(ExceptionRule $r) => [
                'id' => $r->getId(),
                'ruleType' => $r->getRuleType(),
                'toleranceMinutes' => $r->getToleranceMinutes(),
                'missedPunchWindowMinutes' => $r->getMissedPunchWindowMinutes(),
                'filingWindowDays' => $r->getFilingWindowDays(),
                'isActive' => $r->isActive(),
            ], $rules),
            'slaHours' => 24,
            'businessHoursStart' => '08:00',
            'businessHoursEnd' => '18:00',
            'escalationThresholdHours' => 2,
        ];

        return $this->json($config);
    }

    /**
     * PUT /api/admin/config — update exception rules.
     */
    #[Route('/config', name: 'api_admin_config_update', methods: ['PUT'])]
    public function updateConfig(Request $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $this->getUser();

        $data = json_decode($request->getContent(), true) ?? [];

        if (!empty($data['rules']) && is_array($data['rules'])) {
            foreach ($data['rules'] as $ruleData) {
                if (empty($ruleData['id'])) continue;
                $rule = $this->ruleRepository->find((int) $ruleData['id']);
                if ($rule === null) continue;

                $oldValues = [
                    'toleranceMinutes' => $rule->getToleranceMinutes(),
                    'missedPunchWindowMinutes' => $rule->getMissedPunchWindowMinutes(),
                    'filingWindowDays' => $rule->getFilingWindowDays(),
                ];

                if (isset($ruleData['toleranceMinutes'])) {
                    $rule->setToleranceMinutes((int) $ruleData['toleranceMinutes']);
                }
                if (isset($ruleData['missedPunchWindowMinutes'])) {
                    $rule->setMissedPunchWindowMinutes((int) $ruleData['missedPunchWindowMinutes']);
                }
                if (isset($ruleData['filingWindowDays'])) {
                    $rule->setFilingWindowDays((int) $ruleData['filingWindowDays']);
                }

                $this->auditService->log(
                    $admin,
                    'CONFIG_UPDATE',
                    'ExceptionRule',
                    $rule->getId(),
                    $oldValues,
                    [
                        'toleranceMinutes' => $rule->getToleranceMinutes(),
                        'missedPunchWindowMinutes' => $rule->getMissedPunchWindowMinutes(),
                        'filingWindowDays' => $rule->getFilingWindowDays(),
                    ],
                    $request,
                );
            }
        }

        $this->entityManager->flush();

        return $this->json(['message' => 'Configuration updated']);
    }

    /**
     * GET /api/admin/anomaly-alerts — recent failed login attempts.
     */
    #[Route('/anomaly-alerts', name: 'api_admin_anomaly_alerts', methods: ['GET'])]
    public function anomalyAlerts(): JsonResponse
    {
        $attempts = $this->entityManager->getRepository(FailedLoginAttempt::class)
            ->createQueryBuilder('a')
            ->where('a.attemptedAt >= :since')
            ->setParameter('since', new \DateTimeImmutable('-24 hours'))
            ->orderBy('a.attemptedAt', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        $data = array_map(fn(FailedLoginAttempt $a) => [
            'id' => $a->getId(),
            'username' => $a->getUsername(),
            'ipAddress' => $a->getIpAddress(),
            'attemptedAt' => $a->getAttemptedAt()->format(\DateTimeInterface::ATOM),
        ], $attempts);

        return $this->json($data);
    }

    /**
     * Serialize user with FULL identity (admin view).
     * Phone is decrypted and returned in full.
     */
    private function serializeUserFull(User $user): array
    {
        $phone = null;
        $phoneEnc = $user->getPhoneEncrypted();
        if ($phoneEnc !== null) {
            try {
                $phone = $this->encryptionService->decrypt($phoneEnc);
            } catch (\Throwable) {
                // Legacy/unencrypted value — return as-is (admin gets full access)
                $phone = $phoneEnc;
            }
        }

        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'role' => $user->getRole(),
            'phone' => $phone, // Full phone for admin
            'isActive' => $user->isActive(),
            'isOut' => $user->isOut(),
            'deletedAt' => $user->getDeletedAt()?->format(\DateTimeInterface::ATOM),
            'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
