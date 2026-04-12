import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  CheckCircle2,
  XCircle,
  Clock,
  AlertTriangle,
  Inbox,
  AlertCircle,
  Loader2,
  ChevronDown,
  ChevronUp,
} from 'lucide-react';
import { getApprovalQueue, approveStep, rejectStep } from '../../api/attendance';
import type { ExceptionRequest, RequestType, ApprovalStep } from '../../types';

// ---- Helpers ----

const TYPE_LABELS: Record<RequestType, string> = {
  CORRECTION: 'Correction',
  PTO: 'PTO',
  LEAVE: 'Leave',
  BUSINESS_TRIP: 'Business Trip',
  OUTING: 'Outing',
};

const TYPE_BADGE_CLASS: Record<RequestType, string> = {
  CORRECTION: 'bg-blue-500/20 text-blue-400 border-blue-500/30',
  PTO: 'bg-purple-500/20 text-purple-400 border-purple-500/30',
  LEAVE: 'bg-teal-500/20 text-teal-400 border-teal-500/30',
  BUSINESS_TRIP: 'bg-indigo-500/20 text-indigo-400 border-indigo-500/30',
  OUTING: 'bg-orange-500/20 text-orange-400 border-orange-500/30',
};

function slaColorClass(minutes: number): string {
  if (minutes <= 0) return 'text-red-400';
  if (minutes < 240) return 'text-red-400';
  if (minutes < 720) return 'text-amber-400';
  return 'text-green-400';
}

function formatRemaining(minutes: number): string {
  if (minutes <= 0) return 'Overdue';
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  if (h === 0) return `${m}m`;
  return `${h}h ${m}m`;
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

// ---- Sub-components ----

function Skeleton({ className }: { className?: string }) {
  return <div className={`bg-surface-hover rounded-lg shimmer ${className ?? ''}`} />;
}

function TableSkeleton() {
  return (
    <div className="space-y-3 animate-pulse">
      {[1, 2, 3, 4].map((i) => (
        <Skeleton key={i} className="h-16 w-full" />
      ))}
    </div>
  );
}

// ---- Inline action panel ----

type ActionType = 'approve' | 'reject';

interface ActionPanelProps {
  step: ApprovalStep;
  action: ActionType;
  onClose: () => void;
  onDone: () => void;
}

function ActionPanel({ step, action, onClose, onDone }: ActionPanelProps) {
  const [comment, setComment] = useState('');
  const [error, setError] = useState<string | null>(null);
  const queryClient = useQueryClient();

  const mutation = useMutation({
    mutationFn: () =>
      action === 'approve' ? approveStep(step.id, comment) : rejectStep(step.id, comment),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['approval-queue'] });
      onDone();
    },
    onError: (err) => {
      setError((err as Error)?.message ?? 'Action failed. Please try again.');
    },
  });

  const isApprove = action === 'approve';

  return (
    <div
      className={`mt-3 p-4 rounded-lg border space-y-3 ${
        isApprove
          ? 'bg-green-500/5 border-green-500/20'
          : 'bg-red-500/5 border-red-500/20'
      }`}
    >
      <p className="text-sm font-medium text-white">
        {isApprove ? 'Confirm Approval' : 'Confirm Rejection'}
      </p>
      <textarea
        value={comment}
        onChange={(e) => setComment(e.target.value)}
        rows={3}
        placeholder={isApprove ? 'Optional comment...' : 'Reason for rejection (recommended)...'}
        className="w-full bg-surface border border-surface-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent placeholder-gray-500 resize-none transition-colors"
      />
      {error && (
        <div className="flex items-center gap-2">
          <AlertCircle size={14} className="text-red-400 flex-shrink-0" />
          <p className="text-xs text-red-300">{error}</p>
        </div>
      )}
      <div className="flex items-center gap-2 justify-end">
        <button
          onClick={onClose}
          disabled={mutation.isPending}
          className="px-3 py-1.5 text-xs text-gray-400 hover:text-white transition-colors"
        >
          Cancel
        </button>
        <button
          onClick={() => mutation.mutate()}
          disabled={mutation.isPending}
          className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors disabled:opacity-60 ${
            isApprove
              ? 'bg-green-600 hover:bg-green-700 text-white'
              : 'bg-red-600 hover:bg-red-700 text-white'
          }`}
        >
          {mutation.isPending && <Loader2 size={12} className="animate-spin" />}
          {isApprove ? (
            <>
              <CheckCircle2 size={12} /> Approve
            </>
          ) : (
            <>
              <XCircle size={12} /> Reject
            </>
          )}
        </button>
      </div>
    </div>
  );
}

// ---- Queue row ----

interface QueueRowProps {
  request: ExceptionRequest;
}

function QueueRow({ request }: QueueRowProps) {
  const [activeAction, setActiveAction] = useState<ActionType | null>(null);
  const [expanded, setExpanded] = useState(false);

  // Pending step for this approver (first pending step)
  const pendingStep = request.steps.find((s) => s.status === 'PENDING');
  if (!pendingStep) return null;

  const isOverdue = pendingStep.remainingMinutes <= 0;
  const typeBadgeClass =
    TYPE_BADGE_CLASS[request.requestType] ?? 'bg-gray-500/20 text-gray-400 border-gray-500/30';
  const typeLabel = TYPE_LABELS[request.requestType] ?? request.requestType;

  function handleActionDone() {
    setActiveAction(null);
    setExpanded(false);
  }

  return (
    <div className="bg-surface-card border border-surface-border rounded-xl overflow-hidden">
      {/* Main row */}
      <div className="flex items-center gap-4 px-4 py-3 flex-wrap">
        {/* Employee */}
        <div className="flex-1 min-w-[120px]">
          <p className="text-sm font-medium text-white">{pendingStep.approverName}</p>
          <p className="text-xs text-gray-500">{pendingStep.approverRole}</p>
        </div>

        {/* Type badge */}
        <span
          className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border flex-shrink-0 ${typeBadgeClass}`}
        >
          {typeLabel}
        </span>

        {/* Exception date */}
        <div className="text-xs text-gray-400 flex-shrink-0 hidden sm:block">
          <p className="text-gray-500 text-xs">Date</p>
          <p className="text-white">{formatDate(request.startDate)}</p>
        </div>

        {/* Filed date */}
        <div className="text-xs text-gray-400 flex-shrink-0 hidden sm:block">
          <p className="text-gray-500 text-xs">Filed</p>
          <p className="text-white">{formatDate(request.filedAt)}</p>
        </div>

        {/* SLA */}
        <div className="flex-shrink-0 flex items-center gap-2">
          {isOverdue ? (
            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-500/20 border border-red-500/30 text-red-400 text-xs font-semibold">
              <AlertTriangle size={10} />
              OVERDUE
            </span>
          ) : (
            <span
              className={`inline-flex items-center gap-1 text-xs font-medium ${slaColorClass(pendingStep.remainingMinutes)}`}
            >
              <Clock size={12} />
              {formatRemaining(pendingStep.remainingMinutes)}
            </span>
          )}
        </div>

        {/* Action buttons */}
        <div className="flex items-center gap-2 flex-shrink-0 ml-auto">
          <button
            onClick={() => {
              setExpanded(true);
              setActiveAction(activeAction === 'approve' ? null : 'approve');
            }}
            className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-600/10 hover:bg-green-600/20 border border-green-600/30 text-green-400 text-xs font-medium rounded-lg transition-colors"
          >
            <CheckCircle2 size={13} />
            Approve
          </button>
          <button
            onClick={() => {
              setExpanded(true);
              setActiveAction(activeAction === 'reject' ? null : 'reject');
            }}
            className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-600/10 hover:bg-red-600/20 border border-red-600/30 text-red-400 text-xs font-medium rounded-lg transition-colors"
          >
            <XCircle size={13} />
            Reject
          </button>
          <button
            onClick={() => setExpanded((v) => !v)}
            className="p-1.5 text-gray-500 hover:text-gray-300 transition-colors"
            aria-label="Toggle details"
          >
            {expanded ? <ChevronUp size={15} /> : <ChevronDown size={15} />}
          </button>
        </div>
      </div>

      {/* Expandable: inline action or extra detail */}
      {expanded && (
        <div className="px-4 pb-4 border-t border-surface-border pt-3 space-y-3">
          {/* Extra info on small screens */}
          <div className="flex gap-6 sm:hidden text-xs">
            <div>
              <p className="text-gray-500">Exception Date</p>
              <p className="text-white">{formatDate(request.startDate)}</p>
            </div>
            <div>
              <p className="text-gray-500">Filed</p>
              <p className="text-white">{formatDate(request.filedAt)}</p>
            </div>
          </div>

          {/* Reason preview */}
          <div>
            <p className="text-xs text-gray-500 mb-1">Reason</p>
            <p className="text-sm text-gray-300 leading-relaxed line-clamp-3">
              {request.reason}
            </p>
          </div>

          {/* Inline action panel */}
          {activeAction && (
            <ActionPanel
              step={pendingStep}
              action={activeAction}
              onClose={() => setActiveAction(null)}
              onDone={handleActionDone}
            />
          )}
        </div>
      )}
    </div>
  );
}

// ---- Main Page ----

export default function ApprovalQueuePage() {
  const queryClient = useQueryClient();

  const { data: queue, isLoading, isError, error } = useQuery({
    queryKey: ['approval-queue'],
    queryFn: getApprovalQueue,
    refetchInterval: 60_000,
  });

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-white">Approval Queue</h1>
          <p className="text-sm text-gray-400 mt-1">
            Requests pending your action
          </p>
        </div>
        <button
          onClick={() => queryClient.invalidateQueries({ queryKey: ['approval-queue'] })}
          className="inline-flex items-center gap-2 px-3 py-2 bg-surface-card border border-surface-border hover:border-accent/50 text-gray-400 hover:text-white text-sm rounded-lg transition-colors"
        >
          Refresh
        </button>
      </div>

      {isLoading && <TableSkeleton />}

      {isError && (
        <div className="flex flex-col items-center gap-3 py-16 text-center">
          <AlertCircle size={40} className="text-red-400" />
          <p className="text-white font-semibold">Failed to load approval queue</p>
          <p className="text-sm text-gray-400">
            {(error as Error)?.message ?? 'An unexpected error occurred.'}
          </p>
          <button
            onClick={() => queryClient.invalidateQueries({ queryKey: ['approval-queue'] })}
            className="mt-2 px-4 py-2 bg-accent hover:bg-accent-hover text-white text-sm rounded-lg transition-colors"
          >
            Retry
          </button>
        </div>
      )}

      {!isLoading && !isError && queue && queue.length === 0 && (
        <div className="flex flex-col items-center gap-4 py-20 text-center">
          <div className="w-16 h-16 rounded-full bg-surface-card border border-surface-border flex items-center justify-center">
            <Inbox size={28} className="text-gray-500" />
          </div>
          <p className="text-white font-semibold text-lg">No pending approvals</p>
          <p className="text-sm text-gray-400 max-w-xs">
            You are all caught up. Pending requests from your team will appear here.
          </p>
        </div>
      )}

      {!isLoading && !isError && queue && queue.length > 0 && (
        <div className="space-y-3">
          {/* Column headers (visible on sm+) */}
          <div className="hidden sm:flex items-center gap-4 px-4 py-2 text-xs text-gray-500 uppercase tracking-wider">
            <div className="flex-1">Approver / Role</div>
            <div className="w-28">Type</div>
            <div className="w-28">Exception Date</div>
            <div className="w-28">Filed</div>
            <div className="w-24">SLA</div>
            <div className="ml-auto w-40 text-right">Actions</div>
          </div>

          {queue.map((req) => (
            <QueueRow key={req.id} request={req} />
          ))}
        </div>
      )}
    </div>
  );
}
