# Test Coverage Audit

## Scope, Method, and Constraints

- Audit mode: **static inspection only** (no runtime execution performed).
- Inspected artifacts only required for scope:
  - Routes/controllers: `repo/backend/src/Controller/*.php`, `repo/backend/config/routes.yaml`
  - Tests: `repo/backend/tests/api_tests/*.php`, `repo/backend/tests/unit_tests/*.php`, `repo/frontend/tests/unit_tests/*.test.ts*`, `repo/frontend/tests/e2e/*.spec.ts`
  - README and test runner: `repo/README.md`, `repo/run_tests.sh`
- Route loading evidence: `repo/backend/config/routes.yaml` (`controllers` resource points to `src/Controller` with `type: attribute`).

## Project Type Detection

- README top declaration: `# fullstack` at `repo/README.md:1`.
- Final detected type: **fullstack** (explicit, not inferred).

## Backend Endpoint Inventory

Resolved from Symfony attribute routes (controller prefixes + method-level routes):

1. `GET /api/health`
2. `POST /api/auth/login`
3. `POST /api/auth/logout`
4. `GET /api/auth/me`
5. `POST /api/auth/me/deletion-request`
6. `GET /api/auth/me/deletion-request`
7. `GET /api/auth/csrf-token`
8. `GET /api/approvals/queue`
9. `POST /api/approvals/{stepId}/approve`
10. `POST /api/approvals/{stepId}/reject`
11. `POST /api/approvals/{stepId}/reassign`
12. `POST /api/requests`
13. `GET /api/requests`
14. `GET /api/requests/{id}`
15. `POST /api/requests/{id}/withdraw`
16. `POST /api/requests/{id}/reassign`
17. `GET /api/audit/logs`
18. `GET /api/resources`
19. `GET /api/resources/{id}/availability`
20. `POST /api/bookings`
21. `GET /api/bookings`
22. `GET /api/bookings/{id}`
23. `DELETE /api/bookings/{id}`
24. `GET /api/attendance/today`
25. `GET /api/attendance/{date}`
26. `GET /api/attendance/history`
27. `GET /api/attendance/rules`
28. `GET /api/admin/health`
29. `GET /api/admin/users`
30. `POST /api/admin/users`
31. `PUT /api/admin/users/{id}`
32. `GET /api/admin/deletion-requests`
33. `POST /api/admin/users/{id}/delete-data`
34. `GET /api/admin/config`
35. `PUT /api/admin/config`
36. `POST /api/admin/attendance/import`
37. `GET /api/admin/anomaly-alerts`
38. `GET /api/work-orders/technicians`
39. `POST /api/work-orders`
40. `GET /api/work-orders`
41. `GET /api/work-orders/{id}`
42. `PATCH /api/work-orders/{id}/status`
43. `POST /api/work-orders/{id}/rate`
44. `GET /api/work-orders/{id}/photos/{photoId}`

Primary route evidence files:

- `repo/backend/src/Controller/AuthController.php`
- `repo/backend/src/Controller/ApprovalController.php`
- `repo/backend/src/Controller/ExceptionRequestController.php`
- `repo/backend/src/Controller/AuditController.php`
- `repo/backend/src/Controller/BookingController.php`
- `repo/backend/src/Controller/AttendanceController.php`
- `repo/backend/src/Controller/AdminController.php`
- `repo/backend/src/Controller/WorkOrderController.php`
- `repo/backend/src/Controller/HealthController.php`

## API Test Mapping Table

Legend for test type:

- **True no-mock HTTP** = `WebTestCase` + real request calls + no HTTP-layer mocks in file.
- **Uncovered** = no test issuing exact `METHOD + PATH`.

| Endpoint                                     | Covered | Test type         | Test files                                                                                                                          | Evidence (function refs)                                                             |
| -------------------------------------------- | ------- | ----------------- | ----------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| `GET /api/health`                            | Yes     | True no-mock HTTP | `AuthApiTest.php`, `AttendanceApiTest.php`, `ApprovalApiTest.php`, `AuditApiTest.php`, `BookingApiTest.php`, `WorkOrderApiTest.php` | `testHealthEndpoint`, `testHealthCheck`                                              |
| `POST /api/auth/login`                       | Yes     | True no-mock HTTP | `AuthApiTest.php` (+ login helpers across API tests)                                                                                | `testLoginSuccess`, helper `loginAs`                                                 |
| `POST /api/auth/logout`                      | Yes     | True no-mock HTTP | `AuthApiTest.php`                                                                                                                   | `testCsrfMissingReturns403`                                                          |
| `GET /api/auth/me`                           | Yes     | True no-mock HTTP | `AuthApiTest.php`, `AuditApiTest.php`, `AdminSignatureApiTest.php`                                                                  | `testRateLimitConfigIsEnforced`, `testPhoneMaskedForSystemAdmin`                     |
| `POST /api/auth/me/deletion-request`         | Yes     | True no-mock HTTP | `AuthApiTest.php`                                                                                                                   | `testUserInitiatedDeletionRequestFlow`                                               |
| `GET /api/auth/me/deletion-request`          | Yes     | True no-mock HTTP | `AuthApiTest.php`                                                                                                                   | `testUserInitiatedDeletionRequestFlow`                                               |
| `GET /api/auth/csrf-token`                   | Yes     | True no-mock HTTP | `AuthApiTest.php`                                                                                                                   | `testCsrfTokenEndpoint`                                                              |
| `GET /api/approvals/queue`                   | Yes     | True no-mock HTTP | `ApprovalApiTest.php`                                                                                                               | `testApprovalQueueReturnsData`, `testSupervisorApproveStep1`                         |
| `POST /api/approvals/{stepId}/approve`       | Yes     | True no-mock HTTP | `ApprovalApiTest.php`                                                                                                               | `testSupervisorApproveStep1`, `testEmployeeCannotApproveStep`                        |
| `POST /api/approvals/{stepId}/reject`        | Yes     | True no-mock HTTP | `ApprovalApiTest.php`                                                                                                               | `testSupervisorRejectStep1`                                                          |
| `POST /api/approvals/{stepId}/reassign`      | Yes     | True no-mock HTTP | `ApprovalApiTest.php`                                                                                                               | `testAdminReassignsApprovalStep`                                                     |
| `POST /api/requests`                         | Yes     | True no-mock HTTP | `ExceptionRequestApiTest.php`, `AuthApiTest.php`, `ApprovalApiTest.php`                                                             | `testCreateRequestSuccess`, `testExceptionRequestInvalidDateReturns400`              |
| `GET /api/requests`                          | Yes     | True no-mock HTTP | `ExceptionRequestApiTest.php`                                                                                                       | `testListOwnRequests`                                                                |
| `GET /api/requests/{id}`                     | Yes     | True no-mock HTTP | `ExceptionRequestApiTest.php`                                                                                                       | `testEmployeeCannotSeeOtherRequests`                                                 |
| `POST /api/requests/{id}/withdraw`           | Yes     | True no-mock HTTP | `ExceptionRequestApiTest.php`                                                                                                       | `testWithdrawBeforeFirstApproval`                                                    |
| `POST /api/requests/{id}/reassign`           | Yes     | True no-mock HTTP | `ExceptionRequestApiTest.php`                                                                                                       | `testRequesterReassignEndpointIsAccessible`                                          |
| `GET /api/audit/logs`                        | Yes     | True no-mock HTTP | `AuditApiTest.php`                                                                                                                  | `testAuditLogAccessibleToAdmin`                                                      |
| `GET /api/resources`                         | Yes     | True no-mock HTTP | `BookingApiTest.php`                                                                                                                | `testListResources`                                                                  |
| `GET /api/resources/{id}/availability`       | Yes     | True no-mock HTTP | `BookingApiTest.php`                                                                                                                | `testGetResourceAvailability`                                                        |
| `POST /api/bookings`                         | Yes     | True no-mock HTTP | `BookingApiTest.php`                                                                                                                | `testCreateBookingSuccess`, `testIdempotentBookingReturnsSameId`                     |
| `GET /api/bookings`                          | Yes     | True no-mock HTTP | `BookingApiTest.php`                                                                                                                | `testListOwnBookings`                                                                |
| `GET /api/bookings/{id}`                     | Yes     | True no-mock HTTP | `BookingApiTest.php`                                                                                                                | `testCancelOwnBooking` (detail fetch)                                                |
| `DELETE /api/bookings/{id}`                  | Yes     | True no-mock HTTP | `BookingApiTest.php`                                                                                                                | `testCancelOwnBooking`                                                               |
| `GET /api/attendance/today`                  | Yes     | True no-mock HTTP | `AttendanceApiTest.php`                                                                                                             | `testGetTodayAttendanceCard`                                                         |
| `GET /api/attendance/{date}`                 | Yes     | True no-mock HTTP | `AttendanceApiTest.php`                                                                                                             | `testGetAttendanceByDate`                                                            |
| `GET /api/attendance/history`                | Yes     | True no-mock HTTP | `AttendanceApiTest.php`                                                                                                             | `testGetHistoryPaginated`                                                            |
| `GET /api/attendance/rules`                  | Yes     | True no-mock HTTP | `AttendanceApiTest.php`                                                                                                             | `testGetAttendanceRules`                                                             |
| `GET /api/admin/health`                      | Yes     | True no-mock HTTP | `AuthApiTest.php`                                                                                                                   | `testApiAdminRequiresAuth`                                                           |
| `GET /api/admin/users`                       | Yes     | True no-mock HTTP | `AdminApiTest.php`, `AdminSignatureApiTest.php`, `ApprovalApiTest.php`                                                              | `testListUsersContainsExpectedFields`, `testAdminReadEndpointsDoNotRequireSignature` |
| `POST /api/admin/users`                      | Yes     | True no-mock HTTP | `AdminApiTest.php`, `AdminSignatureApiTest.php`                                                                                     | `testDeleteUserData` (setup create), `testAdminWriteWithValidSignatureSucceeds`      |
| `PUT /api/admin/users/{id}`                  | Yes     | True no-mock HTTP | `AdminApiTest.php`                                                                                                                  | `testUpdateUserIsOut`                                                                |
| `GET /api/admin/deletion-requests`           | Yes     | True no-mock HTTP | `AdminApiTest.php`                                                                                                                  | `testListDeletionRequests`                                                           |
| `POST /api/admin/users/{id}/delete-data`     | Yes     | True no-mock HTTP | `AdminApiTest.php`                                                                                                                  | `testDeleteUserData`                                                                 |
| `GET /api/admin/config`                      | Yes     | True no-mock HTTP | `AdminApiTest.php`                                                                                                                  | `testGetConfig`                                                                      |
| `PUT /api/admin/config`                      | Yes     | True no-mock HTTP | `AdminApiTest.php`                                                                                                                  | `testUpdateConfig`                                                                   |
| `POST /api/admin/attendance/import`          | Yes     | True no-mock HTTP | `AdminApiTest.php`                                                                                                                  | `testImportAttendanceMissingFileReturns400`, `testImportAttendanceWithCsvSucceeds`   |
| `GET /api/admin/anomaly-alerts`              | Yes     | True no-mock HTTP | `AdminApiTest.php`                                                                                                                  | `testAnomalyAlerts`                                                                  |
| `GET /api/work-orders/technicians`           | Yes     | True no-mock HTTP | `WorkOrderApiTest.php`                                                                                                              | `testDispatcherCanListTechnicians`                                                   |
| `POST /api/work-orders`                      | Yes     | True no-mock HTTP | `WorkOrderApiTest.php`                                                                                                              | `testSubmitWorkOrder`                                                                |
| `GET /api/work-orders`                       | Yes     | True no-mock HTTP | `WorkOrderApiTest.php`                                                                                                              | `testListWorkOrdersAsEmployee`                                                       |
| `GET /api/work-orders/{id}`                  | Yes     | True no-mock HTTP | `WorkOrderApiTest.php`                                                                                                              | `testGetWorkOrderDetailReturnsExpectedFields`                                        |
| `PATCH /api/work-orders/{id}/status`         | Yes     | True no-mock HTTP | `WorkOrderApiTest.php`                                                                                                              | `testDispatcherAssignsTechnician`, `testEmployeeRatesCompletedWorkOrder`             |
| `POST /api/work-orders/{id}/rate`            | Yes     | True no-mock HTTP | `WorkOrderApiTest.php`                                                                                                              | `testEmployeeRatesCompletedWorkOrder`                                                |
| `GET /api/work-orders/{id}/photos/{photoId}` | **No**  | Uncovered         | —                                                                                                                                   | Route in `WorkOrderController.php`, no matching request found in API tests           |

## API Test Classification

### 1) True No-Mock HTTP

- Classified files:
  - `repo/backend/tests/api_tests/AuthApiTest.php`
  - `repo/backend/tests/api_tests/AttendanceApiTest.php`
  - `repo/backend/tests/api_tests/ApprovalApiTest.php`
  - `repo/backend/tests/api_tests/AdminApiTest.php`
  - `repo/backend/tests/api_tests/AdminSignatureApiTest.php`
  - `repo/backend/tests/api_tests/AuditApiTest.php`
  - `repo/backend/tests/api_tests/BookingApiTest.php`
  - `repo/backend/tests/api_tests/ExceptionRequestApiTest.php`
  - `repo/backend/tests/api_tests/WorkOrderApiTest.php`
- Evidence pattern: Symfony `WebTestCase` + `$client->request(...)` against real HTTP endpoints.

### 2) HTTP with Mocking

- **None detected** in backend API test files.

### 3) Non-HTTP (unit/integration without HTTP)

- Backend unit tests under `repo/backend/tests/unit_tests/*.php` (service/policy/state-machine focused).
- Frontend unit tests under `repo/frontend/tests/unit_tests/*.test.ts*` (module-level and utility-level tests, some with `vi.mock`).

## Mock Detection (Strict)

### Backend API tests

- No `jest.mock`, `vi.mock`, `sinon.stub`, or PHPUnit mock usage detected in `repo/backend/tests/api_tests/*.php`.

### Backend unit tests (non-HTTP)

- Extensive dependency mocking via PHPUnit `createMock(...)`.
- Examples:
  - `repo/backend/tests/unit_tests/ApprovalWorkflowServiceTest.php` (mocks `EntityManagerInterface`, repositories, `SlaService`, `AuditService`).
  - `repo/backend/tests/unit_tests/WorkOrderStateMachineTest.php` (mocks repositories/services, partial mocks via `getMock()`).

### Frontend unit tests (non-HTTP)

- `vi.mock('axios', ...)` present:
  - `repo/frontend/tests/unit_tests/realModules.api.test.ts`
  - `repo/frontend/tests/unit_tests/workorder.api.test.ts`

## Coverage Summary

- Total endpoints: **44**
- Endpoints with HTTP tests (exact method+path): **43**
- Endpoints with true no-mock HTTP tests: **43**
- Endpoints with HTTP tests using mocking: **0**

Computed:

- HTTP coverage % = $\frac{43}{44} \times 100 = 97.73\%$
- True API coverage % = $\frac{43}{44} \times 100 = 97.73\%$

## Unit Test Summary

### Backend Unit Tests

- Files detected: `repo/backend/tests/unit_tests/*.php` (18 unique test files).
- Modules covered (evidence by filename):
  - Services: `AttendanceEngineService`, `ApprovalWorkflowService`, `AuditService`, `BookingService` behavior (`BookingIdempotencyTest`, `BookingStateMachineTest`), `SlaService`, `AnomalyDetectionService`, `MaskingService`, `EncryptionService`, `FileUploadService` signing.
  - Security/Auth: `ApiSignatureAuthenticatorTest`, `IdentityAccessPolicyTest`, `ReassignAuthorizationTest`.
  - Workflow/state logic: `WorkOrderStateMachineTest`, `WorkOrderCreateTest`, `ApprovalDepthTest`, `ExceptionDateValidationTest`.
- Controllers directly unit tested: **not evident** (controller behavior mostly covered via API tests).
- Repositories directly unit tested: **not evident** (mostly mocked collaborators).
- Important backend modules with weak/no explicit direct tests:
  - `AttendanceController`, `AdminController`, `WorkOrderController`, etc. (not unit-tested directly; covered through API tests instead).
  - `WorkOrder photo retrieval endpoint` behavior (`GET /api/work-orders/{id}/photos/{photoId}`) lacks API test coverage.

### Frontend Unit Tests (STRICT)

- Frontend test files present: **Yes** (`repo/frontend/tests/unit_tests/*.test.ts*`).
- Framework/tools detected:
  - `vitest` imports (`describe`, `it`, `expect`, `vi`) in unit test files.
  - Project dev dependency evidence in `repo/frontend/package.json` (`vitest`, `@testing-library/react`, `jsdom`).
- Tests targeting frontend logic/components/modules:
  - Direct imports from production frontend modules, e.g.:
    - `../../src/api/auth` and `../../src/api/attendance` (`realModules.api.test.ts`)
    - `../../src/components/attendance/policyHintText` (`realModules.api.test.ts`, `policyHints.test.ts`)
    - frontend API wrappers and utility/state modules in `workorder.api.test.ts`, `workOrderStateMachine.test.ts`, `roleAccess.test.ts`, etc.
- Important frontend modules/components not clearly unit-tested:
  - Page components under `repo/frontend/src/pages/*` (e.g., `Login.tsx`, `Dashboard.tsx`, `pages/admin/*`, `pages/approvals/*`) lack clear component-render test evidence.
  - Shared UI/layout components under `repo/frontend/src/components/ui/*` and `components/layout/*` lack clear render test evidence.

**Mandatory verdict: Frontend unit tests: PRESENT**

Strict failure rule check (fullstack + FE tests missing/insufficient):

- FE unit tests are present and substantial at module level.
- **No CRITICAL GAP triggered for “missing frontend unit tests.”**
- However, component-render coverage is weaker than module-logic coverage.

### Cross-layer Observation

- Backend API testing is broad and deep.
- Frontend has strong module-level unit tests and Playwright e2e presence (`repo/frontend/tests/e2e/*.spec.ts`), but component-level rendering coverage appears comparatively thinner.
- Balance assessment: **moderately backend-heavy on route assertions; frontend stronger in logic than UI component rendering.**

## API Observability Check

- Generally **strong** in backend API tests:
  - Explicit method/path in each `$client->request(...)`.
  - Request payloads shown in tests.
  - Response assertions check status and key fields.
- Weak spots:
  - `ExceptionRequestApiTest::testRequesterReassignEndpointIsAccessible` checks mostly non-`404`/non-`500`, weak response contract verification.
  - `AuthApiTest::testRateLimitConfigIsEnforced` allows `[200,429]` without strict limit-header assertions.

## Tests Check

### Success paths

- Well covered for auth, attendance, requests, approvals, bookings, admin operations, work-orders.

### Failure/negative paths

- Present across suites (unauthenticated/forbidden/malformed payload/invalid state transitions).

### Edge cases and validation

- Present for idempotency, filing windows, missing fields, signature validation.

### Auth/permissions

- Strong coverage via role-based negative and positive tests (`AdminApiTest`, `AuditApiTest`, `WorkOrderApiTest`, `ApprovalApiTest`).

### Integration boundaries

- Strong backend boundary coverage through HTTP-level tests.
- Frontend API-wrapper tests partly mock transport (`vi.mock('axios')`) and are not substitutes for real FE↔BE integration.

### `run_tests.sh` assessment

- File: `repo/run_tests.sh`
- Host mode orchestrates via Docker/Compose; suite runs inside containers/docker images.
- Also includes dependency installation commands **inside containers** (`composer install`, `npm ci`, `npm install`, Playwright npm install).
- Verdict for requested check:
  - **Docker-based: YES**
  - **Local host dependency requirement: NO (explicitly checks Docker only)**
  - Note: runtime package installation occurs in containers.

## End-to-End Expectations (Fullstack)

- E2E tests are present: `repo/frontend/tests/e2e/auth.spec.ts`, `attendance.spec.ts`, `workorder.spec.ts`.
- Therefore FE↔BE E2E expectation is at least partially addressed.

## Test Coverage Score (0–100)

**Score: 88/100**

## Score Rationale

- - High endpoint coverage (43/44).
- - Broad true HTTP no-mock backend API suite.
- - Strong role/auth/signature negative-path coverage.
- - Frontend unit tests present with real-module imports; FE e2e present.
- − One uncovered backend endpoint (`GET /api/work-orders/{id}/photos/{photoId}`).
- − Some weak-assertion tests (endpoint existence/status-only checks).
- − Frontend component render coverage not clearly demonstrated.

## Key Gaps

1. **Uncovered endpoint**: `GET /api/work-orders/{id}/photos/{photoId}`.
2. **Weak API observability in selected tests**: status-only/non-404 assertions.
3. **Frontend unit emphasis**: strong utility/module tests, weaker direct page/component render tests.

## Confidence & Assumptions

- Confidence: **High** for route inventory and backend API mapping.
- Assumptions:
  1. Only attribute routes under `src/Controller` are active (per `config/routes.yaml`).
  2. Duplicate file listings in search output are tooling duplicates, not duplicate physical files.

## Final Verdict (Test Coverage Audit)

- **PARTIAL PASS**
  - Reason: very strong overall coverage and quality, but not complete due to one uncovered endpoint and some weak-assertion tests.

---

# README Audit

## README Location Check

- Required path: `repo/README.md`
- Result: **Exists**.

## Hard Gates

### Formatting

- Markdown structure is clean/readable with headings, tables, and code blocks.
- Result: **PASS**

### Startup Instructions (fullstack/backend requirement)

- Contains required command form:
  - `docker compose up --build`
  - `docker-compose up --build`
- Required token `docker-compose up` present.
- Result: **PASS**

### Access Method

- Declares:
  - Frontend `http://localhost:3000`
  - API `http://localhost:8000`
- Result: **PASS**

### Verification Method

- Provides UI role-based verification flows and API health curl check.
- Evidence: “Verifying the Application Works” section in `repo/README.md`.
- Result: **PASS**

### Environment Rules (Docker-contained, no host runtime installs)

- README prerequisites list Docker + Docker Compose only.
- Testing section states no local PHP/Composer/Node/npm required.
- README does not instruct `npm install`, `pip install`, `apt-get`, or manual DB setup.
- Result: **PASS**

### Demo Credentials (conditional auth)

- Auth exists (explicit login workflows + auth endpoints in backend).
- README provides seeded credentials across all listed roles:
  - System Administrator, HR Admin, Supervisor, Employee, Dispatcher, Technician.
- Includes username + password for each role.
- Result: **PASS**

## Engineering Quality Evaluation

- Tech stack clarity: **Strong**
- Architecture explanation: **Good** (layers and structure are described)
- Testing instructions: **Good** (`bash run_tests.sh`, suite breakdown)
- Security/roles: **Good** (role matrix and role-specific verification)
- Workflows: **Good** (step-by-step role flows)
- Presentation quality: **Good**

## High Priority Issues

- None.

## Medium Priority Issues

1. README states broad test behavior assertions (e.g., “real HTTP requests, real MySQL”) but does not explicitly mention the one uncovered endpoint (`GET /api/work-orders/{id}/photos/{photoId}`) in testing scope disclaimers.

## Low Priority Issues

1. Contains both `docker compose` and `docker-compose` command blocks (not incorrect; slightly redundant).

## Hard Gate Failures

- **None**.

## README Verdict

**PASS**

## Final Verdict (README Audit)

- **PASS**

---

# Combined Final Verdicts

- **Test Coverage Audit**: **PARTIAL PASS**
- **README Audit**: **PASS**

Overall strict outcome: repository has high test maturity and a compliant README, with a targeted API coverage gap and minor test-assertion-quality weaknesses.
