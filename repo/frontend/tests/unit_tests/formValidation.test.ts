import { describe, it, expect } from 'vitest';

/**
 * Form validation utility tests — validates date ranges, time windows,
 * and request type constraints used in ExceptionRequestForm.
 */

function isValidDateFormat(value: string): boolean {
  if (!value) return false;
  const isoPattern = /^\d{4}-\d{2}-\d{2}$/;
  const usPattern = /^\d{2}\/\d{2}\/\d{4}$/;
  if (!isoPattern.test(value) && !usPattern.test(value)) return false;
  const d = new Date(value);
  return !isNaN(d.getTime());
}

function isEndDateOnOrAfterStart(startDate: string, endDate: string): boolean {
  return new Date(endDate) >= new Date(startDate);
}

function isWithinFilingWindow(startDate: string, windowDays = 7): boolean {
  const start = new Date(startDate);
  const now = new Date();
  const diffMs = now.getTime() - start.getTime();
  const diffDays = diffMs / (1000 * 60 * 60 * 24);
  return diffDays >= 0 && diffDays <= windowDays;
}

function isEndTimeAfterStart(startTime: string, endTime: string): boolean {
  const [sh, sm] = startTime.split(':').map(Number);
  const [eh, em] = endTime.split(':').map(Number);
  return eh * 60 + em > sh * 60 + sm;
}

function roundToFifteenMinutes(minutes: number): number {
  return Math.round(minutes / 15) * 15;
}

function isValidRequestType(type: string): boolean {
  const valid = ['CORRECTION', 'PTO', 'LEAVE', 'BUSINESS_TRIP', 'OUTING'];
  return valid.includes(type);
}

function generateClientKey(): string {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

function getFilingWindowDaysRemaining(startDate: string, windowDays = 7): number {
  const start = new Date(startDate);
  const now = new Date();
  const diffDays = (now.getTime() - start.getTime()) / (1000 * 60 * 60 * 24);
  return Math.max(0, windowDays - diffDays);
}

describe('Date validation', () => {
  it('accepts ISO date format YYYY-MM-DD', () => {
    expect(isValidDateFormat('2026-04-17')).toBe(true);
  });

  it('accepts US date format MM/DD/YYYY', () => {
    expect(isValidDateFormat('04/17/2026')).toBe(true);
  });

  it('rejects empty string', () => {
    expect(isValidDateFormat('')).toBe(false);
  });

  it('rejects arbitrary text', () => {
    expect(isValidDateFormat('not-a-date')).toBe(false);
  });

  it('rejects partial date', () => {
    expect(isValidDateFormat('2026-04')).toBe(false);
  });
});

describe('Date range validation', () => {
  it('accepts end date equal to start date', () => {
    expect(isEndDateOnOrAfterStart('2026-04-17', '2026-04-17')).toBe(true);
  });

  it('accepts end date after start date', () => {
    expect(isEndDateOnOrAfterStart('2026-04-17', '2026-04-20')).toBe(true);
  });

  it('rejects end date before start date', () => {
    expect(isEndDateOnOrAfterStart('2026-04-17', '2026-04-16')).toBe(false);
  });
});

describe('Filing window validation', () => {
  it('allows filing for a date today', () => {
    const today = new Date().toISOString().split('T')[0];
    expect(isWithinFilingWindow(today)).toBe(true);
  });

  it('allows filing for a date 3 days ago', () => {
    const threeDaysAgo = new Date(Date.now() - 3 * 86400000).toISOString().split('T')[0];
    expect(isWithinFilingWindow(threeDaysAgo)).toBe(true);
  });

  it('rejects filing for a date 10 days ago', () => {
    const tenDaysAgo = new Date(Date.now() - 10 * 86400000).toISOString().split('T')[0];
    expect(isWithinFilingWindow(tenDaysAgo)).toBe(false);
  });

  it('calculates remaining filing days: today gives close to 7', () => {
    const today = new Date().toISOString().split('T')[0];
    const remaining = getFilingWindowDaysRemaining(today);
    // At most 7 days remain; actual value depends on time of day (up to ~24h variation)
    expect(remaining).toBeGreaterThanOrEqual(6);
    expect(remaining).toBeLessThanOrEqual(7);
  });
});

describe('Time window validation', () => {
  it('accepts end time after start time', () => {
    expect(isEndTimeAfterStart('09:00', '17:00')).toBe(true);
  });

  it('rejects end time equal to start time', () => {
    expect(isEndTimeAfterStart('09:00', '09:00')).toBe(false);
  });

  it('rejects end time before start time', () => {
    expect(isEndTimeAfterStart('17:00', '09:00')).toBe(false);
  });

  it('works with 15-minute increments', () => {
    expect(isEndTimeAfterStart('09:00', '09:15')).toBe(true);
    expect(isEndTimeAfterStart('09:00', '08:45')).toBe(false);
  });
});

describe('Time increment rounding', () => {
  it('rounds 7 to 0 (nearest 15)', () => {
    expect(roundToFifteenMinutes(7)).toBe(0);
  });

  it('rounds 8 to 15 (nearest 15)', () => {
    expect(roundToFifteenMinutes(8)).toBe(15);
  });

  it('rounds 30 to 30 (exactly 15*2)', () => {
    expect(roundToFifteenMinutes(30)).toBe(30);
  });

  it('rounds 45 to 45 exactly', () => {
    expect(roundToFifteenMinutes(45)).toBe(45);
  });
});

describe('Request type validation', () => {
  it('accepts CORRECTION as valid type', () => {
    expect(isValidRequestType('CORRECTION')).toBe(true);
  });

  it('accepts PTO as valid type', () => {
    expect(isValidRequestType('PTO')).toBe(true);
  });

  it('accepts LEAVE as valid type', () => {
    expect(isValidRequestType('LEAVE')).toBe(true);
  });

  it('accepts BUSINESS_TRIP as valid type', () => {
    expect(isValidRequestType('BUSINESS_TRIP')).toBe(true);
  });

  it('accepts OUTING as valid type', () => {
    expect(isValidRequestType('OUTING')).toBe(true);
  });

  it('rejects unknown type', () => {
    expect(isValidRequestType('VACATION')).toBe(false);
  });

  it('rejects empty string', () => {
    expect(isValidRequestType('')).toBe(false);
  });
});

describe('Client key generation', () => {
  it('generates a UUID-format string', () => {
    const key = generateClientKey();
    expect(key).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
  });

  it('generates unique keys each time', () => {
    const keys = new Set(Array.from({ length: 20 }, generateClientKey));
    expect(keys.size).toBe(20);
  });
});
