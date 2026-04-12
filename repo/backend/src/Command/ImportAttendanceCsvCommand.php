<?php

namespace App\Command;

use App\Entity\PunchEvent;
use App\Entity\User;
use App\Service\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Reader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-attendance',
    description: 'Import attendance punch events from a CSV file',
)]
class ImportAttendanceCsvCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditService $auditService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'Path to CSV file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = $input->getArgument('file');

        if (!file_exists($filePath)) {
            $io->error("File not found: $filePath");
            return Command::FAILURE;
        }

        $io->title('Importing Attendance CSV');
        $io->text("File: $filePath");

        $csv = Reader::createFromPath($filePath, 'r');
        $csv->setHeaderOffset(0);
        $records = $csv->getRecords();

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $userRepo = $this->entityManager->getRepository(User::class);
        $punchRepo = $this->entityManager->getRepository(PunchEvent::class);

        foreach ($records as $offset => $record) {
            $rowNum = $offset + 2; // header is row 1

            // Validate required columns
            $employeeId = $record['employee_id'] ?? null;
            $dateStr = $record['date'] ?? null;
            $eventType = strtoupper(trim($record['event_type'] ?? ''));
            $timeStr = $record['time'] ?? null;

            if (!$employeeId || !$dateStr || !$eventType || !$timeStr) {
                $errors[] = "Row $rowNum: missing required fields";
                continue;
            }

            // Validate event type
            if (!in_array($eventType, ['IN', 'OUT'], true)) {
                $errors[] = "Row $rowNum: invalid event_type '$eventType' (must be IN or OUT)";
                continue;
            }

            // Parse date (MM/DD/YYYY)
            $date = \DateTimeImmutable::createFromFormat('m/d/Y', $dateStr);
            if ($date === false) {
                $errors[] = "Row $rowNum: invalid date '$dateStr' (expected MM/DD/YYYY)";
                continue;
            }
            $date = $date->setTime(0, 0, 0);

            // Parse time (HH:MM:SS or HH:MM)
            $time = \DateTimeImmutable::createFromFormat('H:i:s', $timeStr);
            if ($time === false) {
                $time = \DateTimeImmutable::createFromFormat('H:i', $timeStr);
            }
            if ($time === false) {
                $errors[] = "Row $rowNum: invalid time '$timeStr' (expected HH:MM:SS or HH:MM)";
                continue;
            }

            // Find user
            $user = $userRepo->find((int) $employeeId);
            if ($user === null) {
                $errors[] = "Row $rowNum: user ID $employeeId not found";
                continue;
            }

            // Check for duplicate (skip if already exists)
            $existing = $punchRepo->findOneBy([
                'user' => $user,
                'eventDate' => $date,
                'eventTime' => $time,
                'eventType' => $eventType,
            ]);

            if ($existing !== null) {
                $skipped++;
                continue;
            }

            // Create punch event
            $punch = new PunchEvent();
            $punch->setUser($user);
            $punch->setEventDate($date);
            $punch->setEventTime($time);
            $punch->setEventType($eventType);
            $punch->setSource('CSV');
            $this->entityManager->persist($punch);
            $imported++;

            // Flush every 100 records for memory efficiency
            if ($imported % 100 === 0) {
                $this->entityManager->flush();
            }
        }

        $this->entityManager->flush();

        // Write audit log for the import
        $this->auditService->log(
            null,
            'CSV_IMPORT',
            'PunchEvent',
            null,
            null,
            ['file' => basename($filePath), 'imported' => $imported, 'skipped' => $skipped, 'errors' => count($errors)],
        );

        // Display results
        $io->success("Import complete: $imported imported, $skipped skipped, " . count($errors) . " errors");

        if (!empty($errors)) {
            $io->warning('Errors encountered:');
            foreach (array_slice($errors, 0, 20) as $err) {
                $io->text("  - $err");
            }
            if (count($errors) > 20) {
                $io->text('  ... and ' . (count($errors) - 20) . ' more errors');
            }
        }

        return Command::SUCCESS;
    }
}
