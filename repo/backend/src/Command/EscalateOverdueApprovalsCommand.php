<?php

namespace App\Command;

use App\Repository\ApprovalStepRepository;
use App\Service\ApprovalWorkflowService;
use App\Service\SlaService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Escalates overdue approval steps to backup approvers.
 * Runs every 15 minutes via cron: * /15 * * * * php bin/console app:escalate-approvals
 */
#[AsCommand(
    name: 'app:escalate-approvals',
    description: 'Escalate overdue approval steps to backup approvers',
)]
class EscalateOverdueApprovalsCommand extends Command
{
    public function __construct(
        private readonly ApprovalStepRepository $approvalStepRepository,
        private readonly ApprovalWorkflowService $workflowService,
        private readonly SlaService $slaService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Checking for overdue approval steps');

        // Find pending steps that have not been escalated
        $pendingSteps = $this->approvalStepRepository->findBy([
            'status' => 'PENDING',
            'escalatedAt' => null,
        ]);

        $escalated = 0;

        foreach ($pendingSteps as $step) {
            // Check if past escalation threshold (SLA deadline + 2 business hours)
            if ($this->slaService->shouldEscalate($step)) {
                $this->workflowService->escalate($step);
                $escalated++;
                $io->text(sprintf(
                    '  Escalated step #%d (request #%d) to backup approver',
                    $step->getId(),
                    $step->getExceptionRequest()->getId(),
                ));
            }
        }

        if ($escalated > 0) {
            $io->success("Escalated $escalated overdue approval step(s)");
        } else {
            $io->info('No overdue steps found');
        }

        return Command::SUCCESS;
    }
}
