import { describe, it, expect } from 'vitest';

/**
 * Work order state machine tests — mirrors the WorkOrderService state machine
 * used on the backend and enforced in the frontend WorkOrderDetailPage.
 */

type WorkOrderStatus =
  | 'submitted'
  | 'dispatched'
  | 'accepted'
  | 'in_progress'
  | 'completed'
  | 'rated';

type Role =
  | 'ROLE_EMPLOYEE'
  | 'ROLE_DISPATCHER'
  | 'ROLE_TECHNICIAN'
  | 'ROLE_SUPERVISOR'
  | 'ROLE_HR_ADMIN'
  | 'ROLE_ADMIN';

const ALLOWED_TRANSITIONS: Record<WorkOrderStatus, WorkOrderStatus[]> = {
  submitted: ['dispatched'],
  dispatched: ['accepted'],
  accepted: ['in_progress'],
  in_progress: ['completed'],
  completed: ['rated'],
  rated: [],
};

const TRANSITION_ROLES: Partial<Record<WorkOrderStatus, Role[]>> = {
  dispatched: ['ROLE_DISPATCHER', 'ROLE_ADMIN'],
  accepted: ['ROLE_TECHNICIAN'],
  in_progress: ['ROLE_TECHNICIAN'],
  completed: ['ROLE_TECHNICIAN'],
  rated: ['ROLE_EMPLOYEE', 'ROLE_ADMIN', 'ROLE_SUPERVISOR'],
};

function canTransition(from: WorkOrderStatus, to: WorkOrderStatus): boolean {
  return (ALLOWED_TRANSITIONS[from] ?? []).includes(to);
}

function canActorTransition(role: Role, to: WorkOrderStatus): boolean {
  const allowed = TRANSITION_ROLES[to];
  if (!allowed) return false;
  return allowed.includes(role);
}

function isTerminalStatus(status: WorkOrderStatus): boolean {
  return status === 'rated';
}

function isWithinRatingWindow(completedAt: Date, windowHours = 72): boolean {
  const elapsed = (Date.now() - completedAt.getTime()) / (1000 * 60 * 60);
  return elapsed <= windowHours;
}

function getNextStatus(current: WorkOrderStatus): WorkOrderStatus | null {
  const next = ALLOWED_TRANSITIONS[current];
  return next?.length ? next[0] : null;
}

describe('State machine transitions', () => {
  it('submitted → dispatched is valid', () => {
    expect(canTransition('submitted', 'dispatched')).toBe(true);
  });

  it('dispatched → accepted is valid', () => {
    expect(canTransition('dispatched', 'accepted')).toBe(true);
  });

  it('accepted → in_progress is valid', () => {
    expect(canTransition('accepted', 'in_progress')).toBe(true);
  });

  it('in_progress → completed is valid', () => {
    expect(canTransition('in_progress', 'completed')).toBe(true);
  });

  it('completed → rated is valid', () => {
    expect(canTransition('completed', 'rated')).toBe(true);
  });

  it('submitted → completed is invalid (skip states)', () => {
    expect(canTransition('submitted', 'completed')).toBe(false);
  });

  it('rated → anything is invalid (terminal)', () => {
    expect(canTransition('rated', 'completed')).toBe(false);
    expect(canTransition('rated', 'in_progress')).toBe(false);
  });

  it('in_progress → dispatched is invalid (backward)', () => {
    expect(canTransition('in_progress', 'dispatched')).toBe(false);
  });
});

describe('Role-based transition authorization', () => {
  it('dispatcher can transition to dispatched', () => {
    expect(canActorTransition('ROLE_DISPATCHER', 'dispatched')).toBe(true);
  });

  it('employee cannot transition to dispatched', () => {
    expect(canActorTransition('ROLE_EMPLOYEE', 'dispatched')).toBe(false);
  });

  it('technician can accept', () => {
    expect(canActorTransition('ROLE_TECHNICIAN', 'accepted')).toBe(true);
  });

  it('technician can start in_progress', () => {
    expect(canActorTransition('ROLE_TECHNICIAN', 'in_progress')).toBe(true);
  });

  it('technician can complete', () => {
    expect(canActorTransition('ROLE_TECHNICIAN', 'completed')).toBe(true);
  });

  it('dispatcher cannot accept (technician-only)', () => {
    expect(canActorTransition('ROLE_DISPATCHER', 'accepted')).toBe(false);
  });

  it('employee can rate completed order', () => {
    expect(canActorTransition('ROLE_EMPLOYEE', 'rated')).toBe(true);
  });

  it('supervisor can rate completed order', () => {
    expect(canActorTransition('ROLE_SUPERVISOR', 'rated')).toBe(true);
  });
});

describe('Terminal status check', () => {
  it('rated is terminal', () => {
    expect(isTerminalStatus('rated')).toBe(true);
  });

  it('submitted is not terminal', () => {
    expect(isTerminalStatus('submitted')).toBe(false);
  });

  it('completed is not terminal (can still be rated)', () => {
    expect(isTerminalStatus('completed')).toBe(false);
  });
});

describe('Rating window', () => {
  it('accepts rating within 24 hours of completion', () => {
    const completedAt = new Date(Date.now() - 24 * 3600000);
    expect(isWithinRatingWindow(completedAt)).toBe(true);
  });

  it('accepts rating just before 72-hour deadline', () => {
    const completedAt = new Date(Date.now() - 71.9 * 3600000);
    expect(isWithinRatingWindow(completedAt)).toBe(true);
  });

  it('rejects rating after 72-hour window', () => {
    const completedAt = new Date(Date.now() - 73 * 3600000);
    expect(isWithinRatingWindow(completedAt)).toBe(false);
  });

  it('rejects rating after 100 hours', () => {
    const completedAt = new Date(Date.now() - 100 * 3600000);
    expect(isWithinRatingWindow(completedAt)).toBe(false);
  });
});

describe('Next status helper', () => {
  it('returns dispatched for submitted', () => {
    expect(getNextStatus('submitted')).toBe('dispatched');
  });

  it('returns null for rated (terminal)', () => {
    expect(getNextStatus('rated')).toBeNull();
  });

  it('returns completed for in_progress', () => {
    expect(getNextStatus('in_progress')).toBe('completed');
  });
});
