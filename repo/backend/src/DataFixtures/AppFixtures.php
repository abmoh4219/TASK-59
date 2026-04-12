<?php

namespace App\DataFixtures;

use App\Entity\AttendanceRecord;
use App\Entity\Booking;
use App\Entity\BookingAllocation;
use App\Entity\ExceptionRule;
use App\Entity\PunchEvent;
use App\Entity\Resource;
use App\Entity\ShiftSchedule;
use App\Entity\User;
use App\Entity\WorkOrder;
use App\Service\EncryptionService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EncryptionService $encryptionService,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // =========================================
        // 1. Create 6 users (one per role)
        // =========================================
        $admin = $this->createUser($manager, 'admin', 'admin@wfops.local', 'Admin@WFOps2024!', 'ROLE_ADMIN', 'System', 'Administrator', '+15551000001');
        $hrAdmin = $this->createUser($manager, 'hradmin', 'hradmin@wfops.local', 'HRAdmin@2024!', 'ROLE_HR_ADMIN', 'Helen', 'Roberts', '+15551000002');
        $supervisor = $this->createUser($manager, 'supervisor', 'supervisor@wfops.local', 'Super@2024!', 'ROLE_SUPERVISOR', 'Sarah', 'Mitchell', '+15551000003');
        $employee = $this->createUser($manager, 'employee', 'employee@wfops.local', 'Emp@2024!', 'ROLE_EMPLOYEE', 'John', 'Doe', '+15551000004');
        $dispatcher = $this->createUser($manager, 'dispatcher', 'dispatcher@wfops.local', 'Dispatch@2024!', 'ROLE_DISPATCHER', 'David', 'Chen', '+15551000005');
        $technician = $this->createUser($manager, 'technician', 'technician@wfops.local', 'Tech@2024!', 'ROLE_TECHNICIAN', 'Mike', 'Johnson', '+15551000006');

        // Set backup approvers
        $supervisor->setBackupApproverId($hrAdmin->getId());

        $manager->flush();

        // =========================================
        // 2. Create shift schedules (Mon-Fri 9AM-5PM for employee)
        // =========================================
        for ($day = 1; $day <= 5; $day++) {
            $schedule = new ShiftSchedule();
            $schedule->setUser($employee);
            $schedule->setDayOfWeek($day);
            $schedule->setShiftStart(new \DateTimeImmutable('09:00'));
            $schedule->setShiftEnd(new \DateTimeImmutable('17:00'));
            $schedule->setIsActive(true);
            $manager->persist($schedule);
        }

        // Supervisor also has a schedule
        for ($day = 1; $day <= 5; $day++) {
            $schedule = new ShiftSchedule();
            $schedule->setUser($supervisor);
            $schedule->setDayOfWeek($day);
            $schedule->setShiftStart(new \DateTimeImmutable('08:30'));
            $schedule->setShiftEnd(new \DateTimeImmutable('17:30'));
            $schedule->setIsActive(true);
            $manager->persist($schedule);
        }

        // =========================================
        // 3. Create exception rules
        // =========================================
        $rules = [
            ['LATE_ARRIVAL', 5, 30, 7],
            ['EARLY_LEAVE', 5, 30, 7],
            ['MISSED_PUNCH', 5, 30, 7],
            ['ABSENCE', 5, 30, 7],
        ];
        foreach ($rules as [$type, $tolerance, $window, $filing]) {
            $rule = new ExceptionRule();
            $rule->setRuleType($type);
            $rule->setToleranceMinutes($tolerance);
            $rule->setMissedPunchWindowMinutes($window);
            $rule->setFilingWindowDays($filing);
            $rule->setIsActive(true);
            $manager->persist($rule);
        }

        // =========================================
        // 4. Create sample punch events for today
        // =========================================
        $today = new \DateTimeImmutable('today');

        // Employee punched in late at 9:12 AM
        $punchIn = new PunchEvent();
        $punchIn->setUser($employee);
        $punchIn->setEventDate($today);
        $punchIn->setEventTime(new \DateTimeImmutable('09:12'));
        $punchIn->setEventType('IN');
        $punchIn->setSource('CSV');
        $manager->persist($punchIn);

        // Employee punched out at 5:05 PM
        $punchOut = new PunchEvent();
        $punchOut->setUser($employee);
        $punchOut->setEventDate($today);
        $punchOut->setEventTime(new \DateTimeImmutable('17:05'));
        $punchOut->setEventType('OUT');
        $punchOut->setSource('CSV');
        $manager->persist($punchOut);

        // =========================================
        // 5. Create attendance record for today
        // =========================================
        $attendance = new AttendanceRecord();
        $attendance->setUser($employee);
        $attendance->setRecordDate($today);
        $attendance->setFirstPunchIn(new \DateTimeImmutable('09:12'));
        $attendance->setLastPunchOut(new \DateTimeImmutable('17:05'));
        $attendance->setTotalMinutes(473);
        $attendance->setExceptions(['LATE_ARRIVAL']);
        $manager->persist($attendance);

        // =========================================
        // 6. Create sample bookable resources
        // =========================================
        $confRoom = new Resource();
        $confRoom->setName('Conference Room A');
        $confRoom->setType('meeting_room');
        $confRoom->setCostCenter('ADMIN-001');
        $confRoom->setCapacity(12);
        $confRoom->setIsAvailable(true);
        $confRoom->setDescription('Main conference room with projector and whiteboard');
        $manager->persist($confRoom);

        $vehicle = new Resource();
        $vehicle->setName('Company Van #1');
        $vehicle->setType('vehicle');
        $vehicle->setCostCenter('TRANSPORT-001');
        $vehicle->setCapacity(7);
        $vehicle->setIsAvailable(true);
        $vehicle->setDescription('7-seat passenger van for business trips');
        $manager->persist($vehicle);

        $laptop = new Resource();
        $laptop->setName('Portable Projector');
        $laptop->setType('equipment');
        $laptop->setCostCenter('IT-001');
        $laptop->setCapacity(1);
        $laptop->setIsAvailable(true);
        $laptop->setDescription('Portable projector for offsite presentations');
        $manager->persist($laptop);

        // =========================================
        // 7. Create work orders in various states
        // =========================================

        // Submitted
        $wo1 = new WorkOrder();
        $wo1->setSubmittedBy($employee);
        $wo1->setCategory('Plumbing');
        $wo1->setPriority('HIGH');
        $wo1->setDescription('Leaking faucet in restroom on 2nd floor. Water pooling on the floor.');
        $wo1->setBuilding('Main Building');
        $wo1->setRoom('Restroom 2F-B');
        $wo1->setStatus('submitted');
        $manager->persist($wo1);

        // Dispatched
        $wo2 = new WorkOrder();
        $wo2->setSubmittedBy($employee);
        $wo2->setCategory('Electrical');
        $wo2->setPriority('MEDIUM');
        $wo2->setDescription('Flickering lights in office 305. Multiple bulbs affected.');
        $wo2->setBuilding('Office Tower');
        $wo2->setRoom('305');
        $wo2->setStatus('dispatched');
        $wo2->setAssignedDispatcher($dispatcher);
        $wo2->setAssignedTechnician($technician);
        $wo2->setDispatchedAt(new \DateTimeImmutable('-1 day'));
        $manager->persist($wo2);

        // In progress
        $wo3 = new WorkOrder();
        $wo3->setSubmittedBy($supervisor);
        $wo3->setCategory('HVAC');
        $wo3->setPriority('URGENT');
        $wo3->setDescription('AC not working in server room. Temperature rising above safe threshold.');
        $wo3->setBuilding('Data Center');
        $wo3->setRoom('Server Room A');
        $wo3->setStatus('in_progress');
        $wo3->setAssignedDispatcher($dispatcher);
        $wo3->setAssignedTechnician($technician);
        $wo3->setDispatchedAt(new \DateTimeImmutable('-2 days'));
        $wo3->setAcceptedAt(new \DateTimeImmutable('-2 days'));
        $wo3->setStartedAt(new \DateTimeImmutable('-1 day'));
        $manager->persist($wo3);

        // Completed (ratable)
        $wo4 = new WorkOrder();
        $wo4->setSubmittedBy($employee);
        $wo4->setCategory('General');
        $wo4->setPriority('LOW');
        $wo4->setDescription('Replace broken door handle on supply closet. Handle fell off completely.');
        $wo4->setBuilding('Main Building');
        $wo4->setRoom('Supply Closet 1F');
        $wo4->setStatus('completed');
        $wo4->setAssignedDispatcher($dispatcher);
        $wo4->setAssignedTechnician($technician);
        $wo4->setDispatchedAt(new \DateTimeImmutable('-5 days'));
        $wo4->setAcceptedAt(new \DateTimeImmutable('-5 days'));
        $wo4->setStartedAt(new \DateTimeImmutable('-4 days'));
        $wo4->setCompletedAt(new \DateTimeImmutable('-1 day'));
        $wo4->setCompletionNotes('Replaced door handle with new hardware. Tested and working properly.');
        $manager->persist($wo4);

        $manager->flush();
    }

    private function createUser(
        ObjectManager $manager,
        string $username,
        string $email,
        string $plainPassword,
        string $role,
        string $firstName,
        string $lastName,
        string $phone,
    ): User {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setRole($role);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setPhoneEncrypted($this->encryptionService->encrypt($phone));
        $user->setIsActive(true);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPasswordHash($hashedPassword);

        $manager->persist($user);

        return $user;
    }
}
