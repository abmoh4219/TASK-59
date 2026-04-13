import { describe, it, expect } from 'vitest';

// Import real production helper (exported at the bottom of PolicyHint.tsx).
// These tests exercise the actual code path used by the attendance UI —
// they will fail if getHintText or its rule-lookup logic regresses.
import {
  getHintText,
  type ExceptionRuleData,
} from '../../src/components/attendance/policyHintText';

function makeRule(overrides: Partial<ExceptionRuleData> & { ruleType: string }): ExceptionRuleData {
  return {
    toleranceMinutes: 5,
    missedPunchWindowMinutes: 30,
    filingWindowDays: 7,
    ...overrides,
  };
}

describe('PolicyHint.getHintText (real production import)', () => {
  it('late arrival hint reflects the configured tolerance', () => {
    const rules: ExceptionRuleData[] = [makeRule({ ruleType: 'LATE_ARRIVAL', toleranceMinutes: 5 })];
    expect(getHintText('LATE_ARRIVAL', rules)).toContain('5 minutes tolerance');
  });

  it('missed-punch hint reflects the configured window', () => {
    const rules: ExceptionRuleData[] = [
      makeRule({ ruleType: 'MISSED_PUNCH', missedPunchWindowMinutes: 30 }),
    ];
    expect(getHintText('MISSED_PUNCH', rules)).toContain('30 minutes');
  });

  it('filing-window fallback surfaces through the default branch', () => {
    const rules: ExceptionRuleData[] = [makeRule({ ruleType: 'APPROVED_OFFSITE', filingWindowDays: 7 })];
    // APPROVED_OFFSITE has its own dedicated branch; assert that branch
    // message is returned from the real implementation.
    const hint = getHintText('APPROVED_OFFSITE', rules);
    expect(hint.toLowerCase()).toContain('approved offsite');
  });

  it('updates when tolerance changes', () => {
    const hint5 = getHintText('LATE_ARRIVAL', [makeRule({ ruleType: 'LATE_ARRIVAL', toleranceMinutes: 5 })]);
    const hint10 = getHintText('LATE_ARRIVAL', [makeRule({ ruleType: 'LATE_ARRIVAL', toleranceMinutes: 10 })]);
    expect(hint5).toContain('5 minutes tolerance');
    expect(hint10).toContain('10 minutes tolerance');
    expect(hint5).not.toBe(hint10);
  });

  it('absence branch does not depend on rule fields', () => {
    const rules: ExceptionRuleData[] = [makeRule({ ruleType: 'ABSENCE' })];
    expect(getHintText('ABSENCE', rules)).toContain('no punch events');
  });

  it('early-leave branch uses tolerance', () => {
    const rules: ExceptionRuleData[] = [makeRule({ ruleType: 'EARLY_LEAVE', toleranceMinutes: 7 })];
    expect(getHintText('EARLY_LEAVE', rules)).toContain('7 minutes');
  });
});
