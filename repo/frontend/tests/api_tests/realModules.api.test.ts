import { describe, it, expect, vi, beforeEach } from 'vitest';

/**
 * Tests that import the actual src/api modules so wiring regressions surface.
 * Uses vi.mock to stub axios before module evaluation.
 */

const postMock = vi.fn();
const getMock = vi.fn();
const interceptorsHandlers: Array<{ fulfilled: Function; rejected: Function }> = [];

vi.mock('axios', () => {
  const instance = {
    post: postMock,
    get: getMock,
    interceptors: {
      request: { use: (fulfilled: Function, rejected: Function) => interceptorsHandlers.push({ fulfilled, rejected }) },
      response: { use: (fulfilled: Function, rejected: Function) => interceptorsHandlers.push({ fulfilled, rejected }) },
    },
  };
  return { default: { create: vi.fn(() => instance) }, create: vi.fn(() => instance) };
});

beforeEach(() => {
  postMock.mockReset();
  getMock.mockReset();
});

describe('real api modules', () => {
  it('login() posts to /auth/login and returns the parsed body', async () => {
    const { login } = await import('../../src/api/auth');
    postMock.mockResolvedValueOnce({
      data: { user: { id: 1, username: 'admin', role: 'ROLE_ADMIN' }, csrfToken: 'abc' },
    });

    const res = await login('admin', 'pw');

    expect(postMock).toHaveBeenCalledWith('/auth/login', { username: 'admin', password: 'pw' });
    expect(res.csrfToken).toBe('abc');
    expect(res.user.role).toBe('ROLE_ADMIN');
  });

  it('getApprovalQueue() pulls /approvals/queue', async () => {
    const { getApprovalQueue } = await import('../../src/api/attendance');
    getMock.mockResolvedValueOnce({ data: [{ id: 1 }, { id: 2 }] });

    const queue = await getApprovalQueue();

    expect(getMock).toHaveBeenCalledWith('/approvals/queue');
    expect(queue).toHaveLength(2);
  });

  it('reassignStep() posts to the approver-centric endpoint', async () => {
    const { reassignStep } = await import('../../src/api/attendance');
    postMock.mockResolvedValueOnce({ data: { message: 'Reassigned' } });

    await reassignStep(42, 7, 'out-of-office');

    expect(postMock).toHaveBeenCalledWith('/approvals/42/reassign', {
      newApproverId: 7,
      reason: 'out-of-office',
    });
  });

  it('reassignRequestApprover() routes to the request-scoped owner endpoint', async () => {
    const { reassignRequestApprover } = await import('../../src/api/attendance');
    postMock.mockResolvedValueOnce({ data: { message: 'Reassigned successfully' } });

    await reassignRequestApprover(15, 9, 'approver out');

    expect(postMock).toHaveBeenCalledWith('/requests/15/reassign', {
      newApproverId: 9,
      reason: 'approver out',
    });
  });

  it('auth api getMe() hits /auth/me and returns the typed payload', async () => {
    const { getMe } = await import('../../src/api/auth');
    getMock.mockResolvedValueOnce({
      data: {
        user: { id: 2, username: 'employee', role: 'ROLE_EMPLOYEE' },
        csrfToken: 'xyz',
      },
    });

    const res = await getMe();
    expect(getMock).toHaveBeenCalledWith('/auth/me');
    expect(res.user.username).toBe('employee');
    expect(res.csrfToken).toBe('xyz');
  });

  it('getHintText() from policyHintText.ts uses live tolerance values', async () => {
    const mod = await import('../../src/components/attendance/policyHintText');
    const hint = mod.getHintText('LATE_ARRIVAL', [
      {
        ruleType: 'LATE_ARRIVAL',
        toleranceMinutes: 8,
        missedPunchWindowMinutes: 30,
        filingWindowDays: 7,
      },
    ]);
    expect(hint).toContain('8 minutes tolerance');
  });

  it('offlineQueue exports the expected surface', async () => {
    const mod = await import('../../src/api/offlineQueue');
    expect(typeof mod.enqueueRequest).toBe('function');
    expect(typeof mod.flushQueue).toBe('function');
    expect(typeof mod.registerOfflineSync).toBe('function');
    expect(typeof mod.serializeBody).toBe('function');
  });

  it('serializeBody marks JSON objects as json and keeps body text stable', async () => {
    const { serializeBody } = await import('../../src/api/offlineQueue');
    const result = serializeBody({ foo: 1, bar: 'x' });
    expect(result.serialization).toBe('json');
    expect(result.contentType).toBe('application/json');
    expect(JSON.parse(result.bodyText)).toEqual({ foo: 1, bar: 'x' });
  });

  it('serializeBody preserves pre-serialized strings without re-stringifying', async () => {
    const { serializeBody } = await import('../../src/api/offlineQueue');
    const raw = '{"already":"serialized"}';
    const result = serializeBody(raw);
    expect(result.serialization).toBe('string');
    expect(result.bodyText).toBe(raw);
  });

  it('serializeBody flags FormData bodies as unsupported', async () => {
    const { serializeBody } = await import('../../src/api/offlineQueue');
    // Minimal shim: FormData is the browser global; fabricate the instance
    // check here for Node test envs that lack it.
    const fd = typeof FormData !== 'undefined' ? new FormData() : undefined;
    if (fd === undefined) {
      return;
    }
    fd.append('file', 'x');
    const result = serializeBody(fd);
    expect(result.serialization).toBe('unsupported');
  });

  it('serializeBody treats empty bodies as empty (not "null")', async () => {
    const { serializeBody } = await import('../../src/api/offlineQueue');
    expect(serializeBody(undefined).serialization).toBe('empty');
    expect(serializeBody(null).serialization).toBe('empty');
    expect(serializeBody('').serialization).toBe('empty');
  });
});
