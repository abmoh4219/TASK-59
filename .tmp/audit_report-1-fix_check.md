# Fix Check Report — `audit_report-1` Findings

Date: 2026-04-13  
Mode: **Static-only re-check** (no runtime/test execution in this pass)

## Summary

All 5 previously listed findings appear to be addressed in the current codebase.

- **Fixed:** 5
- **Partially fixed:** 0
- **Open:** 0

## Per-Finding Validation

### 1) High — Reassignment frontend path functionally incomplete

**Previous issue:** hardcoded `approverIsOut`, dead `/approvals/reassign/:id` link, no route.

**Current evidence:**

- `frontend/src/pages/attendance/RequestDetailPage.tsx` now derives status from API:
  - `const approverIsOut = Boolean(pendingStep?.approverIsOut);`
- Reassign action now uses an in-page modal flow instead of dead route:
  - `showReassign` state + `<ReassignModal ... />`
  - button triggers `setShowReassign(true)`
- Backend now exposes `approverIsOut` in serialized step payload:
  - `backend/src/Controller/ExceptionRequestController.php` includes
    `'approverIsOut' => $step->getApprover()->isOut(),`

**Assessment:** ✅ **Fixed**

---

### 2) Medium — Offline replay payload handling fragile

**Previous issue:** queue stored raw `cfg.data`, replay always `JSON.stringify(...)`.

**Current evidence:**

- `frontend/src/api/client.ts` now sends structured enqueue input:
  - `data`, `contentType` passed into `enqueueRequest(...)`
- `frontend/src/api/offlineQueue.ts` now serializes explicitly with modes:
  - `serializeBody()` supports `json | string | empty | unsupported`
  - stores `bodyText`, `serialization`, `contentType`
  - replay sends exact `bodyText` (or `undefined` for empty), not blind stringify
  - unsupported payload types (FormData/Blob/binary) are rejected (fail-closed)

**Assessment:** ✅ **Fixed**

---

### 3) Medium — Offline replay lacks conflict/idempotency safeguards

**Previous issue:** simple replay loop with no idempotency protection.

**Current evidence:**

- `frontend/src/api/offlineQueue.ts` now includes:
  - deterministic `idempotencyKey` per queued item
  - replay header `X-Idempotency-Key`
  - JSON body augmentation with `clientKey` when missing
  - bounded retries (`MAX_ATTEMPTS = 5`)
  - terminal-state handling (`terminal` flag) and retryability rules

**Assessment:** ✅ **Fixed**

---

### 4) Medium — Frontend README template-level

**Previous issue:** default Vite template README.

**Current evidence:**

- `frontend/README.md` now project-specific with:
  - stack description
  - run/test instructions
  - folder layout
  - auth/signing flow
  - offline-first design notes

**Assessment:** ✅ **Fixed**

---

### 5) Low — Signature test depth narrow at API integration layer

**Previous issue:** only unit-level signature tests were evidenced.

**Current evidence:**

- New integration test file exists:
  - `backend/tests/api_tests/AdminSignatureApiTest.php`
- Covers listener behavior on real admin endpoints:
  - missing signature → rejected
  - malformed signature → rejected
  - invalid signature key → rejected
  - valid signature → accepted
  - admin GET read path remains accessible without signature

**Assessment:** ✅ **Fixed**

## Final Result

Based on this static re-check, the previously reported 5 findings in `audit_report-1` are now resolved in code.

> Note: This conclusion is static-only. Full confidence still benefits from executing backend/frontend test suites and a brief UI smoke test for reassignment + offline replay paths.
