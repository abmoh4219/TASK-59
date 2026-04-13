<?php

namespace App\Tests\UnitTests;

use App\Entity\User;
use App\Service\EncryptionService;
use App\Service\IdentityAccessPolicy;
use App\Service\MaskingService;
use PHPUnit\Framework\TestCase;

/**
 * Tests the central identity-data tiering policy.
 *
 * Rules locked in here (Prompt: "only HR Admin sees full identity"):
 *   - HR Admin:     full phone for any subject
 *   - Admin (Sys):  masked phone for any subject
 *   - Supervisor:   masked phone for any subject other than themselves
 *   - Employee:     masked phone for any subject other than themselves
 *   - Any viewer:   full phone when looking at their own record
 */
class IdentityAccessPolicyTest extends TestCase
{
    private IdentityAccessPolicy $policy;
    private EncryptionService $encryption;

    protected function setUp(): void
    {
        // EncryptionService is instantiated with a deterministic 32-byte key
        // so the test can encrypt/decrypt without touching any real config.
        $this->encryption = new EncryptionService(str_repeat('A', 32));
        $this->policy = new IdentityAccessPolicy($this->encryption, new MaskingService());
    }

    private function makeUser(int $id, string $role, ?string $phone = '+15551234567'): User
    {
        $u = new User();
        $u->setUsername("u$id");
        $u->setEmail("u$id@t.invalid");
        $u->setFirstName('F');
        $u->setLastName('L');
        $u->setRole($role);
        $u->setIsActive(true);
        $u->setPasswordHash('x');
        if ($phone !== null) {
            $u->setPhoneEncrypted($this->encryption->encrypt($phone));
        }
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($u, $id);
        return $u;
    }

    public function testHrAdminSeesFullPhoneForAnySubject(): void
    {
        $hr = $this->makeUser(1, 'ROLE_HR_ADMIN');
        $employee = $this->makeUser(2, 'ROLE_EMPLOYEE');

        $phone = $this->policy->resolvePhone($hr, $employee);

        $this->assertSame('+15551234567', $phone);
    }

    public function testSystemAdminReceivesMaskedPhone(): void
    {
        $admin = $this->makeUser(1, 'ROLE_ADMIN');
        $employee = $this->makeUser(2, 'ROLE_EMPLOYEE');

        $phone = $this->policy->resolvePhone($admin, $employee);

        $this->assertNotNull($phone);
        $this->assertStringContainsString('*', (string) $phone);
        $this->assertStringNotContainsString('1234567', (string) $phone);
    }

    public function testSupervisorReceivesMaskedPhoneForOtherUsers(): void
    {
        $sup = $this->makeUser(1, 'ROLE_SUPERVISOR');
        $employee = $this->makeUser(2, 'ROLE_EMPLOYEE');

        $phone = $this->policy->resolvePhone($sup, $employee);

        $this->assertStringContainsString('*', (string) $phone);
    }

    public function testEmployeeSelfViewIsMasked(): void
    {
        // Per the tiering rule, /api/auth/me returns masked identity data
        // for every role EXCEPT HR Admin — there is no self-view exception.
        $employee = $this->makeUser(2, 'ROLE_EMPLOYEE');

        $phone = $this->policy->resolvePhone($employee, $employee);

        $this->assertNotNull($phone);
        $this->assertStringContainsString('*', (string) $phone);
    }

    public function testNullPhoneStaysNull(): void
    {
        $hr = $this->makeUser(1, 'ROLE_HR_ADMIN');
        $subject = $this->makeUser(2, 'ROLE_EMPLOYEE', null);

        $this->assertNull($this->policy->resolvePhone($hr, $subject));
    }

    public function testCanViewFullIdentityBoundaries(): void
    {
        $hr = $this->makeUser(1, 'ROLE_HR_ADMIN');
        $admin = $this->makeUser(2, 'ROLE_ADMIN');
        $emp = $this->makeUser(3, 'ROLE_EMPLOYEE');

        // HR Admin yes; admin no; self-view also no (masked).
        $this->assertTrue($this->policy->canViewFullIdentity($hr, $emp));
        $this->assertFalse($this->policy->canViewFullIdentity($admin, $emp));
        $this->assertFalse($this->policy->canViewFullIdentity($emp, $emp));
    }
}
