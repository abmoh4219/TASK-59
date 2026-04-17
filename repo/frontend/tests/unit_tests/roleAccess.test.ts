import { describe, it, expect } from 'vitest';

/**
 * Role-based access control utility tests — mirrors the sidebar
 * navigation gating and the identity access policy logic used in the UI.
 */

type Role =
  | 'ROLE_ADMIN'
  | 'ROLE_HR_ADMIN'
  | 'ROLE_SUPERVISOR'
  | 'ROLE_EMPLOYEE'
  | 'ROLE_DISPATCHER'
  | 'ROLE_TECHNICIAN';

const NAV_ITEMS: Record<string, Role[]> = {
  attendance: ['ROLE_EMPLOYEE', 'ROLE_SUPERVISOR', 'ROLE_HR_ADMIN', 'ROLE_ADMIN'],
  approvals: ['ROLE_SUPERVISOR', 'ROLE_HR_ADMIN', 'ROLE_ADMIN'],
  workOrders: ['ROLE_EMPLOYEE', 'ROLE_SUPERVISOR', 'ROLE_HR_ADMIN', 'ROLE_ADMIN', 'ROLE_DISPATCHER', 'ROLE_TECHNICIAN'],
  bookings: ['ROLE_EMPLOYEE', 'ROLE_SUPERVISOR', 'ROLE_HR_ADMIN', 'ROLE_ADMIN', 'ROLE_DISPATCHER'],
  admin: ['ROLE_ADMIN'],
  audit: ['ROLE_ADMIN', 'ROLE_HR_ADMIN'],
};

function canAccessNav(role: Role, section: string): boolean {
  return (NAV_ITEMS[section] ?? []).includes(role);
}

function canViewUserPii(actorRole: Role, targetId: number, actorId: number): boolean {
  if (actorRole === 'ROLE_HR_ADMIN' || actorRole === 'ROLE_ADMIN') return true;
  return actorId === targetId;
}

function canApproveStep(actorId: number, assignedApproverId: number): boolean {
  return actorId === assignedApproverId;
}

function isAdminRole(role: Role): boolean {
  return role === 'ROLE_ADMIN' || role === 'ROLE_HR_ADMIN';
}

function getDisplayName(role: Role): string {
  const names: Record<Role, string> = {
    ROLE_ADMIN: 'System Administrator',
    ROLE_HR_ADMIN: 'HR Admin',
    ROLE_SUPERVISOR: 'Supervisor',
    ROLE_EMPLOYEE: 'Employee',
    ROLE_DISPATCHER: 'Dispatcher',
    ROLE_TECHNICIAN: 'Technician',
  };
  return names[role] ?? role;
}

describe('Navigation access control', () => {
  it('employee can access attendance', () => {
    expect(canAccessNav('ROLE_EMPLOYEE', 'attendance')).toBe(true);
  });

  it('employee cannot access admin panel', () => {
    expect(canAccessNav('ROLE_EMPLOYEE', 'admin')).toBe(false);
  });

  it('admin can access admin panel', () => {
    expect(canAccessNav('ROLE_ADMIN', 'admin')).toBe(true);
  });

  it('employee cannot access approvals queue', () => {
    expect(canAccessNav('ROLE_EMPLOYEE', 'approvals')).toBe(false);
  });

  it('supervisor can access approvals queue', () => {
    expect(canAccessNav('ROLE_SUPERVISOR', 'approvals')).toBe(true);
  });

  it('technician can access work orders', () => {
    expect(canAccessNav('ROLE_TECHNICIAN', 'workOrders')).toBe(true);
  });

  it('dispatcher can access bookings', () => {
    expect(canAccessNav('ROLE_DISPATCHER', 'bookings')).toBe(true);
  });

  it('technician cannot access bookings', () => {
    expect(canAccessNav('ROLE_TECHNICIAN', 'bookings')).toBe(false);
  });

  it('hr admin can access audit logs', () => {
    expect(canAccessNav('ROLE_HR_ADMIN', 'audit')).toBe(true);
  });

  it('employee cannot access audit logs', () => {
    expect(canAccessNav('ROLE_EMPLOYEE', 'audit')).toBe(false);
  });
});

describe('Identity access policy', () => {
  it('HR admin can view any user PII', () => {
    expect(canViewUserPii('ROLE_HR_ADMIN', 5, 1)).toBe(true);
  });

  it('admin can view any user PII', () => {
    expect(canViewUserPii('ROLE_ADMIN', 5, 1)).toBe(true);
  });

  it('employee can view own PII', () => {
    expect(canViewUserPii('ROLE_EMPLOYEE', 5, 5)).toBe(true);
  });

  it('employee cannot view another user PII', () => {
    expect(canViewUserPii('ROLE_EMPLOYEE', 5, 6)).toBe(false);
  });

  it('supervisor cannot view subordinate PII', () => {
    expect(canViewUserPii('ROLE_SUPERVISOR', 5, 3)).toBe(false);
  });
});

describe('Approval step authorization', () => {
  it('assigned approver can approve their step', () => {
    expect(canApproveStep(10, 10)).toBe(true);
  });

  it('non-assigned user cannot approve step', () => {
    expect(canApproveStep(11, 10)).toBe(false);
  });
});

describe('Role type checks', () => {
  it('ROLE_ADMIN is admin role', () => {
    expect(isAdminRole('ROLE_ADMIN')).toBe(true);
  });

  it('ROLE_HR_ADMIN is admin role', () => {
    expect(isAdminRole('ROLE_HR_ADMIN')).toBe(true);
  });

  it('ROLE_EMPLOYEE is not admin role', () => {
    expect(isAdminRole('ROLE_EMPLOYEE')).toBe(false);
  });

  it('ROLE_SUPERVISOR is not admin role', () => {
    expect(isAdminRole('ROLE_SUPERVISOR')).toBe(false);
  });
});

describe('Role display names', () => {
  it('maps ROLE_ADMIN to System Administrator', () => {
    expect(getDisplayName('ROLE_ADMIN')).toBe('System Administrator');
  });

  it('maps ROLE_HR_ADMIN to HR Admin', () => {
    expect(getDisplayName('ROLE_HR_ADMIN')).toBe('HR Admin');
  });

  it('maps ROLE_EMPLOYEE to Employee', () => {
    expect(getDisplayName('ROLE_EMPLOYEE')).toBe('Employee');
  });

  it('maps ROLE_DISPATCHER to Dispatcher', () => {
    expect(getDisplayName('ROLE_DISPATCHER')).toBe('Dispatcher');
  });

  it('maps ROLE_TECHNICIAN to Technician', () => {
    expect(getDisplayName('ROLE_TECHNICIAN')).toBe('Technician');
  });
});
