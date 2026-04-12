import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import {
  Plus,
  Wrench,
  ChevronRight,
  AlertCircle,
  RefreshCw,
  Filter,
  Calendar,
  User,
} from 'lucide-react';
import { getWorkOrders } from '../../api/workOrders';
import { useAuth } from '../../context/AuthContext';
import type { WorkOrder, WorkOrderStatus, WorkOrderPriority } from '../../types';

// ── Badge maps ────────────────────────────────────────────────────────────────

const STATUS_BADGE: Record<WorkOrderStatus, { label: string; className: string }> = {
  submitted:   { label: 'Submitted',   className: 'bg-gray-500/20   text-gray-400   border-gray-500/30'   },
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

const STATUS_FILTER_OPTIONS: { value: string; label: string }[] = [
  { value: '',           label: 'All Statuses' },
  { value: 'submitted',  label: 'Submitted'    },
  { value: 'dispatched', label: 'Dispatched'   },
  { value: 'accepted',   label: 'Accepted'     },
  { value: 'in_progress',label: 'In Progress'  },
  { value: 'completed',  label: 'Completed'    },
  { value: 'rated',      label: 'Rated'        },
];

// ── Reusable badge ─────────────────────────────────────────────────────────────

function Badge({ className, label, dot }: { className: string; label: string; dot?: string }) {
  return (
    <span className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border ${className}`}>
      {dot && <span className={`w-1.5 h-1.5 rounded-full ${dot}`} />}
      {label}
    </span>
  );
}

// ── Skeleton row ──────────────────────────────────────────────────────────────

function SkeletonRow() {
  return (
    <tr className="border-b border-surface-border/50 animate-pulse">
      {[1, 2, 3, 4, 5, 6].map((i) => (
        <td key={i} className="py-4 px-4">
          <div className="h-4 bg-surface-hover rounded w-full max-w-[120px]" />
        </td>
      ))}
    </tr>
  );
}

// ── Empty state ────────────────────────────────────────────────────────────────

function EmptyState({ filtered }: { filtered: boolean }) {
  return (
    <div className="flex flex-col items-center gap-4 py-20 text-center">
      <div className="w-16 h-16 bg-surface-hover rounded-2xl flex items-center justify-center">
        <Wrench size={28} className="text-gray-600" />
      </div>
      <div>
        <p className="text-white font-semibold text-base">No work orders found</p>
        <p className="text-sm text-gray-400 mt-1">
          {filtered
            ? 'Try changing the status filter to see more results.'
            : 'Work orders submitted here will appear in this list.'}
        </p>
      </div>
    </div>
  );
}

// ── Formatted date ─────────────────────────────────────────────────────────────

function fmtDate(iso: string): string {
  return new Date(iso).toLocaleDateString('en-US', {
    month: '2-digit',
    day: '2-digit',
    year: 'numeric',
  });
}

// ── Main component ────────────────────────────────────────────────────────────

export default function WorkOrderListPage() {
  const navigate = useNavigate();
  const { hasRole } = useAuth();

  const [statusFilter, setStatusFilter] = useState('');

  const canCreate =
    hasRole('ROLE_EMPLOYEE') ||
    hasRole('ROLE_ADMIN') ||
    hasRole('ROLE_HR_ADMIN') ||
    hasRole('ROLE_SUPERVISOR');

  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['work-orders', statusFilter],
    queryFn: () => getWorkOrders({ status: statusFilter || undefined }),
  });

  const workOrders: WorkOrder[] = data?.data ?? [];

  return (
    <div className="space-y-5">
      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-2xl font-bold text-white">Work Orders</h1>
          <p className="text-sm text-gray-400 mt-0.5">
            {isLoading ? 'Loading…' : `${data?.total ?? 0} total`}
          </p>
        </div>
        <div className="flex items-center gap-3 flex-wrap">
          {/* Status filter */}
          <div className="relative">
            <Filter size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="bg-surface-card border border-surface-border rounded-lg pl-8 pr-8 py-2 text-sm text-white appearance-none focus:outline-none focus:ring-2 focus:ring-accent"
            >
              {STATUS_FILTER_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>{o.label}</option>
              ))}
            </select>
            <div className="pointer-events-none absolute inset-y-0 right-3 flex items-center">
              <svg className="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 20 20">
                <path stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M6 8l4 4 4-4" />
              </svg>
            </div>
          </div>

          {canCreate && (
            <button
              onClick={() => navigate('/work-orders/new')}
              className="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-accent to-accent-dark hover:from-accent-hover hover:to-accent text-white rounded-lg text-sm font-medium transition-all shadow-glow"
            >
              <Plus size={15} />
              New Work Order
            </button>
          )}
        </div>
      </div>

      {/* Table card */}
      <div className="bg-surface-card border border-surface-border rounded-xl overflow-hidden">
        {isError ? (
          <div className="flex flex-col items-center gap-3 py-16 text-center px-4">
            <AlertCircle size={36} className="text-red-400" />
            <p className="text-white font-semibold">Failed to load work orders</p>
            <p className="text-sm text-gray-400">
              {(error as Error)?.message ?? 'An unexpected error occurred.'}
            </p>
            <button
              onClick={() => refetch()}
              className="mt-2 inline-flex items-center gap-2 px-4 py-2 bg-accent hover:bg-accent-hover text-white text-sm rounded-lg transition-colors"
            >
              <RefreshCw size={14} />
              Retry
            </button>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[700px]">
              <thead>
                <tr className="border-b border-surface-border">
                  <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Order</th>
                  <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Priority</th>
                  <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                  <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Submitted By</th>
                  <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                  <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Technician</th>
                  <th className="py-3 px-4" />
                </tr>
              </thead>
              <tbody>
                {isLoading
                  ? Array.from({ length: 5 }).map((_, i) => <SkeletonRow key={i} />)
                  : workOrders.length === 0
                  ? (
                    <tr>
                      <td colSpan={7}>
                        <EmptyState filtered={!!statusFilter} />
                      </td>
                    </tr>
                  )
                  : workOrders.map((wo) => {
                    const statusMeta = STATUS_BADGE[wo.status] ?? {
                      label: wo.status,
                      className: 'bg-gray-500/20 text-gray-400 border-gray-500/30',
                    };
                    const priorityMeta = PRIORITY_BADGE[wo.priority] ?? {
                      label: wo.priority,
                      className: 'bg-gray-500/20 text-gray-400 border-gray-500/30',
                      dot: 'bg-gray-400',
                    };

                    return (
                      <tr
                        key={wo.id}
                        onClick={() => navigate(`/work-orders/${wo.id}`)}
                        className="border-b border-surface-border/50 hover:bg-surface-hover/60 cursor-pointer transition-colors group"
                      >
                        {/* Title / location */}
                        <td className="py-3.5 px-4">
                          <p className="text-sm font-medium text-white group-hover:text-accent-light transition-colors">
                            #{wo.id} — {wo.category}
                          </p>
                          <p className="text-xs text-gray-500 mt-0.5">
                            {wo.building} · {wo.room}
                          </p>
                        </td>

                        {/* Priority */}
                        <td className="py-3.5 px-4">
                          <Badge
                            label={priorityMeta.label}
                            className={priorityMeta.className}
                            dot={priorityMeta.dot}
                          />
                        </td>

                        {/* Status */}
                        <td className="py-3.5 px-4">
                          <Badge label={statusMeta.label} className={statusMeta.className} />
                        </td>

                        {/* Submitted by */}
                        <td className="py-3.5 px-4">
                          <span className="flex items-center gap-1.5 text-sm text-gray-300">
                            <User size={13} className="text-gray-500 flex-shrink-0" />
                            {wo.submittedByName}
                          </span>
                        </td>

                        {/* Date */}
                        <td className="py-3.5 px-4">
                          <span className="flex items-center gap-1.5 text-sm text-gray-400">
                            <Calendar size={13} className="text-gray-600 flex-shrink-0" />
                            {fmtDate(wo.createdAt)}
                          </span>
                        </td>

                        {/* Technician */}
                        <td className="py-3.5 px-4">
                          {wo.assignedTechnicianName ? (
                            <span className="text-sm text-gray-300">{wo.assignedTechnicianName}</span>
                          ) : (
                            <span className="text-xs text-gray-600 italic">Unassigned</span>
                          )}
                        </td>

                        {/* Arrow */}
                        <td className="py-3.5 px-4 text-right">
                          <ChevronRight size={16} className="text-gray-600 group-hover:text-gray-400 transition-colors inline-block" />
                        </td>
                      </tr>
                    );
                  })
                }
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
