import { describe, it, expect } from 'vitest';

// ---------------------------------------------------------------------------
// Inline utilities — mirrors logic used in ExceptionRequestForm.tsx
// ---------------------------------------------------------------------------

function generate15MinIncrements(): string[] {
  const times: string[] = [];
  for (let h = 0; h < 24; h++) {
    for (let m = 0; m < 60; m += 15) {
      times.push(`${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}`);
    }
  }
  return times;
}

function isValidMmDdYyyy(dateStr: string): boolean {
  return /^\d{2}\/\d{2}\/\d{4}$/.test(dateStr);
}

function validateStartEnd(start: string, end: string): boolean {
  return start < end;
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('timeIncrement — 15-minute increment generation', () => {
  it('generates exactly 96 time slots (24 hours × 4 slots)', () => {
    const times = generate15MinIncrements();
    expect(times).toHaveLength(96);
  });

  it('first slot is 00:00', () => {
    const times = generate15MinIncrements();
    expect(times[0]).toBe('00:00');
  });

  it('last slot is 23:45', () => {
    const times = generate15MinIncrements();
    expect(times[times.length - 1]).toBe('23:45');
  });

  it('includes 09:15', () => {
    const times = generate15MinIncrements();
    expect(times).toContain('09:15');
  });

  it('includes every expected quarter-hour boundary', () => {
    const times = generate15MinIncrements();
    expect(times).toContain('00:00');
    expect(times).toContain('00:15');
    expect(times).toContain('00:30');
    expect(times).toContain('00:45');
    expect(times).toContain('12:00');
    expect(times).toContain('23:30');
    expect(times).toContain('23:45');
  });

  it('does not include half-hour-only slots like 01:20', () => {
    const times = generate15MinIncrements();
    expect(times).not.toContain('01:20');
    expect(times).not.toContain('08:05');
    expect(times).not.toContain('23:59');
  });

  it('all entries are zero-padded HH:MM strings', () => {
    const times = generate15MinIncrements();
    const pattern = /^\d{2}:\d{2}$/;
    for (const t of times) {
      expect(t).toMatch(pattern);
    }
  });
});

describe('timeIncrement — start/end validation', () => {
  it('returns true when start is before end', () => {
    expect(validateStartEnd('09:00', '17:00')).toBe(true);
    expect(validateStartEnd('00:00', '23:45')).toBe(true);
    expect(validateStartEnd('08:30', '08:45')).toBe(true);
  });

  it('returns false when start is after end', () => {
    expect(validateStartEnd('17:00', '09:00')).toBe(false);
    expect(validateStartEnd('23:45', '00:00')).toBe(false);
  });

  it('returns false when start equals end', () => {
    // String comparison: '09:00' < '09:00' is false
    expect(validateStartEnd('09:00', '09:00')).toBe(false);
  });
});

describe('timeIncrement — MM/DD/YYYY date format validation', () => {
  it('accepts a correctly formatted date', () => {
    expect(isValidMmDdYyyy('04/12/2026')).toBe(true);
  });

  it('rejects ISO format (YYYY-MM-DD)', () => {
    expect(isValidMmDdYyyy('2026-04-12')).toBe(false);
  });

  it('accepts a date that passes the regex pattern even with month 13 (regex-only check)', () => {
    // The regex only validates the pattern shape, not calendar validity
    expect(isValidMmDdYyyy('13/01/2026')).toBe(true);
  });

  it('rejects incomplete date strings', () => {
    expect(isValidMmDdYyyy('4/12/2026')).toBe(false);   // single-digit month
    expect(isValidMmDdYyyy('04/12/26')).toBe(false);     // 2-digit year
    expect(isValidMmDdYyyy('04-12-2026')).toBe(false);   // wrong separator
    expect(isValidMmDdYyyy('')).toBe(false);
  });

  it('rejects strings with extra characters', () => {
    expect(isValidMmDdYyyy('04/12/2026 ')).toBe(false);
    expect(isValidMmDdYyyy(' 04/12/2026')).toBe(false);
  });
});
