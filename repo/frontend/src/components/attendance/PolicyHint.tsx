import { useQuery } from '@tanstack/react-query';
import apiClient from '../../api/client';
import type { ExceptionType } from '../../types';
import { Info } from 'lucide-react';

interface ExceptionRuleData {
  ruleType: string;
  toleranceMinutes: number;
  missedPunchWindowMinutes: number;
  filingWindowDays: number;
}

function getHintText(exceptionType: ExceptionType, rules: ExceptionRuleData[]): string {
  const rule = rules.find((r) => r.ruleType === exceptionType) || rules[0];
  const tolerance = rule?.toleranceMinutes ?? 5;
  const missedWindow = rule?.missedPunchWindowMinutes ?? 30;
  const filingDays = rule?.filingWindowDays ?? 7;

  switch (exceptionType) {
    case 'LATE_ARRIVAL':
      return `Late arrival: after shift start + ${tolerance} minutes tolerance (e.g., late after 9:0${tolerance} AM for a 9:00 AM shift)`;
    case 'EARLY_LEAVE':
      return `Early leave: departed more than ${tolerance} minutes before shift end`;
    case 'MISSED_PUNCH':
      return `Missed punch: no clock-in event within ${missedWindow} minutes of shift start`;
    case 'ABSENCE':
      return 'Absence: no punch events recorded for the scheduled shift';
    case 'APPROVED_OFFSITE':
      return 'Approved offsite: business trip or outing request approved for this date';
    default:
      return `Requests must be filed within ${filingDays} calendar days of the exception`;
  }
}

export function FilingWindowHint({ rules }: { rules: ExceptionRuleData[] }) {
  const filingDays = rules[0]?.filingWindowDays ?? 7;
  return (
    <div className="flex items-start gap-2 p-3 rounded-lg bg-blue-500/10 border border-blue-500/20">
      <Info size={16} className="text-blue-400 mt-0.5 flex-shrink-0" />
      <p className="text-sm text-blue-300">
        Requests must be filed within {filingDays} calendar days of the exception.
      </p>
    </div>
  );
}

export default function PolicyHint({ exceptions }: { exceptions: ExceptionType[] }) {
  const { data: rules } = useQuery<ExceptionRuleData[]>({
    queryKey: ['attendance', 'rules'],
    queryFn: async () => {
      const res = await apiClient.get('/attendance/rules');
      return res.data;
    },
  });

  if (!rules || exceptions.length === 0) return null;

  return (
    <div className="space-y-2">
      {exceptions.map((exc) => (
        <div
          key={exc}
          className="flex items-start gap-2 p-3 rounded-lg bg-amber-500/10 border border-amber-500/20"
        >
          <Info size={16} className="text-amber-400 mt-0.5 flex-shrink-0" />
          <p className="text-sm text-amber-200">{getHintText(exc, rules)}</p>
        </div>
      ))}
      <FilingWindowHint rules={rules} />
    </div>
  );
}

// Export for testing
export { getHintText };
export type { ExceptionRuleData };
