/**
 * IndexedDB-backed request queue for offline-first writes.
 *
 * Design goals:
 * - Persist enough metadata to replay each queued request *exactly* the way it
 *   was originally issued. Do not blindly JSON.stringify every body.
 * - Survive network flaps with bounded retries and a deterministic
 *   idempotency token so replays do not create duplicate writes on endpoints
 *   that support the existing `clientKey` / `X-Idempotency-Key` patterns.
 * - Fail closed on unsupported payload shapes (FormData, Blob, etc) rather
 *   than corrupting the replayed request.
 *
 * The queue is opt-in and additive — the axios response interceptor only
 * enqueues write requests that actually failed because the browser is offline.
 */

const DB_NAME = 'wfops-offline';
const STORE = 'pending';
const DB_VERSION = 2;
const MAX_ATTEMPTS = 5;

export type SerializationMode = 'json' | 'string' | 'empty' | 'unsupported';

export interface QueuedRequest {
  id?: number;
  method: string;
  url: string;
  /** Already-serialized body bytes (string form) — exactly what the fetch call will send. */
  bodyText: string;
  /** How `bodyText` was produced. 'unsupported' payloads will never replay. */
  serialization: SerializationMode;
  contentType: string;
  /** Deterministic idempotency token — passed as header and merged into JSON bodies when absent. */
  idempotencyKey: string;
  headers?: Record<string, string>;
  createdAt: number;
  attempts: number;
  lastError?: string;
  /** Once `attempts >= MAX_ATTEMPTS` the item is marked terminal and skipped by flushQueue(). */
  terminal?: boolean;
}

/** Input for enqueueing — the raw request config, not yet serialized. */
export interface EnqueueInput {
  method: string;
  url: string;
  data?: unknown;
  contentType?: string;
  headers?: Record<string, string>;
  idempotencyKey?: string;
}

function openDb(): Promise<IDBDatabase> {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, DB_VERSION);
    req.onupgradeneeded = () => {
      const db = req.result;
      if (!db.objectStoreNames.contains(STORE)) {
        db.createObjectStore(STORE, { keyPath: 'id', autoIncrement: true });
      }
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

function randomId(): string {
  // Prefer crypto.randomUUID, fall back to a best-effort id that is still
  // unique enough for replay idempotency.
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  return `qk_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 10)}`;
}

/**
 * Serialize an arbitrary axios request body into a stable string form plus a
 * mode marker. Anything we cannot faithfully replay (FormData, Blob, binary)
 * is flagged 'unsupported' so flushQueue() will skip it instead of silently
 * corrupting the request.
 */
export function serializeBody(
  data: unknown,
  contentType?: string,
): { bodyText: string; serialization: SerializationMode; contentType: string } {
  if (data === undefined || data === null || data === '') {
    return { bodyText: '', serialization: 'empty', contentType: contentType ?? 'application/json' };
  }
  if (typeof data === 'string') {
    return {
      bodyText: data,
      serialization: 'string',
      contentType: contentType ?? 'text/plain',
    };
  }
  if (typeof FormData !== 'undefined' && data instanceof FormData) {
    return { bodyText: '', serialization: 'unsupported', contentType: 'multipart/form-data' };
  }
  if (typeof Blob !== 'undefined' && data instanceof Blob) {
    return { bodyText: '', serialization: 'unsupported', contentType: data.type || 'application/octet-stream' };
  }
  if (typeof ArrayBuffer !== 'undefined' && (data instanceof ArrayBuffer || ArrayBuffer.isView(data))) {
    return { bodyText: '', serialization: 'unsupported', contentType: 'application/octet-stream' };
  }
  // Plain object / array → JSON.
  try {
    return {
      bodyText: JSON.stringify(data),
      serialization: 'json',
      contentType: contentType ?? 'application/json',
    };
  } catch {
    return { bodyText: '', serialization: 'unsupported', contentType: 'application/json' };
  }
}

/**
 * Inject the idempotency key into a JSON body when the API shape supports a
 * `clientKey` field but the caller did not already provide one. This matches
 * the existing `BookingService` / `ApprovalWorkflowService` conventions.
 */
function withClientKey(bodyText: string, serialization: SerializationMode, key: string): string {
  if (serialization !== 'json' || bodyText === '') return bodyText;
  try {
    const parsed = JSON.parse(bodyText);
    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed) && !('clientKey' in parsed)) {
      parsed.clientKey = key;
      return JSON.stringify(parsed);
    }
  } catch {
    /* fallthrough */
  }
  return bodyText;
}

export async function enqueueRequest(input: EnqueueInput): Promise<void> {
  const { bodyText, serialization, contentType } = serializeBody(input.data, input.contentType);
  if (serialization === 'unsupported') {
    // Fail closed: refuse to queue payload shapes we cannot safely replay.
    return;
  }

  const idempotencyKey = input.idempotencyKey ?? randomId();
  const finalBody = withClientKey(bodyText, serialization, idempotencyKey);

  const item: Omit<QueuedRequest, 'id'> = {
    method: input.method.toUpperCase(),
    url: input.url,
    bodyText: finalBody,
    serialization,
    contentType,
    idempotencyKey,
    headers: input.headers,
    createdAt: Date.now(),
    attempts: 0,
  };

  const db = await openDb();
  await new Promise<void>((resolve, reject) => {
    const tx = db.transaction(STORE, 'readwrite');
    tx.objectStore(STORE).add(item);
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
  });
}

export async function listPending(): Promise<QueuedRequest[]> {
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE, 'readonly');
    const req = tx.objectStore(STORE).getAll();
    req.onsuccess = () => resolve(req.result as QueuedRequest[]);
    req.onerror = () => reject(req.error);
  });
}

export async function removePending(id: number): Promise<void> {
  const db = await openDb();
  await new Promise<void>((resolve, reject) => {
    const tx = db.transaction(STORE, 'readwrite');
    tx.objectStore(STORE).delete(id);
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
  });
}

async function updatePending(item: QueuedRequest): Promise<void> {
  if (item.id === undefined) return;
  const db = await openDb();
  await new Promise<void>((resolve, reject) => {
    const tx = db.transaction(STORE, 'readwrite');
    tx.objectStore(STORE).put(item);
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
  });
}

export interface FlushResult {
  ok: number;
  failed: number;
  skipped: number;
  terminal: number;
}

/**
 * Replay every queued write. Bounded retries: an item that fails
 * `MAX_ATTEMPTS` times is flagged terminal and never retried automatically.
 * Terminal items stay in the store so they are visible/inspectable.
 */
export async function flushQueue(): Promise<FlushResult> {
  if (typeof navigator !== 'undefined' && navigator.onLine === false) {
    return { ok: 0, failed: 0, skipped: 0, terminal: 0 };
  }

  const items = await listPending();
  let ok = 0;
  let failed = 0;
  let skipped = 0;
  let terminal = 0;

  for (const item of items) {
    if (item.terminal || item.serialization === 'unsupported') {
      skipped++;
      continue;
    }
    if (item.attempts >= MAX_ATTEMPTS) {
      item.terminal = true;
      await updatePending(item);
      terminal++;
      continue;
    }

    try {
      const headers: Record<string, string> = {
        'Content-Type': item.contentType,
        'X-Idempotency-Key': item.idempotencyKey,
        ...(item.headers ?? {}),
      };

      const res = await fetch(item.url, {
        method: item.method,
        credentials: 'include',
        headers,
        body: item.serialization === 'empty' ? undefined : item.bodyText,
      });

      if (res.ok) {
        if (item.id !== undefined) await removePending(item.id);
        ok++;
        continue;
      }

      // Non-2xx: treat 4xx (except 408/429) as terminal — the server will not
      // accept this payload even after another try.
      const status = res.status;
      const retryable = status >= 500 || status === 408 || status === 429;

      item.attempts += 1;
      item.lastError = `HTTP ${status}`;
      if (!retryable || item.attempts >= MAX_ATTEMPTS) {
        item.terminal = true;
        terminal++;
      } else {
        failed++;
      }
      await updatePending(item);
    } catch (err) {
      item.attempts += 1;
      item.lastError = (err as Error)?.message ?? 'network-error';
      if (item.attempts >= MAX_ATTEMPTS) {
        item.terminal = true;
        terminal++;
      } else {
        failed++;
      }
      await updatePending(item);
    }
  }

  return { ok, failed, skipped, terminal };
}

export function registerOfflineSync(): void {
  if (typeof window === 'undefined') return;
  window.addEventListener('online', () => {
    flushQueue().catch(() => null);
  });
}
