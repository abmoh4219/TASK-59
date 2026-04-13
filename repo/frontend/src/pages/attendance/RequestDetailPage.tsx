import { useState } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  ArrowLeft,
  AlertCircle,
  XCircle,
  Loader2,
  CalendarDays,
  Clock,
  FileText,
} from 'lucide-react';
import { getRequestDetail, withdrawRequest, reassignRequestApprover } from '../../api/attendance';
import ApprovalTimeline from '../../components/attendance/ApprovalTimeline';
import type { RequestStatus, RequestType } from '../../types';

// ---- Badge helpers ----

const STATUS_BADGE: Record<RequestStatus, { label: string; className: string }> = {
  PENDING: {
    label: 'Pending',
    className: 'bg-amber-500/20 text-amber-400 border-amber-500/30',
  },
  APPROVED: {
    label: 'Approved',
    className: 'bg-green-500/20 text-green-400 border-green-500/30',
  },
  REJECTED: {
    label: 'Rejected',
    className: 'bg-red-500/20 text-red-400 border-red-500/30',
  },
  WITHDRAWN: {
    label: 'Withdrawn',
    className: 'bg-gray-500/20 text-gray-400 border-gray-500/30',
  },
};

const TYPE_BADGE: Record<RequestType, { label: string; className: string }> = {
  CORRECTION: {
    label: 'Correction',
    className: 'bg-blue-500/20 text-blue-400 border-blue-500/30',
  },
  PTO: {
    label: 'PTO',
    className: 'bg-purple-500/20 text-purple-400 border-purple-500/30',
  },
  LEAVE: {
    label: 'Leave',
    className: 'bg-teal-500/20 text-teal-400 border-teal-500/30',
  },
  BUSINESS_TRIP: {
    label: 'Business Trip',
    className: 'bg-indigo-500/20 text-indigo-400 border-indigo-500/30',
  },
  OUTING: {
    label: 'Outing',
    className: 'bg-orange-500/20 text-orange-400 border-orange-500/30',
  },
};

function Badge({ className, label }: { className: string; label: string }) {
  return (
    <span
      className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border ${className}`}
    >
      {label}
    </span>
  );
}

// ---- Skeleton ----

function Skeleton({ className }: { className?: string }) {
  return (
    <div className={`bg-surface-hover rounded-lg shimmer ${className ?? ''}`} />
  );
}

function DetailSkeleton() {
  return (
    <div className="space-y-6 animate-pulse">
      <Skeleton className="h-8 w-48" />
      <div className="bg-surface-card border border-surface-border rounded-xl p-6 space-y-4">
        <Skeleton className="h-5 w-32" />
        <Skeleton className="h-4 w-full" />
        <Skeleton className="h-4 w-3/4" />
      </div>
      <div className="bg-surface-card border border-surface-border rounded-xl p-6 space-y-3">
        <Skeleton className="h-5 w-40" />
        {[1, 2].map((i) => (
          <Skeleton key={i} className="h-16 w-full" />
        ))}
      </div>
    </div>
  );
}

// ---- Withdraw modal ----

function WithdrawConfirm({
  onConfirm,
  onCancel,
  isPending,
}: {
  onConfirm: () => void;
  onCancel: () => void;
  isPending: boolean;
}) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
      <div className="bg-surface-card border border-surface-border rounded-xl p-6 max-w-sm w-full shadow-glow-lg space-y-4">
        <div className="flex items-center gap-3">
          <XCircle size={24} className="text-red-400 flex-shrink-0" />
          <div>
            <h3 className="text-base font-semibold text-white">Withdraw Request</h3>
            <p className="text-sm text-gray-400">This action cannot be undone.</p>
          </div>
        </div>
        <p className="text-sm text-gray-300">
          Are you sure you want to withdraw this exception request? It will be cancelled and
          removed from the approval queue.
        </p>
        <div className="flex gap-3 justify-end">
          <button
            onClick={onCancel}
            disabled={isPending}
            className="px-4 py-2 text-sm text-gray-400 hover:text-white transition-colors"
          >
            Cancel
          </button>
          <button
            onClick={onConfirm}
            disabled={isPending}
            className="inline-flex items-center gap-2 px-4 py-2 bg-red-500 hover:bg-red-600 disabled:opacity-60 text-white text-sm font-medium rounded-lg transition-colors"
          >
            {isPending && (
              <Loader2 size={14} className="animate-spin" />
            )}
            Withdraw
          </button>
        </div>
      </div>
    </div>
  );
}

// ---- Reassign modal ----

function ReassignModal({
  requestId,
  onClose,
  onDone,
}: {
  requestId: number;
  onClose: () => void;
  onDone: () => void;
}) {
  const [newApproverId, setNewApproverId] = useState('');
  const [reason, setReason] = useState('');
  const [error, setError] = useState<string | null>(null);

  const mutation = useMutation({
    mutationFn: () => {
      const id = parseInt(newApproverId, 10);
      if (Number.isNaN(id) || id <= 0) {
        return Promise.reject(new Error('Enter a valid approver user ID'));
      }
      return reassignRequestApprover(requestId, id, reason);
    },
    onSuccess: () => onDone(),
    onError: (err) =>
      setError(
        (err as { response?: { data?: { error?: string } } })?.response?.data?.error ??
          (err as Error)?.message ??
          'Reassign failed.',
      ),
  });

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
      <div className="bg-surface-card border border-surface-border rounded-xl p-6 max-w-sm w-full shadow-glow-lg space-y-4">
        <div>
          <h3 className="text-base font-semibold text-white">Reassign Approver</h3>
          <p className="text-sm text-gray-400">
            The current approver is out of office. Assign this step to a different approver.
          </p>
        </div>
        <input
          type="number"
          min={1}
          value={newApproverId}
          onChange={(e) => setNewApproverId(e.target.value)}
          placeholder="New approver user ID"
          className="w-full bg-surface border border-surface-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 placeholder-gray-500"
        />
        <textarea
          value={reason}
          onChange={(e) => setReason(e.target.value)}
          rows={3}
          placeholder="Reason (optional)"
          className="w-full bg-surface border border-surface-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 placeholder-gray-500 resize-none"
        />
        {error && (
          <div className="flex items-center gap-2">
            <AlertCircle size={14} className="text-red-400 flex-shrink-0" />
            <p className="text-xs text-red-300">{error}</p>
          </div>
        )}
        <div className="flex gap-3 justify-end">
          <button
            onClick={onClose}
            disabled={mutation.isPending}
            className="px-4 py-2 text-sm text-gray-400 hover:text-white transition-colors"
          >
            Cancel
          </button>
          <button
            onClick={() => mutation.mutate()}
            disabled={mutation.isPending}
            className="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 disabled:opacity-60 text-white text-sm font-medium rounded-lg transition-colors"
          >
            {mutation.isPending && <Loader2 size={14} className="animate-spin" />}
            Reassign
          </button>
        </div>
      </div>
    </div>
  );
}

// ---- Main page ----

export default function RequestDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [showWithdrawConfirm, setShowWithdrawConfirm] = useState(false);
  const [showReassign, setShowReassign] = useState(false);

  const requestId = Number(id);

  const { data: request, isLoading, isError, error } = useQuery({
    queryKey: ['requests', requestId],
    queryFn: () => getRequestDetail(requestId),
    enabled: !!requestId && !Number.isNaN(requestId),
    refetchInterval: 60_000,
  });

  const withdrawMutation = useMutation({
    mutationFn: () => withdrawRequest(requestId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['requests', requestId] });
      queryClient.invalidateQueries({ queryKey: ['my-requests'] });
      setShowWithdrawConfirm(false);
      navigate('/attendance');
    },
  });

  if (isLoading) {
    return (
      <div>
        <div className="mb-6">
          <Link to="/attendance" className="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-white transition-colors">
            <ArrowLeft size={15} /> Back
          </Link>
        </div>
        <DetailSkeleton />
      </div>
    );
  }

  if (isError || !request) {
    return (
      <div>
        <div className="mb-6">
          <Link to="/attendance" className="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-white transition-colors">
            <ArrowLeft size={15} /> Back
          </Link>
        </div>
        <div className="flex flex-col items-center gap-3 py-16 text-center">
          <AlertCircle size={40} className="text-red-400" />
          <p className="text-white font-semibold">Failed to load request</p>
          <p className="text-sm text-gray-400">
            {(error as Error)?.message ?? 'An unexpected error occurred.'}
          </p>
          <button
            onClick={() => queryClient.invalidateQueries({ queryKey: ['requests', requestId] })}
            className="mt-2 px-4 py-2 bg-accent hover:bg-accent-hover text-white text-sm rounded-lg transition-colors"
          >
            Retry
          </button>
        </div>
      </div>
    );
  }

  const typeBadge = TYPE_BADGE[request.requestType] ?? {
    label: request.requestType,
    className: 'bg-gray-500/20 text-gray-400 border-gray-500/30',
  };
  const statusBadge = STATUS_BADGE[request.status] ?? {
    label: request.status,
    className: 'bg-gray-500/20 text-gray-400 border-gray-500/30',
  };

  const canWithdraw =
    request.status === 'PENDING' &&
    request.steps.length > 0 &&
    request.steps[0].actedAt === null;

  // Check if any approver in the current pending step is marked out.
  // approverIsOut is surfaced by the backend in ExceptionRequestController::serializeRequest.
  const pendingStep = request.steps.find((s) => s.status === 'PENDING');
  const approverIsOut = Boolean(pendingStep?.approverIsOut);

  const formatDate = (d: string) =>
    new Date(d + 'T00:00:00').toLocaleDateString('en-US', {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    });

  return (
    <div>
      {showWithdrawConfirm && (
        <WithdrawConfirm
          onConfirm={() => withdrawMutation.mutate()}
          onCancel={() => setShowWithdrawConfirm(false)}
          isPending={withdrawMutation.isPending}
        />
      )}

      {showReassign && pendingStep && (
        <ReassignModal
          requestId={requestId}
          onClose={() => setShowReassign(false)}
          onDone={() => {
            setShowReassign(false);
            queryClient.invalidateQueries({ queryKey: ['requests', requestId] });
          }}
        />
      )}

      {/* Back nav */}
      <div className="mb-6">
        <Link
          to="/attendance"
          className="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-white transition-colors"
        >
          <ArrowLeft size={15} /> Back to Attendance
        </Link>
      </div>

      {/* Header */}
      <div className="flex items-start justify-between gap-4 mb-6 flex-wrap">
        <div>
          <h1 className="text-2xl font-bold text-white mb-2">Exception Request #{request.id}</h1>
          <div className="flex items-center gap-2 flex-wrap">
            <Badge label={typeBadge.label} className={typeBadge.className} />
            <Badge label={statusBadge.label} className={statusBadge.className} />
          </div>
        </div>

        {/* Actions */}
        <div className="flex items-center gap-3">
          {canWithdraw && (
            <button
              onClick={() => setShowWithdrawConfirm(true)}
              className="inline-flex items-center gap-2 px-4 py-2 bg-red-500/10 hover:bg-red-500/20 border border-red-500/30 text-red-400 text-sm font-medium rounded-lg transition-colors"
            >
              <XCircle size={15} />
              Withdraw
            </button>
          )}
          {approverIsOut && pendingStep && (
            <button
              onClick={() => setShowReassign(true)}
              className="inline-flex items-center gap-2 px-4 py-2 bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/30 text-amber-400 text-sm font-medium rounded-lg transition-colors"
            >
              Reassign Approver
            </button>
          )}
        </div>
      </div>

      {/* Withdraw error */}
      {withdrawMutation.isError && (
        <div className="mb-4 flex items-center gap-2 p-3 rounded-lg bg-red-500/10 border border-red-500/20">
          <AlertCircle size={16} className="text-red-400 flex-shrink-0" />
          <p className="text-sm text-red-300">
            {(withdrawMutation.error as Error)?.message ?? 'Failed to withdraw request.'}
          </p>
        </div>
      )}

      {/* Detail card */}
      <div className="bg-surface-card border border-surface-border rounded-xl p-6 mb-6 space-y-5">
        <h2 className="text-base font-semibold text-white">Request Details</h2>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div className="flex items-start gap-3">
            <CalendarDays size={16} className="text-gray-500 mt-0.5 flex-shrink-0" />
            <div>
              <p className="text-xs text-gray-500 mb-0.5">Date Range</p>
              <p className="text-sm text-white">
                {formatDate(request.startDate)}
                {request.startDate !== request.endDate && (
                  <> &ndash; {formatDate(request.endDate)}</>
                )}
              </p>
            </div>
          </div>

          <div className="flex items-start gap-3">
            <Clock size={16} className="text-gray-500 mt-0.5 flex-shrink-0" />
            <div>
              <p className="text-xs text-gray-500 mb-0.5">Time Window</p>
              <p className="text-sm text-white">
                {request.startTime} &ndash; {request.endTime}
              </p>
            </div>
          </div>

          <div className="flex items-start gap-3">
            <CalendarDays size={16} className="text-gray-500 mt-0.5 flex-shrink-0" />
            <div>
              <p className="text-xs text-gray-500 mb-0.5">Filed At</p>
              <p className="text-sm text-white">
                {new Date(request.filedAt).toLocaleString('en-US', {
                  month: 'short',
                  day: 'numeric',
                  year: 'numeric',
                  hour: '2-digit',
                  minute: '2-digit',
                })}
              </p>
            </div>
          </div>
        </div>

        <div className="flex items-start gap-3 pt-1 border-t border-surface-border">
          <FileText size={16} className="text-gray-500 mt-0.5 flex-shrink-0" />
          <div>
            <p className="text-xs text-gray-500 mb-1">Reason</p>
            <p className="text-sm text-gray-200 leading-relaxed whitespace-pre-wrap">
              {request.reason}
            </p>
          </div>
        </div>
      </div>

      {/* Approval Timeline */}
      <div className="bg-surface-card border border-surface-border rounded-xl p-6">
        <h2 className="text-base font-semibold text-white mb-5">Approval Timeline</h2>
        <ApprovalTimeline steps={request.steps} />
      </div>
    </div>
  );
}
