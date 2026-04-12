import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Settings,
  Save,
  AlertCircle,
  CheckCircle2,
  Loader2,
  RefreshCw,
  Lock,
  Clock,
  ShieldAlert,
} from 'lucide-react';
import apiClient from '../../api/client';

// ── Types ──────────────────────────────────────────────────────────────────────

interface ExceptionRule {
  ruleType: string;
  toleranceMinutes: number;
  missedPunchWindowMinutes: number;
  filingWindowDays: number;
}

interface SystemConfig {
  rules: ExceptionRule[];
  slaHours: number;
  businessHoursStart: string;
  businessHoursEnd: string;
  escalationThresholdHours: number;
  anomalyAlerts?: AnomalyAlert[];
}

interface AnomalyAlert {
  id: number;
  username: string;
  ipAddress: string;
  attemptedAt: string;
  attemptCount: number;
}

// ── API ────────────────────────────────────────────────────────────────────────

const fetchConfig = async (): Promise<SystemConfig> => {
  const res = await apiClient.get<SystemConfig>('/admin/config');
  return res.data;
};

const saveConfig = async (rules: ExceptionRule[]): Promise<SystemConfig> => {
  const res = await apiClient.put<SystemConfig>('/admin/config', { rules });
  return res.data;
};

const fetchAnomalyAlerts = async (): Promise<AnomalyAlert[]> => {
  const res = await apiClient.get<AnomalyAlert[]>('/admin/anomaly-alerts');
  return res.data;
};

// ── Helpers ────────────────────────────────────────────────────────────────────

function formatTs(iso: string): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('en-US', {
    month: '2-digit',
    day: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  });
}

// ── Input Styles ───────────────────────────────────────────────────────────────

const numInputClass =
  'w-28 bg-surface border border-surface-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent placeholder-gray-500 transition-colors text-right font-mono';

// ── Skeleton ───────────────────────────────────────────────────────────────────

function ConfigSkeleton() {
  return (
    <div className="space-y-4 animate-pulse">
      {[1, 2, 3].map((i) => (
        <div key={i} className="h-20 w-full rounded-xl bg-surface-hover" />
      ))}
    </div>
  );
}

// ── Read-only config item ──────────────────────────────────────────────────────

function ReadOnlyItem({
  label,
  value,
  note,
  icon,
}: {
  label: string;
  value: string;
  note?: string;
  icon?: React.ReactNode;
}) {
  return (
    <div className="flex items-center justify-between py-3 border-b border-surface-border/60 last:border-0">
      <div className="flex items-start gap-2">
        {icon && <span className="mt-0.5 text-gray-500">{icon}</span>}
        <div>
          <p className="text-sm font-medium text-white">{label}</p>
          {note && <p className="text-xs text-gray-500 mt-0.5">{note}</p>}
        </div>
      </div>
      <div className="flex items-center gap-2">
        <span className="font-mono text-sm font-semibold text-accent">{value}</span>
        <Lock size={13} className="text-gray-600" />
      </div>
    </div>
  );
}

// ── Editable rule row ──────────────────────────────────────────────────────────

interface EditableRuleRowProps {
  rule: ExceptionRule;
  onChange: (updated: ExceptionRule) => void;
}

function EditableRuleRow({ rule, onChange }: EditableRuleRowProps) {
  const ruleLabel =
    rule.ruleType
      .replace(/_/g, ' ')
      .toLowerCase()
      .replace(/\b\w/g, (c) => c.toUpperCase()) || 'Default';

  return (
    <div className="py-4 border-b border-surface-border/60 last:border-0">
      <div className="flex items-center justify-between mb-3 flex-wrap gap-2">
        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full bg-indigo-500/15 text-indigo-300 border border-indigo-500/25 text-xs font-semibold">
          {ruleLabel}
        </span>
        <p className="text-xs text-gray-500">
          ruleType:{' '}
          <code className="font-mono text-gray-400">{rule.ruleType}</code>
        </p>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        {/* Tolerance */}
        <div className="flex flex-col gap-1.5">
          <label className="text-xs text-gray-500 font-medium uppercase tracking-wide">
            Arrival Tolerance (minutes)
          </label>
          <div className="flex items-center gap-2">
            <input
              type="number"
              min={0}
              max={120}
              value={rule.toleranceMinutes}
              onChange={(e) =>
                onChange({ ...rule, toleranceMinutes: Number(e.target.value) })
              }
              className={numInputClass}
            />
            <span className="text-xs text-gray-500">min</span>
          </div>
          <p className="text-xs text-gray-600">
            Punches within this window after shift start are not flagged as late.
          </p>
        </div>

        {/* Missed punch window */}
        <div className="flex flex-col gap-1.5">
          <label className="text-xs text-gray-500 font-medium uppercase tracking-wide">
            Missed Punch Window (minutes)
          </label>
          <div className="flex items-center gap-2">
            <input
              type="number"
              min={5}
              max={120}
              value={rule.missedPunchWindowMinutes}
              onChange={(e) =>
                onChange({
                  ...rule,
                  missedPunchWindowMinutes: Number(e.target.value),
                })
              }
              className={numInputClass}
            />
            <span className="text-xs text-gray-500">min</span>
          </div>
          <p className="text-xs text-gray-600">
            No punch within this window of shift start triggers a MISSED_PUNCH exception.
          </p>
        </div>

        {/* Filing window */}
        <div className="flex flex-col gap-1.5">
          <label className="text-xs text-gray-500 font-medium uppercase tracking-wide">
            Filing Window (days)
          </label>
          <div className="flex items-center gap-2">
            <input
              type="number"
              min={1}
              max={30}
              value={rule.filingWindowDays}
              onChange={(e) =>
                onChange({ ...rule, filingWindowDays: Number(e.target.value) })
              }
              className={numInputClass}
            />
            <span className="text-xs text-gray-500">days</span>
          </div>
          <p className="text-xs text-gray-600">
            Employees can only file exception requests within this many days of the
            exception date.
          </p>
        </div>
      </div>
    </div>
  );
}

// ── Main Page ──────────────────────────────────────────────────────────────────

export default function SystemConfigPage() {
  const queryClient = useQueryClient();

  const { data: config, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['admin-config'],
    queryFn: fetchConfig,
  });

  const { data: alerts } = useQuery({
    queryKey: ['admin-anomaly-alerts'],
    queryFn: fetchAnomalyAlerts,
    refetchInterval: 60_000,
  });

  // Local editable copy of rules
  const [localRules, setLocalRules] = useState<ExceptionRule[]>([]);
  const [saveSuccess, setSaveSuccess] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  // Sync rules when config loads
  useEffect(() => {
    if (config?.rules) {
      setLocalRules(config.rules);
    }
  }, [config]);

  const saveMutation = useMutation({
    mutationFn: () => saveConfig(localRules),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-config'] });
      setSaveSuccess(true);
      setSaveError(null);
      setTimeout(() => setSaveSuccess(false), 3500);
    },
    onError: (err: unknown) => {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data
          ?.message ?? (err as Error)?.message ?? 'Failed to save configuration.';
      setSaveError(msg);
      setSaveSuccess(false);
    },
  });

  function updateRule(index: number, updated: ExceptionRule) {
    setLocalRules((prev) => {
      const next = [...prev];
      next[index] = updated;
      return next;
    });
    setSaveSuccess(false);
    setSaveError(null);
  }

  const isDirty =
    config?.rules && JSON.stringify(localRules) !== JSON.stringify(config.rules);

  return (
    <div>
      {/* Header */}
      <div className="flex items-start justify-between mb-6 gap-4 flex-wrap">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-teal-500/10 border border-teal-500/20 flex items-center justify-center flex-shrink-0">
            <Settings size={20} className="text-teal-400" />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-white">System Configuration</h1>
            <p className="text-sm text-gray-400 mt-0.5">
              Attendance rules, SLA settings, and security alerts
            </p>
          </div>
        </div>
        <button
          onClick={() => refetch()}
          className="inline-flex items-center gap-2 px-3 py-2 bg-surface-card border border-surface-border hover:border-accent/50 text-gray-400 hover:text-white text-sm rounded-lg transition-colors"
        >
          <RefreshCw size={14} />
          Refresh
        </button>
      </div>

      {/* Loading */}
      {isLoading && <ConfigSkeleton />}

      {/* Error */}
      {isError && (
        <div className="flex flex-col items-center gap-3 py-16 text-center">
          <AlertCircle size={40} className="text-red-400" />
          <p className="text-white font-semibold">Failed to load configuration</p>
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

      {!isLoading && !isError && config && (
        <div className="space-y-6">
          {/* Read-only system constants */}
          <div className="bg-surface-card border border-surface-border rounded-xl p-5">
            <div className="flex items-center gap-2 mb-4">
              <Lock size={15} className="text-gray-400" />
              <h2 className="text-sm font-semibold text-white">
                System Constants{' '}
                <span className="text-xs font-normal text-gray-500">
                  (configurable via environment)
                </span>
              </h2>
            </div>

            <ReadOnlyItem
              label="Approval SLA"
              value={`${config.slaHours}h`}
              note="Maximum hours per approval step before escalation triggers"
              icon={<Clock size={14} />}
            />
            <ReadOnlyItem
              label="Business Hours Start"
              value={config.businessHoursStart}
              note="SLA countdown begins at this time (Mon–Fri)"
              icon={<Clock size={14} />}
            />
            <ReadOnlyItem
              label="Business Hours End"
              value={config.businessHoursEnd}
              note="SLA countdown pauses after this time"
              icon={<Clock size={14} />}
            />
            <ReadOnlyItem
              label="Escalation Threshold"
              value={`SLA + ${config.escalationThresholdHours}h`}
              note="Grace period after SLA deadline before auto-escalation"
              icon={<AlertCircle size={14} />}
            />
          </div>

          {/* Editable exception rules */}
          <div className="bg-surface-card border border-surface-border rounded-xl p-5">
            <div className="flex items-center justify-between mb-1 flex-wrap gap-3">
              <div className="flex items-center gap-2">
                <Settings size={15} className="text-accent" />
                <h2 className="text-sm font-semibold text-white">
                  Attendance Exception Rules
                </h2>
              </div>
              {isDirty && (
                <span className="text-xs text-amber-400 font-medium">
                  Unsaved changes
                </span>
              )}
            </div>
            <p className="text-xs text-gray-500 mb-4">
              These tolerances control when the attendance engine flags exceptions.
              Changes take effect on the next nightly run (02:00 AM).
            </p>

            {localRules.length === 0 ? (
              <p className="text-sm text-gray-500 py-4 text-center">
                No exception rules configured.
              </p>
            ) : (
              localRules.map((rule, idx) => (
                <EditableRuleRow
                  key={rule.ruleType || idx}
                  rule={rule}
                  onChange={(updated) => updateRule(idx, updated)}
                />
              ))
            )}

            {/* Save feedback */}
            {saveSuccess && (
              <div className="flex items-center gap-2 mt-4 p-3 rounded-lg bg-green-500/10 border border-green-500/20">
                <CheckCircle2 size={15} className="text-green-400 flex-shrink-0" />
                <p className="text-sm text-green-300">Configuration saved successfully.</p>
              </div>
            )}
            {saveError && (
              <div className="flex items-start gap-2 mt-4 p-3 rounded-lg bg-red-500/10 border border-red-500/20">
                <AlertCircle size={15} className="text-red-400 flex-shrink-0 mt-0.5" />
                <p className="text-sm text-red-300">{saveError}</p>
              </div>
            )}

            <div className="flex items-center justify-end mt-5">
              <button
                onClick={() => saveMutation.mutate()}
                disabled={saveMutation.isPending || !isDirty}
                className="inline-flex items-center gap-2 px-5 py-2.5 bg-accent hover:bg-accent-hover disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium rounded-lg transition-colors"
              >
                {saveMutation.isPending ? (
                  <>
                    <Loader2 size={15} className="animate-spin" />
                    Saving...
                  </>
                ) : (
                  <>
                    <Save size={15} />
                    Save Configuration
                  </>
                )}
              </button>
            </div>
          </div>

          {/* Anomaly / Failed Login Alerts */}
          <div className="bg-surface-card border border-surface-border rounded-xl p-5">
            <div className="flex items-center gap-2 mb-4">
              <ShieldAlert size={15} className="text-red-400" />
              <h2 className="text-sm font-semibold text-white">
                Security Alerts{' '}
                <span className="text-xs font-normal text-gray-500">
                  (failed login attempts)
                </span>
              </h2>
              {alerts && alerts.length > 0 && (
                <span className="ml-auto inline-flex items-center justify-center w-5 h-5 rounded-full bg-red-500 text-white text-xs font-bold">
                  {alerts.length > 9 ? '9+' : alerts.length}
                </span>
              )}
            </div>

            {!alerts && (
              <div className="space-y-2 animate-pulse">
                {[1, 2].map((i) => (
                  <div key={i} className="h-10 rounded-lg bg-surface-hover" />
                ))}
              </div>
            )}

            {alerts && alerts.length === 0 && (
              <div className="flex flex-col items-center py-8 gap-2 text-center">
                <CheckCircle2 size={28} className="text-green-400" />
                <p className="text-sm text-gray-400">No recent security alerts</p>
              </div>
            )}

            {alerts && alerts.length > 0 && (
              <div className="overflow-x-auto">
                <table className="w-full text-xs">
                  <thead>
                    <tr className="border-b border-surface-border">
                      {['Username', 'IP Address', 'Attempts', 'Last Attempt'].map(
                        (h) => (
                          <th
                            key={h}
                            className="px-3 py-2 text-left text-gray-500 uppercase tracking-wide font-semibold whitespace-nowrap"
                          >
                            {h}
                          </th>
                        ),
                      )}
                    </tr>
                  </thead>
                  <tbody>
                    {alerts.map((a) => (
                      <tr
                        key={a.id}
                        className="border-b border-surface-border/50 hover:bg-surface-hover transition-colors"
                      >
                        <td className="px-3 py-2 text-white font-medium">
                          {a.username}
                        </td>
                        <td className="px-3 py-2 font-mono text-gray-400">
                          {a.ipAddress}
                        </td>
                        <td className="px-3 py-2">
                          <span
                            className={`inline-flex items-center px-2 py-0.5 rounded-full font-bold border text-xs ${
                              a.attemptCount >= 5
                                ? 'bg-red-500/20 text-red-300 border-red-500/30'
                                : 'bg-amber-500/20 text-amber-300 border-amber-500/30'
                            }`}
                          >
                            {a.attemptCount}×
                          </span>
                        </td>
                        <td className="px-3 py-2 text-gray-400">
                          {formatTs(a.attemptedAt)}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
