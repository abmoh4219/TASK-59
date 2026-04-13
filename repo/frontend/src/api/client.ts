import axios from 'axios';
import { enqueueRequest } from './offlineQueue';

const apiClient = axios.create({
  baseURL: '/api',
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
});

async function hmacSha256Hex(key: string, message: string): Promise<string> {
  const enc = new TextEncoder();
  const cryptoKey = await crypto.subtle.importKey(
    'raw',
    enc.encode(key),
    { name: 'HMAC', hash: 'SHA-256' },
    false,
    ['sign'],
  );
  const sig = await crypto.subtle.sign('HMAC', cryptoKey, enc.encode(message));
  return Array.from(new Uint8Array(sig))
    .map((b) => b.toString(16).padStart(2, '0'))
    .join('');
}

async function sha256Hex(message: string): Promise<string> {
  const enc = new TextEncoder();
  const buf = await crypto.subtle.digest('SHA-256', enc.encode(message));
  return Array.from(new Uint8Array(buf))
    .map((b) => b.toString(16).padStart(2, '0'))
    .join('');
}

// Request interceptor: attach CSRF token, and HMAC signature for admin writes
apiClient.interceptors.request.use(async (config) => {
  const csrfToken = localStorage.getItem('csrf_token');
  const method = (config.method ?? 'get').toLowerCase();
  const isWrite = ['post', 'put', 'patch', 'delete'].includes(method);

  if (csrfToken && isWrite) {
    config.headers['X-CSRF-Token'] = csrfToken;
  }

  // Sign privileged writes with HMAC-SHA256 keyed by the session CSRF token.
  // The server validates the same set of routes in ApiSignatureListener:
  // admin writes, approval decisions, requester reassignment, and work-order
  // status transitions. Ordinary writes remain CSRF-only.
  const url = config.url ?? '';
  const strippedUrl = url.split('?')[0] || '';
  const PRIVILEGED_PATTERNS: RegExp[] = [
    /^\/admin(\/|$)/,
    /^\/approvals\/\d+\/(approve|reject|reassign)$/,
    /^\/requests\/\d+\/reassign$/,
    /^\/work-orders\/\d+\/status$/,
  ];
  const isPrivilegedWrite =
    isWrite && PRIVILEGED_PATTERNS.some((re) => re.test(strippedUrl));
  if (isPrivilegedWrite && csrfToken) {
    const timestamp = Math.floor(Date.now() / 1000).toString();
    const path = '/api' + (url.split('?')[0] || '');
    let bodyStr = '';
    if (config.data !== undefined && config.data !== null) {
      if (typeof config.data === 'string') {
        bodyStr = config.data;
      } else if (config.data instanceof FormData) {
        // FormData bodies are not signed (multipart); use empty body hash.
        bodyStr = '';
      } else {
        bodyStr = JSON.stringify(config.data);
      }
    }
    const bodyHash = await sha256Hex(bodyStr);
    const payload = method.toUpperCase() + path + timestamp + bodyHash;
    const signature = await hmacSha256Hex(csrfToken, payload);
    config.headers['X-Api-Signature'] = signature;
    config.headers['X-Timestamp'] = timestamp;
  }

  return config;
});

// Response interceptor: redirect to /login on 401, queue writes when offline.
apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('csrf_token');
      if (window.location.pathname !== '/login') {
        window.location.href = '/login';
      }
    }

    // If the network is unreachable on a state-mutating request, persist it
    // to the offline queue so it can replay once the user is back online.
    // The queue persists explicit serialization metadata + an idempotency key
    // so replay is safe for mixed payload types and flaky connections.
    const cfg = error.config;
    const method = (cfg?.method ?? 'get').toLowerCase();
    const isWrite = ['post', 'put', 'patch', 'delete'].includes(method);
    if (isWrite && !error.response && typeof navigator !== 'undefined' && navigator.onLine === false) {
      try {
        const contentType =
          (cfg.headers && (cfg.headers['Content-Type'] || cfg.headers['content-type'])) ||
          undefined;
        await enqueueRequest({
          method: method.toUpperCase(),
          url: '/api' + (cfg.url ?? ''),
          data: cfg.data,
          contentType: typeof contentType === 'string' ? contentType : undefined,
        });
      } catch {
        /* no-op */
      }
    }

    return Promise.reject(error);
  }
);

export default apiClient;
