# Workforce & Operations Hub — Frontend

React 18 + TypeScript + Vite single-page app for the Workforce & Operations
Hub. Proxied at `/api` by nginx to the Symfony backend. Served on port 3000 in
Docker.

## Stack

- React 18, React Router 6, TanStack Query 5
- TypeScript + Vite 5
- Tailwind CSS + shadcn/ui primitives
- Axios client with CSRF + HMAC request signing interceptors
- Vitest + Testing Library

## Run

From the repo root:

```bash
docker compose up --build      # starts backend, frontend, mysql
# frontend → http://localhost:3000
# api      → http://localhost:8000
```

Standalone dev (expects a backend at `/api`):

```bash
cd frontend
npm install
npm run dev
```

## Test

```bash
# inside the test profile container
docker compose --profile test run --rm test sh -lc 'cd /app/frontend && npx vitest run'
```

The suite mixes pure-logic unit tests (`tests/unit_tests/`) with real-module
contract tests (`tests/api_tests/`) that import `src/api/*` directly and mock
axios so wiring regressions surface.

## Layout

```
src/
  api/           axios client + typed API helpers + offline queue
  components/    layout, ui primitives, attendance widgets
  context/       AuthContext
  hooks/         useAuth, useAttendance, useWorkOrders
  pages/         login, dashboard, attendance, approvals, workorders,
                 bookings, admin (users/audit/csv-import/system-config)
  types/         shared TS types
public/
  sw.js          service worker (shell + GET cache)
```

## Auth + API signing

On login the server returns a session cookie plus a `csrfToken` that the
client stores in `localStorage`. `src/api/client.ts` injects:

- `X-CSRF-Token` on every POST/PUT/PATCH/DELETE
- An HMAC-SHA256 signature on admin writes (`/admin/*`), keyed by the session
  CSRF token and covering `METHOD + path + timestamp + sha256(body)`. The
  backend `ApiSignatureAuthenticator` validates this against the same
  session-bound key.

The client also redirects to `/login` on any `401`.

## Offline-first

The app is usable through short network outages:

- **Service worker** (`public/sw.js`) caches the app shell and performs
  network-first with cache fallback for `/api/*` GETs.
- **Write queue** (`src/api/offlineQueue.ts`) persists failed POST/PUT/PATCH/
  DELETE requests in IndexedDB when `navigator.onLine` is `false`. Each entry
  stores explicit serialization metadata (`json` / `string` / `empty` /
  `unsupported`) so replay is byte-stable and never blindly re-stringifies a
  body.
- **Idempotency**: every queued write gets a deterministic `X-Idempotency-Key`
  header; JSON bodies also receive a `clientKey` field when the caller did not
  already supply one, matching the backend `IdempotencyKey` conventions used
  by booking + exception-request endpoints.
- **Bounded retry**: the queue retries up to 5 times, treats 4xx (except
  408/429) as terminal, and marks terminal items so they do not replay in a
  loop. `flushQueue()` runs automatically on the browser `online` event.
- Unsupported payloads (FormData, Blob, binary) are refused at enqueue time —
  the queue fails closed rather than corrupting the replayed request.

## Env

Vite reads `VITE_*` variables from `.env` if present, but in Docker the app is
built static and served behind nginx, which proxies `/api` to the backend
container — there is no runtime API base URL to configure.
