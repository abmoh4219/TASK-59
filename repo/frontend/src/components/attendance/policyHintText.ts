import type { ExceptionType } from '../../types';

/**
 * Dependency-free home for the policy-hint text logic. Kept separate from
 * PolicyHint.tsx so it can be imported by unit tests and other callers
 * without pulling in react / react-query.
 */
export interface ExceptionRuleData {
  ruleType: string;
  toleranceMinutes: number;
  missedPunchWindowMinutes: number;
  filingWindowDays: number;
}

export function getHintText(
  exceptionType: ExceptionType,
  rules: ExceptionRuleData[],
): string {
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
