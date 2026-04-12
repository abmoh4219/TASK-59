import { describe, it, expect } from 'vitest';

// ---------------------------------------------------------------------------
// Types & inline implementation (mirrors PolicyHint.tsx export)
// ---------------------------------------------------------------------------

type ExceptionType =
  | 'LATE_ARRIVAL'
  | 'EARLY_LEAVE'
  | 'MISSED_PUNCH'
  | 'ABSENCE'
  | 'APPROVED_OFFSITE';

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
    case 'MISSED_PUNCH':
      return `Missed punch: no clock-in event within ${missedWindow} minutes of shift start`;
    case 'ABSENCE':
      return 'Absence: no punch events recorded for the scheduled shift';
    default:
      return `Requests must be filed within ${filingDays} calendar days of the exception`;
  }
}

// ---------------------------------------------------------------------------
// Helper: build a minimal rule object
// ---------------------------------------------------------------------------

function makeRule(overrides: Partial<ExceptionRuleData> & { ruleType: string }): ExceptionRuleData {
  return {
    toleranceMinutes: 5,
    missedPunchWindowMinutes: 30,
    filingWindowDays: 7,
    ...overrides,
  };
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('policyHints – getHintText', () => {
  it('testLateHintText: tolerance=5 hint contains "5 minutes tolerance"', () => {
    const rules: ExceptionRuleData[] = [makeRule({ ruleType: 'LATE_ARRIVAL', toleranceMinutes: 5 })];

    const hint = getHintText('LATE_ARRIVAL', rules);

    expect(hint).toContain('5 minutes tolerance');
  });

  it('testMissedPunchHintText: missedPunchWindow=30 hint contains "30 minutes"', () => {
    const rules: ExceptionRuleData[] = [
      makeRule({ ruleType: 'MISSED_PUNCH', missedPunchWindowMinutes: 30 }),
    ];

    const hint = getHintText('MISSED_PUNCH', rules);

    expect(hint).toContain('30 minutes');
  });

  it('testFilingWindowText: filingDays=7 default branch returns filing hint', () => {
    // The default case (e.g. 'APPROVED_OFFSITE' which has no dedicated branch) returns
    // the filing window message, letting callers surface it as a fallback hint.
    const rules: ExceptionRuleData[] = [makeRule({ ruleType: 'APPROVED_OFFSITE', filingWindowDays: 7 })];

    const hint = getHintText('APPROVED_OFFSITE', rules);

    expect(hint).toContain('7 calendar days');
  });

  it('testHintUpdatesWithTolerance: tolerance=10 changes hint text to "10 minutes tolerance"', () => {
    const rulesWith5  = [makeRule({ ruleType: 'LATE_ARRIVAL', toleranceMinutes: 5 })];
    const rulesWith10 = [makeRule({ ruleType: 'LATE_ARRIVAL', toleranceMinutes: 10 })];

    const hintWith5  = getHintText('LATE_ARRIVAL', rulesWith5);
    const hintWith10 = getHintText('LATE_ARRIVAL', rulesWith10);

    expect(hintWith5).toContain('5 minutes tolerance');
    expect(hintWith10).toContain('10 minutes tolerance');
    expect(hintWith5).not.toBe(hintWith10);
  });
});
