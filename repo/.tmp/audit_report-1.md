# Static Delivery Acceptance & Architecture Audit (Fresh Re-Review)

Date: 2026-04-13  
Mode: **Static-only** (no runtime/test/docker execution)

## 1) Verdict

- **Overall conclusion: Partial Pass (still not full acceptance)**

### What improved since the prior report

- Privileged write signature enforcement is now active for `/api/admin` mutating requests.
- Lockout/account-status checking is now wired into the main firewall via `user_checker`.
- Reassignment authorization is materially hardened in `ApprovalWorkflowService::reassign()`.
- Work-order photo endpoint now mirrors detail-level role/object checks.
- Admin CSV import HTTP endpoint now exists and matches frontend intent.
- Booking detail access now includes object-level checks for requester/traveler/admin/HR.

### Why full acceptance is still withheld

- Frontend reassignment UX wiring remains incomplete (dead link condition + missing route target).
- Offline-first implementation exists, but replay fidelity and conflict robustness are still weak for production-grade guarantees.
- Documentation quality (especially frontend README) remains template-level and not project-verifiable.

## 2) Scope and Verification Boundary

### Reviewed (static)

- Backend security/config/listeners/controllers/services/tests
- Frontend routing/api/offline/service worker/tests/docs
- Existing audit artifact and updated remediation points

### Not executed

- Docker, app startup, migrations, PHPUnit, Vitest, browser UI runs

### Manual verification still required

- Runtime correctness of offline replay behavior across payload types
- Browser-level UX correctness for reassignment flow and offline transitions
- Operational behavior under real network interruptions and concurrent actors

## 3) Requirement Mapping Snapshot

### Attendance + approvals + timeline

- **Status:** Mostly implemented
- **Evidence:** `backend/src/Controller/AttendanceController.php`, `backend/src/Controller/ApprovalController.php`, `frontend/src/pages/attendance/RequestDetailPage.tsx`

### Facilities work orders + photo handling

- **Status:** Implemented with improved authorization parity
- **Evidence:** `backend/src/Controller/WorkOrderController.php:224`

### Booking/idempotency/allocation behavior

- **Status:** Implemented, including merge path
- **Evidence:** `backend/src/Service/BookingService.php:22`, `backend/src/Service/BookingService.php:127`

### Security controls (CSRF/rate-limit/audit/signature/lockout)

- **Status:** Substantially improved
- **Evidence:**
  - `backend/config/packages/security.yaml:16` (user checker wired)
  - `backend/src/Security/UserChecker.php:14`
  - `backend/src/EventListener/ApiSignatureListener.php:15`
  - `backend/src/Security/ApiSignatureAuthenticator.php`

### Offline-first

- **Status:** Added, but still maturing
- **Evidence:** `frontend/src/main.tsx:10`, `frontend/public/sw.js:1`, `frontend/src/api/offlineQueue.ts:1`

## 4) Section-by-Section Acceptance

### A. Hard Gates

#### A1) Security gate

- **Result:** **Pass (improved from Fail)**
- **Reasoning:**
  - Admin write signature enforcement now runs on request event for `/api/admin` non-safe methods.
  - Lockout/account status checks are integrated pre-auth.
  - Reassign privilege checks now include actor and target-role constraints.
- **Evidence:**
  - `backend/src/EventListener/ApiSignatureListener.php:26`
  - `backend/src/Security/UserChecker.php:17`
  - `backend/src/Service/ApprovalWorkflowService.php:304`

#### A2) Requirement-fit gate

- **Result:** **Partial Pass**
- **Reasoning:** core domains are covered, but offline robustness + reassignment UX completeness are not yet fully production-ready.

### B. Delivery Completeness

- **Result:** **Partial Pass**
- **Strengths:** full-stack flows exist across attendance/work-orders/bookings/admin operations.
- **Remaining gaps:** frontend reassignment route/trigger completeness; docs quality.
- **Evidence:**
  - `frontend/src/App.tsx:42`
  - `frontend/src/pages/attendance/RequestDetailPage.tsx:228`
  - `frontend/src/pages/attendance/RequestDetailPage.tsx:281`
  - `frontend/README.md:1`

### C. Engineering and Architecture Quality

- **Result:** **Pass**
- **Reasoning:** module decomposition remains strong and remediations were implemented in the correct layers (listener/checker/service-level authorization).
- **Evidence:** `backend/src/EventListener/`, `backend/src/Security/`, `backend/src/Service/`, `frontend/src/api/`

### D. Professionalism (errors/validation/ops-readiness)

- **Result:** **Partial Pass**
- **Reasoning:** validation and hardening improved, but offline replay currently makes assumptions about payload encoding and conflict handling.
- **Evidence:**
  - `frontend/src/api/client.ts:83` (queues `cfg.data`)
  - `frontend/src/api/offlineQueue.ts:74` (always `JSON.stringify(item.body)`)

### E. Prompt Understanding and Constraint Fit

- **Result:** **Partial Pass**
- **Reasoning:** major constraints are now addressed in code, but a few implementation details still limit end-to-end fit.

### F. Frontend UX/Aesthetics (static only)

- **Result:** **Cannot fully verify statically**
- **Reasoning:** component structure appears consistent, but rendering/interactions were not executed.

## 5) Findings (Updated Severity)

### High

1. **Reassignment frontend path remains functionally incomplete**

- **Evidence:**
  - `frontend/src/pages/attendance/RequestDetailPage.tsx:228` (`approverIsOut` hardcoded false)
  - `frontend/src/pages/attendance/RequestDetailPage.tsx:281` (link to `/approvals/reassign/:id`)
  - `frontend/src/App.tsx:42` (no matching `/approvals/reassign/:id` route)
- **Impact:** reassignment UI affordance is effectively unreachable/misaligned despite backend support.
- **Minimum fix:** expose approver out-of-office state in API, remove hardcoded false, and register a concrete route/page (or modal flow).

### Medium

2. **Offline replay payload handling is fragile for non-uniform body formats**

- **Evidence:**
  - Queue stores `cfg.data` directly: `frontend/src/api/client.ts:83`
  - Replay always JSON-stringifies body: `frontend/src/api/offlineQueue.ts:74`
- **Impact:** risk of malformed replay for already-serialized strings or non-JSON bodies; potential duplicate/failed writes under poor connectivity.
- **Minimum fix:** persist explicit content-type + serialization metadata and replay accordingly; skip/branch FormData and string payloads safely.

3. **Offline-first behavior lacks conflict/idempotency replay safeguards on frontend**

- **Evidence:** `frontend/src/api/offlineQueue.ts:62` (simple linear replay), no client replay idempotency keys attached in queue path.
- **Impact:** network flaps may cause duplicate mutation attempts if backend endpoint lacks idempotent semantics.
- **Minimum fix:** ensure queued writes include deterministic idempotency tokens where supported; track replay attempts and terminal failures.

4. **Frontend project documentation remains template-level**

- **Evidence:** `frontend/README.md:1`
- **Impact:** weak operational verifiability and onboarding clarity.
- **Minimum fix:** replace with project-specific architecture/run/test/env/offline notes.

### Low

5. **Signature protection test depth is improved but still narrow at API-layer integration level**

- **Evidence:** `backend/tests/unit_tests/ApiSignatureAuthenticatorTest.php:1` (unit-level coverage exists)
- **Impact:** good unit confidence, but no confirmed end-to-end API tests asserting listener enforcement across real admin endpoints.
- **Minimum fix:** add backend API tests for `/api/admin/*` writes with missing/invalid/valid signatures.

## 6) Security Review Summary (Post-Remediation)

- **Authentication entry points:** **Pass**  
  Evidence: `backend/config/packages/security.yaml:16`, `backend/src/Security/UserChecker.php:17`

- **Route-level authorization:** **Pass**  
  Evidence: `backend/config/packages/security.yaml:28`, `backend/src/EventListener/ApiSignatureListener.php:29`

- **Object-level authorization:** **Partial Pass**  
  Evidence: `backend/src/Controller/BookingController.php:143`, `backend/src/Controller/WorkOrderController.php:241`

- **Function-level authorization:** **Pass** (materially improved)  
  Evidence: `backend/src/Service/ApprovalWorkflowService.php:304`

- **Admin/internal protection:** **Pass**  
  Evidence: `backend/src/EventListener/ApiSignatureListener.php:37`, `backend/tests/unit_tests/ApiSignatureAuthenticatorTest.php:37`

## 7) Tests and Logging Snapshot (Static)

- **Backend unit tests:** improved for remediated risk (`ReassignAuthorizationTest`, `ApiSignatureAuthenticatorTest`).
- **Backend API tests:** present, but signature-listener integration assertions should be added.
- **Frontend tests:** module-import tests improved (`frontend/tests/api_tests/realModules.api.test.ts`), but offline replay edge cases are still not deeply tested.
- **Audit logging:** still solidly implemented in backend services/controllers.

## 8) Delta vs Previous Audit

### Downgraded / closed prior high-risk findings

- API signature “implemented but unused” → **closed** (listener now enforces).
- Lockout bypass concern → **downgraded/mostly closed** (user checker wired + test strengthened).
- Reassign privilege escalation risk → **downgraded** (service-level authorization added + unit tests).
- Work-order photo authorization parity gap → **closed**.
- Missing admin CSV import endpoint → **closed**.

### Remaining material gaps

- Reassignment UX path completeness on frontend.
- Offline replay robustness and conflict/idempotency strategy.
- Frontend docs quality.

## 9) Final Acceptance Recommendation

- **Recommendation:** **Do not mark final acceptance yet**.
- **Reason:** Security blockers from the previous report were substantially remediated, but delivery is still short of full product-ready acceptance due to unresolved UX/offline robustness/documentation gaps.
- **Fastest path to full acceptance:**
  1. finalize reassignment route/UI wiring,
  2. harden offline replay serialization/idempotency behavior,
  3. replace frontend template README with project-specific operational docs.
