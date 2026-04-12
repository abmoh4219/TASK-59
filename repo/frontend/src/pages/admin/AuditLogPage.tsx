import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
  Shield,
  AlertCircle,
  Loader2,
  SearchX,
  ChevronLeft,
  ChevronRight,
  Lock,
  RefreshCw,
} from 'lucide-react';
import apiClient from '../../api/client';
import type { AuditLogEntry } from '../../types';

// ── Types ──────────────────────────────────────────────────────────────────────

interface AuditLogsResponse {
  data: AuditLogEntry[];
  total: number;
  page: number;
  limit: number;
  retention: string;
}

interface Filters {
  entity: string;
  actor: string;
  from: string;
  to: string;
}

// ── API ────────────────────────────────────────────────────────────────────────

async function fetchAuditLogs(
  filters: Filters,
  page: number,
): Promise<AuditLogsResponse> {
  const params = new URLSearchParams();
  if (filters.entity) params.set('entity', filters.entity);
  if (filters.actor) params.set('actor', filters.actor);
  if (filters.from) params.set('from', filters.from);
  if (filters.to) params.set('to', filters.to);
  params.set('page', String(page));

  const res = await apiClient.get<AuditLogsResponse>(
    `/audit/logs?${params.toString()}`,
  );
  return res.data;
}

// ── Helpers ────────────────────────────────────────────────────────────────────

function maskIp(ip: string): string {
  if (!ip) return '—';
  // IPv4: mask last octet → x.x.x.***
  const parts = ip.split('.');
  if (parts.length === 4) {
    return `${parts[0]}.${parts[1]}.${parts[2]}.***`;
  }
  // IPv6 or unknown: mask last segment
  const idx = ip.lastIndexOf(':');
  if (idx !== -1) return `${ip.substring(0, idx)}:****`;
  return ip;
}

function formatTs(iso: string): string {
  if (!iso) return '—';
  const d = new Date(iso);
  return d.toLocaleString('en-US', {
    month: '2-digit',
    day: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
  });
}

function summarizeChanges(
  oldVal: Record<string, unknown>,
  newVal: Record<string, unknown>,
): string {
  if (!oldVal && !newVal) return '—';
  const old_ = oldVal ?? {};
  const new_ = newVal ?? {};
  const allKeys = new Set([...Object.keys(old_), ...Object.keys(new_)]);
  const diffs: string[] = [];
  allKeys.forEach((k) => {
    const o = old_[k];
    const n = new_[k];
    if (JSON.stringify(o) !== JSON.stringify(n)) {
      const oStr = o !== undefined ? String(o) : '∅';
      const nStr = n !== undefined ? String(n) : '∅';
      diffs.push(`${k}: ${oStr} → ${nStr}`);
    }
  });
  if (diffs.length === 0) return '—';
  const summary = diffs.slice(0, 2).join(' | ');
  return diffs.length > 2 ? `${summary} (+${diffs.length - 2} more)` : summary;
}

const ENTITY_OPTIONS = [
  'User',
  'ExceptionRequest',
  'ApprovalStep',
  'ApprovalAction',
  'WorkOrder',
  'Booking',
  'PunchEvent',
  'AttendanceRecord',
  'Resource',
];

// ── Skeleton ───────────────────────────────────────────────────────────────────

function TableSkeleton() {
  return (
    <div className="space-y-2 animate-pulse">
      {Array.from({ length: 8 }).map((_, i) => (
        <div key={i} className="h-11 w-full rounded-lg bg-surface-hover" />
      ))}
    </div>
  );
}

// ── Main Page ──────────────────────────────────────────────────────────────────

export default function AuditLogPage() {
  const [filters, setFilters] = useState<Filters>({
    entity: '',
    actor: '',
    from: '',
    to: '',
  });
  const [pendingFilters, setPendingFilters] = useState<Filters>(filters);
  const [page, setPage] = useState(1);

  const { data, isLoading, isError, error, refetch, isFetching } = useQuery({
    queryKey: ['audit-logs', filters, page],
    queryFn: () => fetchAuditLogs(filters, page),
    placeholderData: (prev) => prev,
  });

  const totalPages = data ? Math.ceil(data.total / data.limit) : 1;

  function applyFilters() {
    setFilters(pendingFilters);
    setPage(1);
  }

  function clearFilters() {
    const empty: Filters = { entity: '', actor: '', from: '', to: '' };
    setPendingFilters(empty);
    setFilters(empty);
    setPage(1);
  }

  const inputClass =
    'bg-surface border border-surface-border rounded-lg px-3 py-1.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent placeholder-gray-500 transition-colors';
  const selectClass =
    'bg-surface border border-surface-border rounded-lg px-3 py-1.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent transition-colors appearance-none';

  return (
    <div>
      {/* Header */}
      <div className="flex items-start justify-between mb-6 gap-4 flex-wrap">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center flex-shrink-0">
            <Shield size={20} className="text-indigo-400" />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-white">Audit Log</h1>
            <p className="text-sm text-gray-400 mt-0.5">
              Immutable record of all system actions
            </p>
          </div>
        </div>
        <button
          onClick={() => refetch()}
          disabled={isFetching}
          className="inline-flex items-center gap-2 px-3 py-2 bg-surface-card border border-surface-border hover:border-accent/50 text-gray-400 hover:text-white text-sm rounded-lg transition-colors disabled:opacity-60"
        >
          <RefreshCw size={14} className={isFetching ? 'animate-spin' : ''} />
          Refresh
        </button>
      </div>

      {/* Immutable notice */}
      <div className="flex items-start gap-3 p-4 mb-6 rounded-xl bg-amber-500/10 border border-amber-500/20">
        <Lock size={18} className="text-amber-400 flex-shrink-0 mt-0.5" />
        <div>
          <p className="text-sm font-semibold text-amber-300">
            IMMUTABLE RECORD
          </p>
          <p className="text-xs text-amber-200/70 mt-0.5">
            {data?.retention
              ? `Records retained for ${data.retention} and cannot be modified or deleted.`
              : 'Records are retained for 7 years and cannot be modified or deleted.'}
            {' '}This log is append-only and protected from all edit operations.
          </p>
        </div>
      </div>

      {/* Filters */}
      <div className="bg-surface-card border border-surface-border rounded-xl p-4 mb-6">
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          {/* Entity type */}
          <div className="flex flex-col gap-1">
            <label className="text-xs text-gray-500 font-medium uppercase tracking-wide">
              Entity Type
            </label>
            <select
              value={pendingFilters.entity}
              onChange={(e) =>
                setPendingFilters((f) => ({ ...f, entity: e.target.value }))
              }
              className={selectClass}
            >
              <option value="">All Entities</option>
              {ENTITY_OPTIONS.map((e) => (
                <option key={e} value={e}>
                  {e}
                </option>
              ))}
            </select>
          </div>

          {/* Actor */}
          <div className="flex flex-col gap-1">
            <label className="text-xs text-gray-500 font-medium uppercase tracking-wide">
              Actor Username
            </label>
            <input
              type="text"
              value={pendingFilters.actor}
              onChange={(e) =>
                setPendingFilters((f) => ({ ...f, actor: e.target.value }))
              }
              placeholder="e.g. admin"
              className={inputClass}
            />
          </div>

          {/* From */}
          <div className="flex flex-col gap-1">
            <label className="text-xs text-gray-500 font-medium uppercase tracking-wide">
              From Date
            </label>
            <input
              type="date"
              value={pendingFilters.from}
              onChange={(e) =>
                setPendingFilters((f) => ({ ...f, from: e.target.value }))
              }
              className={inputClass}
            />
          </div>

          {/* To */}
          <div className="flex flex-col gap-1">
            <label className="text-xs text-gray-500 font-medium uppercase tracking-wide">
              To Date
            </label>
            <input
              type="date"
              value={pendingFilters.to}
              onChange={(e) =>
                setPendingFilters((f) => ({ ...f, to: e.target.value }))
              }
              className={inputClass}
            />
          </div>
        </div>

        <div className="flex items-center gap-2 mt-3 justify-end">
          <button
            onClick={clearFilters}
            className="px-3 py-1.5 text-xs text-gray-400 hover:text-white transition-colors"
          >
            Clear
          </button>
          <button
            onClick={applyFilters}
            className="px-4 py-1.5 bg-accent hover:bg-accent-hover text-white text-xs font-medium rounded-lg transition-colors"
          >
            Apply Filters
          </button>
        </div>
      </div>

      {/* Results count */}
      {data && (
        <p className="text-xs text-gray-500 mb-3">
          Showing{' '}
          <span className="text-gray-300 font-medium">
            {(page - 1) * data.limit + 1}–
            {Math.min(page * data.limit, data.total)}
          </span>{' '}
          of <span className="text-gray-300 font-medium">{data.total}</span>{' '}
          records
        </p>
      )}

      {/* Loading */}
      {isLoading && <TableSkeleton />}

      {/* Error */}
      {isError && (
        <div className="flex flex-col items-center gap-3 py-16 text-center">
          <AlertCircle size={40} className="text-red-400" />
          <p className="text-white font-semibold">Failed to load audit logs</p>
          <p className="text-sm text-gray-400">
            {(error as Error)?.message ?? 'An unexpected error occurred.'}
          </p>
          <button
            onClick={() => refetch()}
            className="mt-2 px-4 py-2 bg-accent hover:bg-accent-hover text-white text-sm rounded-lg transition-colors"
          >
            Retry
          </button>
        </div>
      )}

      {/* Empty */}
      {!isLoading && !isError && data?.data.length === 0 && (
        <div className="flex flex-col items-center gap-4 py-20 text-center">
          <div className="w-16 h-16 rounded-full bg-surface-card border border-surface-border flex items-center justify-center">
            <SearchX size={28} className="text-gray-500" />
          </div>
          <p className="text-white font-semibold text-lg">No log entries found</p>
          <p className="text-sm text-gray-400 max-w-xs">
            Try adjusting your filters or clearing them to see all records.
          </p>
          <button
            onClick={clearFilters}
            className="mt-1 px-4 py-2 bg-surface-card border border-surface-border hover:border-accent/50 text-gray-300 text-sm rounded-lg transition-colors"
          >
            Clear Filters
          </button>
        </div>
      )}

      {/* Table */}
      {!isLoading && !isError && data && data.data.length > 0 && (
        <div
          className={`bg-surface-card border border-surface-border rounded-xl overflow-hidden transition-opacity ${
            isFetching ? 'opacity-60' : 'opacity-100'
          }`}
        >
          {isFetching && (
            <div className="flex items-center justify-center gap-2 py-2 bg-accent/10 border-b border-accent/20 text-xs text-accent">
              <Loader2 size={12} className="animate-spin" />
              Loading…
            </div>
          )}
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-surface-border">
                  {[
                    'Timestamp',
                    'Actor',
                    'Action',
                    'Entity Type',
                    'Entity ID',
                    'IP Address',
                    'Changes',
                  ].map((col) => (
                    <th
                      key={col}
                      className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap"
                    >
                      {col}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {data.data.map((entry, idx) => (
                  <tr
                    key={entry.id}
                    className={`border-b border-surface-border/50 hover:bg-surface-hover transition-colors ${
                      idx % 2 === 0 ? '' : 'bg-surface/30'
                    }`}
                  >
                    <td className="px-4 py-3 whitespace-nowrap font-mono text-xs text-gray-300">
                      {formatTs(entry.createdAt)}
                    </td>
                    <td className="px-4 py-3 whitespace-nowrap">
                      <span className="text-white font-medium">
                        {entry.actorUsername}
                      </span>
                    </td>
                    <td className="px-4 py-3 whitespace-nowrap">
                      <span className="inline-flex items-center px-2 py-0.5 rounded-full bg-indigo-500/15 border border-indigo-500/25 text-indigo-300 text-xs font-medium">
                        {entry.action}
                      </span>
                    </td>
                    <td className="px-4 py-3 whitespace-nowrap text-gray-300">
                      {entry.entityType}
                    </td>
                    <td className="px-4 py-3 whitespace-nowrap text-gray-400 font-mono text-xs">
                      #{entry.entityId}
                    </td>
                    <td className="px-4 py-3 whitespace-nowrap font-mono text-xs text-gray-400">
                      {maskIp(entry.ipAddress)}
                    </td>
                    <td className="px-4 py-3 max-w-xs text-xs text-gray-400 truncate">
                      {summarizeChanges(
                        entry.oldValue as Record<string, unknown>,
                        entry.newValue as Record<string, unknown>,
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          <div className="flex items-center justify-between px-4 py-3 border-t border-surface-border">
            <p className="text-xs text-gray-500">
              Page {page} of {totalPages}
            </p>
            <div className="flex items-center gap-2">
              <button
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                disabled={page <= 1}
                className="inline-flex items-center gap-1 px-3 py-1.5 bg-surface border border-surface-border hover:border-accent/40 text-gray-400 hover:text-white text-xs rounded-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
              >
                <ChevronLeft size={13} />
                Prev
              </button>
              <button
                onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                disabled={page >= totalPages}
                className="inline-flex items-center gap-1 px-3 py-1.5 bg-surface border border-surface-border hover:border-accent/40 text-gray-400 hover:text-white text-xs rounded-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
              >
                Next
                <ChevronRight size={13} />
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
