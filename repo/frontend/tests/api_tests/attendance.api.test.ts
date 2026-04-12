import { describe, it, expect } from 'vitest';

/**
 * Attendance API contract tests — verify request shapes and response parsing.
 */

interface AttendanceCardResponse {
  recordDate: string;
  shiftStart: string | null;
  shiftEnd: string | null;
  firstPunchIn: string | null;
  lastPunchOut: string | null;
  totalMinutes: number;
  exceptions: string[];
  punches: Array<{ id: number; eventTime: string; eventType: string }>;
}

interface ExceptionRequestPayload {
  requestType: string;
  startDate: string;
  endDate: string;
  startTime?: string;
  endTime?: string;
  reason: string;
  clientKey: string;
}

interface ApprovalStep {
  id: number;
  stepNumber: number;
  status: string;
  approverName: string;
  slaDeadline: string | null;
  remainingMinutes: number;
  actedAt: string | null;
}

function buildExceptionRequestPayload(
  requestType: string,
  startDate: string,
  endDate: string,
  reason: string,
  clientKey: string,
  times?: { startTime: string; endTime: string },
): ExceptionRequestPayload {
  return {
    requestType,
    startDate,
    endDate,
    reason,
    clientKey,
    ...(times || {}),
  };
}

function parseAttendanceCard(json: AttendanceCardResponse): {
  hasExceptions: boolean;
  hours: number;
  punchCount: number;
} {
  return {
    hasExceptions: (json.exceptions || []).length > 0,
    hours: Math.floor((json.totalMinutes || 0) / 60),
    punchCount: (json.punches || []).length,
  };
}

function getCurrentStep(steps: ApprovalStep[]): ApprovalStep | null {
  return steps.find((s) => s.status === 'PENDING') || null;
}

function isWithinFilingWindow(exceptionDate: Date, filingDays: number = 7): boolean {
  const now = new Date();
  const diff = (now.getTime() - exceptionDate.getTime()) / (1000 * 60 * 60 * 24);
  return diff <= filingDays;
}

describe('Attendance API', () => {
  it('builds exception request payload with required fields', () => {
    const payload = buildExceptionRequestPayload(
      'PTO',
      '2026-04-15',
      '2026-04-15',
      'Personal day',
      'key-1',
    );
    expect(payload.requestType).toBe('PTO');
    expect(payload.startDate).toBe('2026-04-15');
    expect(payload.clientKey).toBe('key-1');
  });

  it('includes optional time window when provided', () => {
    const payload = buildExceptionRequestPayload(
      'CORRECTION',
      '2026-04-12',
      '2026-04-12',
      'Fix missed punch',
      'key-2',
      { startTime: '09:00', endTime: '17:00' },
    );
    expect(payload.startTime).toBe('09:00');
    expect(payload.endTime).toBe('17:00');
  });

  it('parses attendance card without exceptions', () => {
    const card: AttendanceCardResponse = {
      recordDate: '2026-04-12',
      shiftStart: '09:00',
      shiftEnd: '17:00',
      firstPunchIn: '09:00',
      lastPunchOut: '17:00',
      totalMinutes: 480,
      exceptions: [],
      punches: [
        { id: 1, eventTime: '09:00:00', eventType: 'IN' },
        { id: 2, eventTime: '17:00:00', eventType: 'OUT' },
      ],
    };
    const parsed = parseAttendanceCard(card);
    expect(parsed.hasExceptions).toBe(false);
    expect(parsed.hours).toBe(8);
    expect(parsed.punchCount).toBe(2);
  });

  it('parses attendance card with LATE_ARRIVAL exception', () => {
    const card: AttendanceCardResponse = {
      recordDate: '2026-04-12',
      shiftStart: '09:00',
      shiftEnd: '17:00',
      firstPunchIn: '09:12',
      lastPunchOut: '17:05',
      totalMinutes: 473,
      exceptions: ['LATE_ARRIVAL'],
      punches: [],
    };
    const parsed = parseAttendanceCard(card);
    expect(parsed.hasExceptions).toBe(true);
    expect(parsed.hours).toBe(7); // 473/60 = 7.88 → 7
  });

  it('handles empty attendance card (no record)', () => {
    const card: AttendanceCardResponse = {
      recordDate: '2026-04-13',
      shiftStart: null,
      shiftEnd: null,
      firstPunchIn: null,
      lastPunchOut: null,
      totalMinutes: 0,
      exceptions: [],
      punches: [],
    };
    const parsed = parseAttendanceCard(card);
    expect(parsed.hasExceptions).toBe(false);
    expect(parsed.hours).toBe(0);
    expect(parsed.punchCount).toBe(0);
  });

  it('finds current pending step in approval timeline', () => {
    const steps: ApprovalStep[] = [
      { id: 1, stepNumber: 1, status: 'APPROVED', approverName: 'A', slaDeadline: null, remainingMinutes: 0, actedAt: '2026-04-12T10:00:00Z' },
      { id: 2, stepNumber: 2, status: 'PENDING', approverName: 'B', slaDeadline: '2026-04-13T12:00:00Z', remainingMinutes: 1440, actedAt: null },
      { id: 3, stepNumber: 3, status: 'PENDING', approverName: 'C', slaDeadline: null, remainingMinutes: 0, actedAt: null },
    ];
    const current = getCurrentStep(steps);
    expect(current?.id).toBe(2);
    expect(current?.approverName).toBe('B');
  });

  it('returns null when no pending step exists', () => {
    const steps: ApprovalStep[] = [
      { id: 1, stepNumber: 1, status: 'APPROVED', approverName: 'A', slaDeadline: null, remainingMinutes: 0, actedAt: '2026-04-12T10:00:00Z' },
    ];
    expect(getCurrentStep(steps)).toBeNull();
  });

  it('allows filing within 7-day window', () => {
    const threeDaysAgo = new Date(Date.now() - 3 * 24 * 60 * 60 * 1000);
    expect(isWithinFilingWindow(threeDaysAgo)).toBe(true);
  });

  it('rejects filing beyond 7-day window', () => {
    const tenDaysAgo = new Date(Date.now() - 10 * 24 * 60 * 60 * 1000);
    expect(isWithinFilingWindow(tenDaysAgo)).toBe(false);
  });

  it('respects custom filing window', () => {
    const twoDaysAgo = new Date(Date.now() - 2 * 24 * 60 * 60 * 1000);
    expect(isWithinFilingWindow(twoDaysAgo, 1)).toBe(false);
    expect(isWithinFilingWindow(twoDaysAgo, 3)).toBe(true);
  });
});
