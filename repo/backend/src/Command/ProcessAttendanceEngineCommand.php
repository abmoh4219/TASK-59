<?php

namespace App\Command;

use App\Service\AttendanceEngineService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Runs the attendance engine for a given date.
 * Defaults to yesterday if no date specified.
 *
 * Schedule: runs nightly at 2:00 AM via cron or Symfony Scheduler.
 * Cron entry: 0 2 * * * cd /app/backend && php bin/console app:process-attendance
 */
#[AsCommand(
    name: 'app:process-attendance',
    description: 'Process attendance records and detect exceptions for a given date',
)]
class ProcessAttendanceEngineCommand extends Command
{
    public function __construct(
        private readonly AttendanceEngineService $attendanceEngine,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'date',
            'd',
            InputOption::VALUE_OPTIONAL,
            'Date to process (YYYY-MM-DD format, defaults to yesterday)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dateStr = $input->getOption('date');

        if ($dateStr !== null) {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d', $dateStr);
            if ($date === false) {
                $io->error("Invalid date format: $dateStr (expected YYYY-MM-DD)");
                return Command::FAILURE;
            }
        } else {
            $date = new \DateTimeImmutable('yesterday');
        }

        $date = $date->setTime(0, 0, 0);
        $io->title('Processing Attendance for ' . $date->format('Y-m-d'));

        $summary = $this->attendanceEngine->processDate($date);

        $io->table(
            ['Metric', 'Count'],
            [
                ['Users processed', $summary['processed']],
                ['Exceptions found', $summary['exceptions_found']],
                ['Records created', $summary['records_created']],
                ['Records updated', $summary['records_updated']],
            ]
        );

        $io->success('Attendance processing complete for ' . $date->format('Y-m-d'));

        return Command::SUCCESS;
    }
}
