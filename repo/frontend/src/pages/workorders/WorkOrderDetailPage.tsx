import { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  ArrowLeft,
  AlertCircle,
  RefreshCw,
  Building2,
  DoorOpen,
  User,
  Calendar,
  FileText,
  ImageIcon,
  Loader2,
  CheckCircle2,
  Play,
  Truck,
  Star,
  ChevronDown,
  Clock,
  MapPin,
  Tag,
  Wrench,
} from 'lucide-react';
import { getWorkOrder, updateWorkOrderStatus, rateWorkOrder } from '../../api/workOrders';
import { useAuth } from '../../context/AuthContext';
import type { WorkOrder, WorkOrderStatus, WorkOrderPriority } from '../../types';

// ── Badge maps ────────────────────────────────────────────────────────────────

const STATUS_BADGE: Record<WorkOrderStatus, { label: string; className: string }> = {
  submitted:   { label: 'Submitted',   className: 'bg-gray-500/20   text-gray-300   border-gray-500/30'   },
  dispatched:  { label: 'Dispatched',  className: 'bg-blue-500/20   text-blue-400   border-blue-500/30'   },
  accepted:    { label: 'Accepted',    className: 'bg-indigo-500/20 text-indigo-400 border-indigo-500/30' },
  in_progress: { label: 'In Progress', className: 'bg-amber-500/20  text-amber-400  border-amber-500/30'  },
  completed:   { label: 'Completed',   className: 'bg-green-500/20  text-green-400  border-green-500/30'  },
  rated:       { label: 'Rated',       className: 'bg-teal-500/20   text-teal-400   border-teal-500/30'   },
};

const PRIORITY_BADGE: Record<WorkOrderPriority, { label: string; className: string; dot: string }> = {
  LOW:    { label: 'Low',    className: 'bg-green-500/15  text-green-400  border-green-500/30',  dot: 'bg-green-400'  },
  MEDIUM: { label: 'Medium', className: 'bg-amber-500/15  text-amber-400  border-amber-500/30',  dot: 'bg-amber-400'  },
  HIGH:   { label: 'High',   className: 'bg-orange-500/15 text-orange-400 border-orange-500/30', dot: 'bg-orange-400' },
  URGENT: { label: 'Urgent', className: 'bg-red-500/15    text-red-400    border-red-500/30',    dot: 'bg-red-400'    },
};

// State machine steps in order
const STATE_STEPS: WorkOrderStatus[] = [
  'submitted', 'dispatched', 'accepted', 'in_progress', 'completed', 'rated',
];

// ── Small helpers ─────────────────────────────────────────────────────────────

function Badge({ className, label, dot }: { className: string; label: string; dot?: string }) {
  return (
    <span className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border ${className}`}>
      {dot && <span className={`w-1.5 h-1.5 rounded-full ${dot}`} />}
      {label}
    </span>
  );
}

function DetailItem({
  icon: Icon,
  label,
  value,
}: {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  icon: React.ComponentType<any>;
  label: string;
  value: string | null | undefined;
}) {
  return (
    <div className="flex items-start gap-3">
      <Icon size={15} className="text-gray-500 mt-0.5 flex-shrink-0" />
      <div>
        <p className="text-xs text-gray-500 mb-0.5">{label}</p>
        <p className="text-sm text-white">{value || '—'}</p>
      </div>
    </div>
  );
}

function fmtDatetime(iso: string | null | undefined): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('en-US', {
    month: 'short', day: 'numeric', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
}

function fmtDate(iso: string): string {
  return new Date(iso).toLocaleDateString('en-US', {
    month: '2-digit', day: '2-digit', year: 'numeric',
  });
}

// ── Skeleton ──────────────────────────────────────────────────────────────────

function Skeleton({ className }: { className?: string }) {
  return <div className={`bg-surface-hover rounded-lg animate-pulse ${className ?? ''}`} />;
}

function DetailSkeleton() {
  return (
    <div className="space-y-5">
      <Skeleton className="h-8 w-64" />
      <div className="bg-surface-card border border-surface-border rounded-xl p-6 space-y-4">
        <Skeleton className="h-4 w-48" />
        <Skeleton className="h-4 w-full" />
        <Skeleton className="h-4 w-3/4" />
      </div>
      <div className="bg-surface-card border border-surface-border rounded-xl p-6 space-y-3">
        <Skeleton className="h-4 w-32" />
        <Skeleton className="h-10 w-full" />
      </div>
    </div>
  );
}

// ── State machine progress bar ────────────────────────────────────────────────

function StateMachineProgress({ status }: { status: WorkOrderStatus }) {
  const currentIdx = STATE_STEPS.indexOf(status);

  return (
    <div className="relative">
      {/* Connecting line */}
      <div className="absolute top-3.5 left-0 right-0 h-0.5 bg-surface-border" />
      <div
        className="absolute top-3.5 left-0 h-0.5 bg-gradient-to-r from-accent to-accent-light transition-all duration-500"
        style={{ width: `${(currentIdx / (STATE_STEPS.length - 1)) * 100}%` }}
      />

      <div className="relative flex justify-between">
        {STATE_STEPS.map((step, idx) => {
          const isDone = idx < currentIdx;
          const isCurrent = idx === currentIdx;
          const isFuture = idx > currentIdx;

          const label = step.replace('_', ' ');
          const label2 = label.charAt(0).toUpperCase() + label.slice(1);

          return (
            <div key={step} className="flex flex-col items-center gap-2">
              <div
                className={`w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold border-2 transition-all z-10 ${
                  isDone
                    ? 'bg-accent border-accent text-white'
                    : isCurrent
                    ? 'bg-accent/20 border-accent text-accent-light ring-4 ring-accent/20'
                    : 'bg-surface border-surface-border text-gray-600'
                }`}
              >
                {isDone ? (
                  <svg className="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                  </svg>
                ) : (
                  <span>{idx + 1}</span>
                )}
              </div>
              <span className={`text-[10px] font-medium text-center leading-tight ${
                isCurrent ? 'text-accent-light' : isDone ? 'text-gray-400' : 'text-gray-600'
              }`}>
                {label2}
              </span>
            </div>
          );
        })}
      </div>
    </div>
  );
}

// ── Timeline entry ────────────────────────────────────────────────────────────

function TimelineEntry({
  label,
  timestamp,
  done,
}: {
  label: string;
  timestamp: string | null | undefined;
  done: boolean;
}) {
  return (
    <div className="flex items-start gap-3">
      <div className={`mt-0.5 w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0 ${
        done ? 'bg-accent/20 border border-accent/40' : 'bg-surface border border-surface-border'
      }`}>
        {done && (
          <svg className="w-3 h-3 text-accent-light" fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
          </svg>
        )}
      </div>
      <div>
        <p className={`text-sm font-medium ${done ? 'text-white' : 'text-gray-600'}`}>{label}</p>
        {timestamp && (
          <p className="text-xs text-gray-500 mt-0.5">
            <Clock size={10} className="inline mr-1" />
            {fmtDatetime(timestamp)}
          </p>
        )}
      </div>
    </div>
  );
}

// ── Star rating ────────────────────────────────────────────────────────────────

function StarRating({
  value,
  onChange,
  disabled,
}: {
  value: number;
  onChange: (v: number) => void;
  disabled?: boolean;
}) {
  const [hovered, setHovered] = useState(0);

  return (
    <div className="flex items-center gap-1">
      {[1, 2, 3, 4, 5].map((star) => {
        const filled = star <= (hovered || value);
        return (
          <button
            key={star}
            type="button"
            disabled={disabled}
            onClick={() => onChange(star)}
            onMouseEnter={() => setHovered(star)}
            onMouseLeave={() => setHovered(0)}
            className={`transition-transform ${disabled ? 'cursor-not-allowed opacity-50' : 'hover:scale-125 cursor-pointer'}`}
          >
            <Star
              size={28}
              className={`transition-colors ${filled ? 'text-amber-400 fill-amber-400' : 'text-gray-600'}`}
            />
          </button>
        );
      })}
    </div>
  );
}

// ── Dispatcher action panel ────────────────────────────────────────────────────

function DispatcherPanel({
  workOrderId,
  onSuccess,
}: {
  workOrderId: number;
  onSuccess: () => void;
}) {
  // TODO: fetch technicians from API — currently hardcoded to fixture technician ID=6
  const TECHNICIANS = [{ id: 6, name: 'Technician (ID 6)' }];

  const [technicianId, setTechnicianId] = useState<number>(TECHNICIANS[0].id);
  const queryClient = useQueryClient();

  const mutation = useMutation({
    mutationFn: () => updateWorkOrderStatus(workOrderId, 'dispatched', undefined, technicianId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['work-order', workOrderId] });
      onSuccess();
    },
  });

  return (
    <div className="bg-surface-card border border-surface-border rounded-xl p-5 space-y-4">
      <div className="flex items-center gap-2">
        <Truck size={16} className="text-blue-400" />
        <h3 className="text-sm font-semibold text-white">Assign & Dispatch</h3>
      </div>

      <div>
        <label className="block text-xs text-gray-400 mb-1.5">Select Technician</label>
        <div className="relative">
          <select
            value={technicianId}
            onChange={(e) => setTechnicianId(Number(e.target.value))}
            className="w-full bg-surface border border-surface-border rounded-lg px-3 py-2 text-white text-sm appearance-none focus:outline-none focus:ring-2 focus:ring-accent"
          >
            {TECHNICIANS.map((t) => (
              <option key={t.id} value={t.id}>{t.name}</option>
            ))}
          </select>
          <ChevronDown size={14} className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
        </div>
      </div>

      {mutation.isError && (
        <div className="flex items-center gap-2 p-2 rounded-lg bg-red-500/10 border border-red-500/20">
          <AlertCircle size={13} className="text-red-400 flex-shrink-0" />
          <p className="text-xs text-red-300">
            {(mutation.error as Error)?.message ?? 'Failed to dispatch.'}
          </p>
        </div>
      )}

      <button
        onClick={() => mutation.mutate()}
        disabled={mutation.isPending}
        className="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white text-sm font-medium rounded-lg transition-colors"
      >
        {mutation.isPending ? (
          <><Loader2 size={14} className="animate-spin" /> Dispatching…</>
        ) : (
          <><Truck size={14} /> Dispatch to Technician</>
        )}
      </button>
    </div>
  );
}

// ── Technician action panel ────────────────────────────────────────────────────

function TechnicianPanel({
  workOrder,
  onSuccess,
}: {
  workOrder: WorkOrder;
  onSuccess: () => void;
}) {
  const [notes, setNotes] = useState('');
  const [showNotes, setShowNotes] = useState(false);
  const queryClient = useQueryClient();

  let nextStatus: WorkOrderStatus | null = null;
  let actionLabel = '';
  let ActionIcon = Play;
  let buttonClass = 'bg-indigo-600 hover:bg-indigo-700';

  if (workOrder.status === 'dispatched') {
    nextStatus = 'accepted';
    actionLabel = 'Accept Work Order';
    ActionIcon = CheckCircle2;
    buttonClass = 'bg-indigo-600 hover:bg-indigo-700';
  } else if (workOrder.status === 'accepted') {
    nextStatus = 'in_progress';
    actionLabel = 'Start Work';
    ActionIcon = Play;
    buttonClass = 'bg-amber-600 hover:bg-amber-700';
  } else if (workOrder.status === 'in_progress') {
    nextStatus = 'completed';
    actionLabel = 'Mark Complete';
    ActionIcon = CheckCircle2;
    buttonClass = 'bg-green-600 hover:bg-green-700';
    if (!showNotes) {
      // show the notes section for completed
    }
  }

  const mutation = useMutation({
    mutationFn: () =>
      updateWorkOrderStatus(workOrder.id, nextStatus!, notes.trim() || undefined),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['work-order', workOrder.id] });
      onSuccess();
    },
  });

  if (!nextStatus) return null;

  return (
    <div className="bg-surface-card border border-surface-border rounded-xl p-5 space-y-4">
      <div className="flex items-center gap-2">
        <Wrench size={16} className="text-amber-400" />
        <h3 className="text-sm font-semibold text-white">Technician Actions</h3>
      </div>

      {/* Notes field — always show for in_progress → completed */}
      {workOrder.status === 'in_progress' && (
        <div>
          <label className="block text-xs text-gray-400 mb-1.5">Completion Notes (optional)</label>
          <textarea
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
            rows={3}
            placeholder="Describe work performed, parts used, etc."
            className="w-full bg-surface border border-surface-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-accent resize-none placeholder-gray-600"
          />
        </div>
      )}

      {mutation.isError && (
        <div className="flex items-center gap-2 p-2 rounded-lg bg-red-500/10 border border-red-500/20">
          <AlertCircle size={13} className="text-red-400 flex-shrink-0" />
          <p className="text-xs text-red-300">
            {(mutation.error as Error)?.message ?? 'Action failed.'}
          </p>
        </div>
      )}

      <button
        onClick={() => mutation.mutate()}
        disabled={mutation.isPending}
        className={`w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 ${buttonClass} disabled:opacity-60 text-white text-sm font-medium rounded-lg transition-colors`}
      >
        {mutation.isPending ? (
          <><Loader2 size={14} className="animate-spin" /> Processing…</>
        ) : (
          <><ActionIcon size={14} /> {actionLabel}</>
        )}
      </button>
    </div>
  );
}

// ── Rating panel ──────────────────────────────────────────────────────────────

function RatingPanel({
  workOrder,
  onSuccess,
}: {
  workOrder: WorkOrder;
  onSuccess: () => void;
}) {
  const [starValue, setStarValue] = useState(0);
  const [submitted, setSubmitted] = useState(false);
  const queryClient = useQueryClient();

  const mutation = useMutation({
    mutationFn: () => rateWorkOrder(workOrder.id, starValue),
    onSuccess: () => {
      setSubmitted(true);
      queryClient.invalidateQueries({ queryKey: ['work-order', workOrder.id] });
      onSuccess();
    },
  });

  // 72-hour window check
  if (!workOrder.completedAt) return null;
  const completedMs = new Date(workOrder.completedAt).getTime();
  const windowExpired = Date.now() - completedMs > 72 * 60 * 60 * 1000;
  if (windowExpired) return null;

  if (submitted) {
    return (
      <div className="bg-surface-card border border-surface-border rounded-xl p-5 flex items-center gap-3">
        <Star size={20} className="text-amber-400 fill-amber-400" />
        <p className="text-sm text-white font-medium">Rating submitted. Thank you!</p>
      </div>
    );
  }

  return (
    <div className="bg-surface-card border border-surface-border rounded-xl p-5 space-y-4">
      <div className="flex items-center gap-2">
        <Star size={16} className="text-amber-400" />
        <h3 className="text-sm font-semibold text-white">Rate this Work Order</h3>
        <span className="ml-auto text-xs text-gray-500">Within 72h window</span>
      </div>

      <div className="flex flex-col items-center gap-3 py-2">
        <StarRating value={starValue} onChange={setStarValue} disabled={mutation.isPending} />
        {starValue > 0 && (
          <p className="text-xs text-amber-300">
            {['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'][starValue]}
          </p>
        )}
      </div>

      {mutation.isError && (
        <div className="flex items-center gap-2 p-2 rounded-lg bg-red-500/10 border border-red-500/20">
          <AlertCircle size={13} className="text-red-400 flex-shrink-0" />
          <p className="text-xs text-red-300">
            {(mutation.error as Error)?.message ?? 'Rating failed.'}
          </p>
        </div>
      )}

      <button
        onClick={() => mutation.mutate()}
        disabled={mutation.isPending || starValue === 0}
        className="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-amber-600 hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium rounded-lg transition-colors"
      >
        {mutation.isPending ? (
          <><Loader2 size={14} className="animate-spin" /> Submitting…</>
        ) : (
          <><Star size={14} /> Submit Rating</>
        )}
      </button>
    </div>
  );
}

// ── Main component ────────────────────────────────────────────────────────────

export default function WorkOrderDetailPage() {
  const { id } = useParams<{ id: string }>();
  const { user, hasRole } = useAuth();
  const queryClient = useQueryClient();
  const [lightboxSrc, setLightboxSrc] = useState<string | null>(null);

  const workOrderId = Number(id);

  const { data: workOrder, isLoading, isError, error } = useQuery({
    queryKey: ['work-order', workOrderId],
    queryFn: () => getWorkOrder(workOrderId),
    enabled: !!workOrderId && !Number.isNaN(workOrderId),
    refetchInterval: 30_000,
  });

  const handleActionSuccess = () => {
    queryClient.invalidateQueries({ queryKey: ['work-order', workOrderId] });
    queryClient.invalidateQueries({ queryKey: ['work-orders'] });
  };

  // ── Loading state ──────────────────────────────────────────────────────────

  if (isLoading) {
    return (
      <div>
        <div className="mb-6">
          <Link to="/work-orders" className="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-white transition-colors">
            <ArrowLeft size={15} /> Back
          </Link>
        </div>
        <DetailSkeleton />
      </div>
    );
  }

  // ── Error state ────────────────────────────────────────────────────────────

  if (isError || !workOrder) {
    return (
      <div>
        <div className="mb-6">
          <Link to="/work-orders" className="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-white transition-colors">
            <ArrowLeft size={15} /> Back
          </Link>
        </div>
        <div className="flex flex-col items-center gap-3 py-20 text-center">
          <AlertCircle size={40} className="text-red-400" />
          <p className="text-white font-semibold">Failed to load work order</p>
          <p className="text-sm text-gray-400">
            {(error as Error)?.message ?? 'An unexpected error occurred.'}
          </p>
          <button
            onClick={() => queryClient.invalidateQueries({ queryKey: ['work-order', workOrderId] })}
            className="mt-2 inline-flex items-center gap-2 px-4 py-2 bg-accent hover:bg-accent-hover text-white text-sm rounded-lg transition-colors"
          >
            <RefreshCw size={14} />
            Retry
          </button>
        </div>
      </div>
    );
  }

  // ── Derive role-specific flags ─────────────────────────────────────────────

  const isDispatcher = hasRole('ROLE_DISPATCHER');
  const isTechnician = hasRole('ROLE_TECHNICIAN');
  const isEmployee = hasRole('ROLE_EMPLOYEE');

  const showDispatchPanel =
    isDispatcher && workOrder.status === 'submitted';

  const showTechnicianPanel =
    isTechnician &&
    workOrder.assignedTechnicianId === user?.id &&
    ['dispatched', 'accepted', 'in_progress'].includes(workOrder.status);

  const showRatingPanel =
    (isEmployee || hasRole('ROLE_ADMIN')) &&
    workOrder.status === 'completed' &&
    workOrder.submittedById === user?.id;

  const statusMeta = STATUS_BADGE[workOrder.status] ?? {
    label: workOrder.status,
    className: 'bg-gray-500/20 text-gray-400 border-gray-500/30',
  };
  const priorityMeta = PRIORITY_BADGE[workOrder.priority] ?? {
    label: workOrder.priority,
    className: 'bg-gray-500/20 text-gray-400 border-gray-500/30',
    dot: 'bg-gray-400',
  };

  return (
    <div className="space-y-5 max-w-4xl">
      {/* Lightbox */}
      {lightboxSrc && (
        <div
          className="fixed inset-0 z-50 bg-black/90 flex items-center justify-center p-4"
          onClick={() => setLightboxSrc(null)}
        >
          <img
            src={lightboxSrc}
            alt="Work order photo"
            className="max-w-full max-h-[90vh] object-contain rounded-xl shadow-2xl"
            onClick={(e) => e.stopPropagation()}
          />
          <button
            onClick={() => setLightboxSrc(null)}
            className="absolute top-4 right-4 w-8 h-8 bg-white/10 hover:bg-white/20 rounded-full flex items-center justify-center text-white transition-colors"
          >
            ✕
          </button>
        </div>
      )}

      {/* Back nav */}
      <div>
        <Link
          to="/work-orders"
          className="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-white transition-colors"
        >
          <ArrowLeft size={15} /> Back to Work Orders
        </Link>
      </div>

      {/* Header */}
      <div className="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <div className="flex items-center gap-2 flex-wrap mb-2">
            <span className="text-xs text-gray-500 font-mono">WO-{workOrder.id}</span>
            <Badge label={priorityMeta.label} className={priorityMeta.className} dot={priorityMeta.dot} />
            <Badge label={statusMeta.label} className={statusMeta.className} />
          </div>
          <h1 className="text-2xl font-bold text-white">
            {workOrder.category} — {workOrder.building}
          </h1>
          <p className="text-gray-400 text-sm mt-1 flex items-center gap-1.5">
            <MapPin size={12} />
            {workOrder.building}, {workOrder.room}
          </p>
        </div>
      </div>

      {/* State machine progress */}
      <div className="bg-surface-card border border-surface-border rounded-xl p-5">
        <h2 className="text-sm font-semibold text-white mb-5 flex items-center gap-2">
          <Tag size={14} className="text-accent-light" />
          Progress
        </h2>
        <StateMachineProgress status={workOrder.status} />
      </div>

      {/* Main grid: details + side panels */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {/* Left column — 2/3 */}
        <div className="lg:col-span-2 space-y-5">
          {/* Details card */}
          <div className="bg-surface-card border border-surface-border rounded-xl p-5">
            <h2 className="text-sm font-semibold text-white mb-4 flex items-center gap-2">
              <FileText size={14} className="text-accent-light" />
              Work Order Details
            </h2>
            <div className="grid grid-cols-2 gap-4 mb-4">
              <DetailItem icon={Tag}       label="Category"      value={workOrder.category} />
              <DetailItem icon={Building2} label="Building"      value={workOrder.building} />
              <DetailItem icon={DoorOpen}  label="Room / Floor"  value={workOrder.room} />
              <DetailItem icon={User}      label="Submitted By"  value={workOrder.submittedByName} />
              <DetailItem icon={Calendar}  label="Created"       value={fmtDate(workOrder.createdAt)} />
              {workOrder.rating !== null && (
                <div className="flex items-start gap-3">
                  <Star size={15} className="text-gray-500 mt-0.5 flex-shrink-0" />
                  <div>
                    <p className="text-xs text-gray-500 mb-0.5">Rating</p>
                    <div className="flex items-center gap-1">
                      {Array.from({ length: 5 }).map((_, i) => (
                        <Star
                          key={i}
                          size={14}
                          className={i < workOrder.rating! ? 'text-amber-400 fill-amber-400' : 'text-gray-600'}
                        />
                      ))}
                      <span className="text-xs text-gray-400 ml-1">{workOrder.rating}/5</span>
                    </div>
                  </div>
                </div>
              )}
            </div>

            {/* Description */}
            <div className="border-t border-surface-border pt-4">
              <p className="text-xs text-gray-500 mb-2">Description</p>
              <p className="text-sm text-gray-200 leading-relaxed whitespace-pre-wrap">
                {workOrder.description}
              </p>
            </div>

            {/* Completion notes */}
            {workOrder.completionNotes && (
              <div className="border-t border-surface-border pt-4 mt-4">
                <p className="text-xs text-gray-500 mb-2">Completion Notes</p>
                <p className="text-sm text-gray-200 leading-relaxed whitespace-pre-wrap">
                  {workOrder.completionNotes}
                </p>
              </div>
            )}
          </div>

          {/* Photos gallery */}
          {workOrder.photos && workOrder.photos.length > 0 && (
            <div className="bg-surface-card border border-surface-border rounded-xl p-5">
              <h2 className="text-sm font-semibold text-white mb-4 flex items-center gap-2">
                <ImageIcon size={14} className="text-accent-light" />
                Photos
                <span className="text-xs text-gray-500 ml-auto">{workOrder.photos.length} file{workOrder.photos.length !== 1 ? 's' : ''}</span>
              </h2>
              <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                {workOrder.photos.map((photo) => (
                  <div
                    key={photo.id}
                    className="relative aspect-square rounded-lg overflow-hidden border border-surface-border cursor-pointer group"
                    onClick={() => setLightboxSrc(photo.url)}
                  >
                    <img
                      src={photo.url}
                      alt={photo.originalFilename}
                      className="w-full h-full object-cover transition-transform duration-200 group-hover:scale-105"
                    />
                    <div className="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-colors flex items-center justify-center">
                      <ImageIcon size={20} className="text-white opacity-0 group-hover:opacity-100 transition-opacity" />
                    </div>
                    <p className="absolute bottom-0 left-0 right-0 px-2 py-1 bg-gradient-to-t from-black/70 text-white text-[10px] truncate opacity-0 group-hover:opacity-100 transition-opacity">
                      {photo.originalFilename}
                    </p>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Status history timeline */}
          <div className="bg-surface-card border border-surface-border rounded-xl p-5">
            <h2 className="text-sm font-semibold text-white mb-4 flex items-center gap-2">
              <Clock size={14} className="text-accent-light" />
              Status History
            </h2>
            <div className="space-y-4">
              <TimelineEntry label="Submitted"   timestamp={workOrder.createdAt}    done={true} />
              <TimelineEntry label="Dispatched"  timestamp={workOrder.dispatchedAt} done={!!workOrder.dispatchedAt} />
              <TimelineEntry label="Accepted"    timestamp={workOrder.acceptedAt}   done={!!workOrder.acceptedAt} />
              <TimelineEntry label="In Progress" timestamp={workOrder.startedAt}    done={!!workOrder.startedAt} />
              <TimelineEntry label="Completed"   timestamp={workOrder.completedAt}  done={!!workOrder.completedAt} />
              <TimelineEntry label="Rated"       timestamp={workOrder.ratedAt}      done={!!workOrder.ratedAt} />
            </div>
          </div>
        </div>

        {/* Right column — 1/3 */}
        <div className="space-y-5">
          {/* Assignment info */}
          <div className="bg-surface-card border border-surface-border rounded-xl p-5">
            <h2 className="text-sm font-semibold text-white mb-4 flex items-center gap-2">
              <User size={14} className="text-accent-light" />
              Assignment
            </h2>
            <div className="space-y-3">
              <div>
                <p className="text-xs text-gray-500 mb-0.5">Dispatcher</p>
                <p className="text-sm text-white">
                  {workOrder.assignedDispatcherName ?? <span className="text-gray-600 italic">Not yet dispatched</span>}
                </p>
              </div>
              <div>
                <p className="text-xs text-gray-500 mb-0.5">Technician</p>
                <p className="text-sm text-white">
                  {workOrder.assignedTechnicianName ?? <span className="text-gray-600 italic">Not yet assigned</span>}
                </p>
              </div>
            </div>
          </div>

          {/* Role-specific action panels */}
          {showDispatchPanel && (
            <DispatcherPanel workOrderId={workOrder.id} onSuccess={handleActionSuccess} />
          )}

          {showTechnicianPanel && (
            <TechnicianPanel workOrder={workOrder} onSuccess={handleActionSuccess} />
          )}

          {showRatingPanel && (
            <RatingPanel workOrder={workOrder} onSuccess={handleActionSuccess} />
          )}
        </div>
      </div>
    </div>
  );
}
