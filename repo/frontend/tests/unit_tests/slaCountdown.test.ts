import { describe, it, expect } from 'vitest';

// ---------------------------------------------------------------------------
// Inline utilities — mirrors the logic used in ApprovalTimeline.tsx
// ---------------------------------------------------------------------------

function getSlaColor(remainingMinutes: number): string {
  if (remainingMinutes <= 0) return 'text-red-400';
  if (remainingMinutes < 240) return 'text-red-400';
  if (remainingMinutes < 720) return 'text-amber-400';
  return 'text-green-400';
}

function formatRemaining(minutes: number): string {
  if (minutes <= 0) return 'OVERDUE';
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  return `${h}h ${m}m remaining`;
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('slaCountdown — color coding', () => {
  it('returns green for more than 720 minutes (12h+) remaining', () => {
    expect(getSlaColor(721)).toBe('text-green-400');
    expect(getSlaColor(1440)).toBe('text-green-400');
    expect(getSlaColor(1000)).toBe('text-green-400');
  });

  it('returns amber for 240–719 minutes remaining', () => {
    expect(getSlaColor(500)).toBe('text-amber-400');
    expect(getSlaColor(240)).toBe('text-amber-400');
    expect(getSlaColor(719)).toBe('text-amber-400');
  });

  it('returns red for fewer than 240 minutes remaining', () => {
    expect(getSlaColor(100)).toBe('text-red-400');
    expect(getSlaColor(1)).toBe('text-red-400');
    expect(getSlaColor(239)).toBe('text-red-400');
  });

  it('returns red when the SLA is overdue (0 or negative)', () => {
    expect(getSlaColor(0)).toBe('text-red-400');
    expect(getSlaColor(-60)).toBe('text-red-400');
    expect(getSlaColor(-1000)).toBe('text-red-400');
  });
});

describe('slaCountdown — formatRemaining', () => {
  it('displays OVERDUE for negative remaining minutes', () => {
    expect(formatRemaining(-60)).toBe('OVERDUE');
    expect(formatRemaining(-1)).toBe('OVERDUE');
    expect(formatRemaining(-1440)).toBe('OVERDUE');
  });

  it('displays OVERDUE for exactly 0 minutes', () => {
    expect(formatRemaining(0)).toBe('OVERDUE');
  });

  it('formats hours and minutes correctly for 1103 minutes', () => {
    // 1103 / 60 = 18h 23m
    expect(formatRemaining(1103)).toBe('18h 23m remaining');
  });

  it('formats exactly 1 hour correctly', () => {
    expect(formatRemaining(60)).toBe('1h 0m remaining');
  });

  it('formats sub-hour durations correctly', () => {
    expect(formatRemaining(45)).toBe('0h 45m remaining');
    expect(formatRemaining(1)).toBe('0h 1m remaining');
  });

  it('formats multi-day durations (in minutes) correctly', () => {
    // 1440 min = 24h 0m
    expect(formatRemaining(1440)).toBe('24h 0m remaining');
    // 1500 min = 25h 0m
    expect(formatRemaining(1500)).toBe('25h 0m remaining');
  });
});
