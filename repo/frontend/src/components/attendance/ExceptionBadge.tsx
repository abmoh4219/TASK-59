import type { ExceptionType } from '../../types';

const badgeConfig: Record<ExceptionType, { label: string; className: string }> = {
  LATE_ARRIVAL: { label: 'Late', className: 'bg-amber-500/20 text-amber-400 border-amber-500/30' },
  EARLY_LEAVE: { label: 'Early Leave', className: 'bg-orange-500/20 text-orange-400 border-orange-500/30' },
  MISSED_PUNCH: { label: 'Missed Punch', className: 'bg-red-500/20 text-red-400 border-red-500/30' },
  ABSENCE: { label: 'Absent', className: 'bg-red-500/20 text-red-400 border-red-500/30' },
  APPROVED_OFFSITE: { label: 'Offsite', className: 'bg-green-500/20 text-green-400 border-green-500/30' },
};

export default function ExceptionBadge({ type }: { type: ExceptionType }) {
  const config = badgeConfig[type] || { label: type, className: 'bg-gray-500/20 text-gray-400 border-gray-500/30' };

  return (
    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${config.className}`}>
      {config.label}
    </span>
  );
}
