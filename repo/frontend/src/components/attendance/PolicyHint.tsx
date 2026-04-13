import { useQuery } from '@tanstack/react-query';
import apiClient from '../../api/client';
import type { ExceptionType } from '../../types';
import { Info } from 'lucide-react';
import { getHintText, type ExceptionRuleData } from './policyHintText';

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

// Re-export the pure helpers so existing test imports continue to resolve.
export { getHintText };
export type { ExceptionRuleData };
