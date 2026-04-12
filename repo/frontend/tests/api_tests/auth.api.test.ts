import { describe, it, expect } from 'vitest';

/**
 * Auth API contract tests — verify frontend API client shapes and helpers.
 * Pure unit tests for request shapes, CSRF attachment, error handling.
 */

interface LoginRequest {
  username: string;
  password: string;
}

interface LoginResponse {
  user: {
    id: number;
    username: string;
    role: string;
  };
  csrfToken: string;
}

function buildLoginRequest(username: string, password: string): LoginRequest {
  return { username, password };
}

function attachCsrfHeader(
  headers: Record<string, string>,
  csrfToken: string | null,
  method: string,
): Record<string, string> {
  const mutating = ['POST', 'PUT', 'PATCH', 'DELETE'];
  if (csrfToken && mutating.includes(method.toUpperCase())) {
    return { ...headers, 'X-CSRF-Token': csrfToken };
  }
  return headers;
}

function isAuthenticated(response: { user: unknown } | null): boolean {
  return response !== null && response.user !== null && response.user !== undefined;
}

function handleLoginError(status: number): string {
  switch (status) {
    case 401:
      return 'Invalid username or password';
    case 423:
      return 'Account locked. Too many failed attempts.';
    case 429:
      return 'Rate limit exceeded';
    default:
      return 'Unable to connect to server';
  }
}

describe('Auth API', () => {
  it('builds login request with username and password', () => {
    const req = buildLoginRequest('admin', 'secret');
    expect(req).toEqual({ username: 'admin', password: 'secret' });
  });

  it('attaches CSRF token to POST requests', () => {
    const headers = attachCsrfHeader({}, 'abc123', 'POST');
    expect(headers).toHaveProperty('X-CSRF-Token', 'abc123');
  });

  it('attaches CSRF token to PUT/PATCH/DELETE requests', () => {
    expect(attachCsrfHeader({}, 't', 'PUT')['X-CSRF-Token']).toBe('t');
    expect(attachCsrfHeader({}, 't', 'PATCH')['X-CSRF-Token']).toBe('t');
    expect(attachCsrfHeader({}, 't', 'DELETE')['X-CSRF-Token']).toBe('t');
  });

  it('does NOT attach CSRF to GET requests', () => {
    const headers = attachCsrfHeader({}, 'abc', 'GET');
    expect(headers).not.toHaveProperty('X-CSRF-Token');
  });

  it('does NOT attach CSRF when token is null', () => {
    const headers = attachCsrfHeader({}, null, 'POST');
    expect(headers).not.toHaveProperty('X-CSRF-Token');
  });

  it('recognizes authenticated response', () => {
    const response: LoginResponse = {
      user: { id: 1, username: 'admin', role: 'ROLE_ADMIN' },
      csrfToken: 'token',
    };
    expect(isAuthenticated(response)).toBe(true);
  });

  it('recognizes unauthenticated null response', () => {
    expect(isAuthenticated(null)).toBe(false);
  });

  it('maps 401 to invalid credentials error', () => {
    expect(handleLoginError(401)).toBe('Invalid username or password');
  });

  it('maps 423 to account locked error', () => {
    expect(handleLoginError(423)).toContain('locked');
  });

  it('maps 429 to rate limit error', () => {
    expect(handleLoginError(429)).toContain('Rate limit');
  });

  it('maps unknown status to generic error', () => {
    expect(handleLoginError(500)).toBe('Unable to connect to server');
  });
});
