import { CheckCircle2, XCircle, Clock, AlertTriangle } from 'lucide-react';
import type { ApprovalStep } from '../../types';

function formatDeadline(deadline: string): string {
  return new Date(deadline).toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatRemaining(minutes: number): string {
  if (minutes <= 0) return 'Overdue';
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  if (h === 0) return `${m}m remaining`;
  return `${h}h ${m}m remaining`;
}

function slaColorClass(minutes: number): string {
  if (minutes <= 0) return 'text-red-400';
  if (minutes < 240) return 'text-red-400';
  if (minutes < 720) return 'text-amber-400';
  return 'text-green-400';
}

function StatusIcon({ step }: { step: ApprovalStep }) {
  if (step.status === 'APPROVED') {
    return <CheckCircle2 size={20} className="text-green-400 flex-shrink-0" />;
  }
  if (step.status === 'REJECTED') {
    return <XCircle size={20} className="text-red-400 flex-shrink-0" />;
  }
  return <Clock size={20} className="text-amber-400 flex-shrink-0" />;
}

function stepBorderClass(step: ApprovalStep, isCurrent: boolean): string {
  if (step.status === 'REJECTED') return 'border-red-500/60';
  if (step.remainingMinutes <= 0 && step.status === 'PENDING') return 'border-red-500/60';
  if (isCurrent) return 'border-accent shadow-[0_0_0_2px_rgba(99,102,241,0.3)]';
  return 'border-surface-border';
}

interface ApprovalTimelineProps {
  steps: ApprovalStep[];
}

export default function ApprovalTimeline({ steps }: ApprovalTimelineProps) {
  if (!steps || steps.length === 0) {
    return (
      <div className="text-sm text-gray-500 py-4 text-center">No approval steps.</div>
    );
  }

  // Current step = first pending step
  const currentStepId = steps.find((s) => s.status === 'PENDING')?.id ?? null;

  return (
    <div className="relative">
      {/* Vertical connecting line */}
      {steps.length > 1 && (
        <div className="absolute left-[19px] top-6 bottom-6 w-px bg-surface-border" />
      )}

      <div className="space-y-3">
        {steps.map((step) => {
          const isCurrent = step.id === currentStepId;
          const isOverdue = step.status === 'PENDING' && step.remainingMinutes <= 0;

          return (
            <div
              key={step.id}
              className={`relative flex gap-4 bg-surface-card border rounded-xl p-4 transition-all ${stepBorderClass(step, isCurrent)}`}
            >
              {/* Step number bubble */}
              <div className="flex-shrink-0 w-10 h-10 rounded-full bg-surface border border-surface-border flex items-center justify-center text-sm font-semibold text-gray-300 z-10">
                {step.stepNumber}
              </div>

              {/* Content */}
              <div className="flex-1 min-w-0">
                <div className="flex items-start justify-between gap-2 flex-wrap">
                  <div>
                    <p className="text-sm font-semibold text-white">{step.approverName}</p>
                    <p className="text-xs text-gray-400">{step.approverRole}</p>
                  </div>

                  <div className="flex items-center gap-2 flex-shrink-0">
                    {isOverdue && (
                      <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-500/20 border border-red-500/30 text-red-400 text-xs font-semibold">
                        <AlertTriangle size={10} />
                        OVERDUE
                      </span>
                    )}
                    {isCurrent && !isOverdue && (
                      <span className="inline-flex items-center px-2 py-0.5 rounded-full bg-accent/20 border border-accent/30 text-accent-light text-xs font-semibold">
                        Current
                      </span>
                    )}
                    <StatusIcon step={step} />
                  </div>
                </div>

                {/* SLA info */}
                <div className="mt-2 flex items-center gap-3 flex-wrap text-xs">
                  <span className="text-gray-500">
                    Deadline:{' '}
                    <span className="text-gray-300">{formatDeadline(step.slaDeadline)}</span>
                  </span>
                  {step.status === 'PENDING' && (
                    <span className={`font-medium ${slaColorClass(step.remainingMinutes)}`}>
                      {formatRemaining(step.remainingMinutes)}
                    </span>
                  )}
                  {step.actedAt && (
                    <span className="text-gray-500">
                      Acted:{' '}
                      <span className="text-gray-300">
                        {new Date(step.actedAt).toLocaleString('en-US', {
                          month: 'short',
                          day: 'numeric',
                          hour: '2-digit',
                          minute: '2-digit',
                        })}
                      </span>
                    </span>
                  )}
                </div>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
