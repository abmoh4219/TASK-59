# CLAUDE.md — Workforce & Operations Hub
# Task ID: TASK-59-W2
# Read SPEC.md + CLAUDE.md + PLAN.md before every single response. No exceptions.

## Read Order (mandatory, every response)
1. SPEC.md — source of truth
2. CLAUDE.md — this file
3. PLAN.md — current execution state

## Project Identity

- Name: Workforce & Operations Hub
- Task ID: TASK-59-W2
- Backend: Symfony 7 (PHP 8.2) — REST API at port 8000
- Frontend: React 18 + TypeScript + Vite + TailwindCSS + shadcn/ui — port 3000
- Database: MySQL 8 via Doctrine ORM + migrations
- Infrastructure: Single docker-compose.yml

## QA Evaluation — BOTH TESTS MUST PASS (read before writing any code)

TEST 1 — Static Code Audit: AI reads every PHP and TSX file looking for file:line evidence.
Security must be explicitly coded. Audit log must have no UPDATE/DELETE methods.
Business rules enforced at service layer. Tests must have real assertions.

TEST 2 — Docker Runtime: Human logs in with all 6 credentials, clicks every page,
tests every feature. No broken pages. No 500 errors. No placeholder content.
Every form submits to real API. Every table shows real MySQL data.

BOTH must pass. Passing one but not the other = FAIL.

## Folder Structure (all code inside repo/)

```
TASK-59-W2/
├── SPEC.md
├── CLAUDE.md
├── PLAN.md
├── docs/
├── sessions/
├── metadata.json
└── repo/
    ├── docker-compose.yml
    ├── .env.example
    ├── .gitignore
    ├── README.md
    ├── run_tests.sh
    ├── backend/                        ← Symfony 7 app
    │   ├── composer.json
    │   ├── symfony.lock
    │   ├── .env
    │   ├── config/
    │   │   ├── packages/
    │   │   └── routes/
    │   ├── src/
    │   │   ├── Controller/             ← one controller per domain
    │   │   │   ├── AuthController.php
    │   │   │   ├── AttendanceController.php
    │   │   │   ├── ExceptionRequestController.php
    │   │   │   ├── ApprovalController.php
    │   │   │   ├── WorkOrderController.php
    │   │   │   ├── BookingController.php
    │   │   │   ├── FileController.php
    │   │   │   ├── AuditController.php
    │   │   │   └── AdminController.php
    │   │   ├── Entity/                 ← Doctrine entities
    │   │   │   ├── User.php
    │   │   │   ├── Role.php
    │   │   │   ├── ShiftSchedule.php
    │   │   │   ├── PunchEvent.php
    │   │   │   ├── AttendanceRecord.php
    │   │   │   ├── AttendanceException.php
    │   │   │   ├── ExceptionRule.php
    │   │   │   ├── ExceptionRequest.php
    │   │   │   ├── ApprovalStep.php
    │   │   │   ├── ApprovalAction.php
    │   │   │   ├── WorkOrder.php
    │   │   │   ├── WorkOrderPhoto.php
    │   │   │   ├── WorkOrderRating.php
    │   │   │   ├── Resource.php
    │   │   │   ├── Booking.php
    │   │   │   ├── BookingAllocation.php
    │   │   │   ├── IdempotencyKey.php
    │   │   │   ├── FileUpload.php
    │   │   │   └── AuditLog.php
    │   │   ├── Service/                ← all business logic here
    │   │   │   ├── AttendanceEngineService.php
    │   │   │   ├── ExceptionDetectionService.php
    │   │   │   ├── ApprovalWorkflowService.php
    │   │   │   ├── SlaService.php
    │   │   │   ├── WorkOrderService.php
    │   │   │   ├── BookingService.php
    │   │   │   ├── FileUploadService.php
    │   │   │   ├── EncryptionService.php
    │   │   │   ├── MaskingService.php
    │   │   │   ├── AuditService.php
    │   │   │   ├── RateLimitService.php
    │   │   │   └── AnomalyDetectionService.php
    │   │   ├── Command/
    │   │   │   ├── ImportAttendanceCsvCommand.php
    │   │   │   ├── ProcessAttendanceEngineCommand.php
    │   │   │   └── EscalateOverdueApprovalsCommand.php
    │   │   ├── EventListener/
    │   │   │   └── CsrfListener.php
    │   │   ├── Security/
    │   │   │   ├── ApiSignatureAuthenticator.php
    │   │   │   └── LoginFormAuthenticator.php
    │   │   └── Repository/             ← no UPDATE/DELETE on AuditLogRepository
    │   ├── migrations/
    │   ├── Dockerfile
    │   └── tests/
    │       ├── unit_tests/             ← PHPUnit, no DB
    │       │   ├── AttendanceEngineTest.php
    │       │   ├── ExceptionDetectionTest.php
    │       │   ├── SlaServiceTest.php
    │       │   ├── MaskingServiceTest.php
    │       │   ├── EncryptionServiceTest.php
    │       │   ├── BookingIdempotencyTest.php
    │       │   └── WorkOrderStateMachineTest.php
    │       └── api_tests/              ← PHPUnit WebTestCase, real MySQL
    │           ├── AuthApiTest.php
    │           ├── AttendanceApiTest.php
    │           ├── ExceptionRequestApiTest.php
    │           ├── ApprovalApiTest.php
    │           ├── WorkOrderApiTest.php
    │           ├── BookingApiTest.php
    │           └── AuditApiTest.php
    ├── frontend/                       ← React 18 + Vite app
    │   ├── package.json
    │   ├── vite.config.ts
    │   ├── tailwind.config.ts
    │   ├── tsconfig.json
    │   ├── index.html
    │   ├── Dockerfile
    │   ├── src/
    │   │   ├── main.tsx
    │   │   ├── App.tsx
    │   │   ├── api/                    ← axios client + typed API calls
    │   │   │   ├── client.ts           ← axios instance, CSRF header, interceptors
    │   │   │   ├── auth.ts
    │   │   │   ├── attendance.ts
    │   │   │   ├── workOrders.ts
    │   │   │   └── bookings.ts
    │   │   ├── components/
    │   │   │   ├── layout/
    │   │   │   │   ├── Sidebar.tsx     ← role-gated nav items only
    │   │   │   │   ├── TopBar.tsx
    │   │   │   │   └── Layout.tsx
    │   │   │   ├── ui/                 ← shadcn/ui + custom components
    │   │   │   │   ├── Button.tsx
    │   │   │   │   ├── Card.tsx
    │   │   │   │   ├── Badge.tsx
    │   │   │   │   ├── Modal.tsx
    │   │   │   │   ├── Table.tsx
    │   │   │   │   ├── Skeleton.tsx
    │   │   │   │   ├── EmptyState.tsx
    │   │   │   │   └── Timeline.tsx
    │   │   │   └── attendance/
    │   │   │       ├── AttendanceCard.tsx
    │   │   │       ├── ExceptionBadge.tsx
    │   │   │       ├── PolicyHint.tsx
    │   │   │       └── ApprovalTimeline.tsx
    │   │   ├── pages/
    │   │   │   ├── Login.tsx
    │   │   │   ├── Dashboard.tsx
    │   │   │   ├── attendance/
    │   │   │   │   ├── AttendancePage.tsx
    │   │   │   │   ├── ExceptionRequestForm.tsx
    │   │   │   │   └── RequestDetailPage.tsx
    │   │   │   ├── approvals/
    │   │   │   │   └── ApprovalQueuePage.tsx
    │   │   │   ├── workorders/
    │   │   │   │   ├── WorkOrderListPage.tsx
    │   │   │   │   ├── WorkOrderForm.tsx
    │   │   │   │   └── WorkOrderDetailPage.tsx
    │   │   │   ├── bookings/
    │   │   │   │   └── BookingPage.tsx
    │   │   │   └── admin/
    │   │   │       ├── UserManagementPage.tsx
    │   │   │       ├── AuditLogPage.tsx
    │   │   │       ├── CsvImportPage.tsx
    │   │   │       └── SystemConfigPage.tsx
    │   │   ├── hooks/
    │   │   │   ├── useAuth.ts
    │   │   │   ├── useAttendance.ts
    │   │   │   └── useWorkOrders.ts
    │   │   ├── context/
    │   │   │   └── AuthContext.tsx
    │   │   └── types/
    │   │       └── index.ts
    │   └── tests/
    │       ├── unit_tests/             ← Vitest, pure logic
    │       │   ├── maskPhone.test.ts
    │       │   ├── policyHints.test.ts
    │       │   ├── slaCountdown.test.ts
    │       │   └── timeIncrement.test.ts
    │       └── api_tests/             ← Vitest + real API calls
    │           ├── auth.api.test.ts
    │           ├── attendance.api.test.ts
    │           └── workorder.api.test.ts
    └── nginx/
        └── nginx.conf                 ← serves frontend, proxies /api to backend
```

## Non-Negotiable Rules

1. **Read SPEC.md + CLAUDE.md + PLAN.md first.** Every response, no exceptions.
2. **One task at a time.** Complete exactly the current PLAN.md task.
3. **Mark [x] then continue.** Update PLAN.md, move to next task without stopping.
4. **All code in repo/.** Never create files outside repo/.
5. **Every page must work in Docker.** QA clicks every page. No broken forms, no placeholder pages, no 500 errors.
6. **Security explicitly coded.** CSRF, rate limiting, lockout, encryption — real PHP code with comments. Not just config.
7. **Doctrine only — no raw SQL.** Use QueryBuilder or DQL for all queries. No string concatenation in SQL.
8. **Audit log append-only.** AuditService only calls persist() + flush(). AuditLogRepository has NO update/delete methods.
9. **Service layer owns business rules.** Controllers are thin. All logic in Service classes.
10. **Sidebar is role-gated.** Each role only sees its permitted nav items. No forbidden links visible.
11. **Beautiful modern SaaS UI.** Premium dark theme with accent colors. Not basic Bootstrap. QA judges the visual quality.
12. **No hardcoded data in React.** All data from real API calls via axios. No mock arrays.
13. **Tests have real assertions.** PHPUnit tests use real MySQL. Vitest unit tests test real logic. No empty test functions.
14. **Pause at phase boundaries only.** Complete all tasks in a phase then pause.
15. **Fix before proceeding.** PHP or TypeScript errors fixed within same task.

## Tech Stack Details

### Backend (Symfony 7, PHP 8.2)

composer.json key dependencies:
- symfony/framework-bundle: ^7.0
- symfony/security-bundle: ^7.0
- symfony/doctrine-bundle: ^2.11
- doctrine/orm: ^3.0
- doctrine/migrations: ^3.7
- symfony/rate-limiter: ^7.0
- symfony/console: ^7.0
- symfony/scheduler: ^7.0
- phpunit/phpunit: ^11.0
- symfony/browser-kit: ^7.0 (for API tests)
- league/csv: ^9.0 (CSV import)

### Frontend (React 18, TypeScript, Vite)

package.json key dependencies:
- react: ^18.0
- react-dom: ^18.0
- react-router-dom: ^6.0
- axios: ^1.0 (with CSRF interceptor)
- @tanstack/react-query: ^5.0 (server state)
- tailwindcss: ^3.0
- @radix-ui/react-* (shadcn/ui primitives)
- react-hook-form + zod (form validation)
- date-fns (date formatting MM/DD/YYYY)
- vitest: ^1.0
- @testing-library/react: ^14.0

## Security Architecture (all explicitly coded)

### CSRF Protection (Symfony EventListener)
```php
// src/EventListener/CsrfListener.php
// Validates X-CSRF-Token header on all POST/PUT/PATCH/DELETE requests
// Token stored in session, refreshed on login
// Returns 403 if token missing or invalid
```

### Rate Limiting (Symfony RateLimiter)
```php
// RateLimitService.php
// Standard endpoints: 60 requests/minute per user (keyed by user ID)
// Upload endpoints: 10 uploads/minute per user
// Returns 429 with Retry-After header when exceeded
```

### Account Lockout (AnomalyDetectionService)
```php
// AnomalyDetectionService.php
// Track failed login attempts in failed_login_attempts table
// After 5 failures within 15 minutes: lock account, write audit log
// Unlock automatically after 15 minutes
// Alert System Administrator via in-system notification
```

### Encryption at Rest (EncryptionService)
```php
// EncryptionService.php
// AES-256-GCM via PHP sodium_crypto_aead_aes256gcm_encrypt
// Key from APP_ENCRYPTION_KEY env var
// encrypt(string $plaintext): string — returns base64(nonce + ciphertext)
// decrypt(string $encoded): string
```

### Phone Masking (MaskingService)
```php
// MaskingService.php
// maskPhone(string $phone): string
// Input: "+15551234567" → Output: "(555) ***-1234"
// Used in API responses for non-HR-Admin roles
// Audit logs always use masked values
```

### API Signature (privileged endpoints)
```php
// ApiSignatureAuthenticator.php
// For admin endpoints: validates HMAC-SHA256 signature
// Signature = HMAC(method + path + timestamp + body_hash, APP_SIGNING_KEY)
// Validates timestamp within ±5 minutes
// Nonce stored in DB to prevent replay
```

## Attendance Engine (deterministic)

```php
// AttendanceEngineService.php
// Runs nightly at 2:00 AM via Symfony Scheduler
// Also triggerable via: php bin/console app:process-attendance --date=YYYY-MM-DD

// Exception detection rules (ExceptionDetectionService.php):
// LATE_ARRIVAL: first punch > shift_start + tolerance (default 5 min = 9:05 AM for 9:00 AM shift)
// EARLY_LEAVE: last punch < shift_end - tolerance
// MISSED_PUNCH: no punch event within 30 minutes of shift start
// ABSENCE: no punch events at all for the day
// APPROVED_OFFSITE: has approved business_trip or outing request for the day

// All tolerances stored in ExceptionRule entity — configurable by HR Admin
// Engine is deterministic: same inputs always produce same exception set
```

## Work Order State Machine

```
submitted → dispatched → accepted → in_progress → completed → rated
                                                 ↑
                                    (technician can add notes)
```

Rules:
- Only Dispatcher can move: submitted → dispatched
- Only Technician can move: dispatched → accepted → in_progress → completed
- Rating window: 72 hours from completed_at
- State transitions validated in WorkOrderService, not controller

## Approval Workflow

```
Step 1: Supervisor (SLA: 24 business hours)
Step 2: HR Admin (SLA: 24 business hours) — for PTO/leave/policy override
Step 3: System Administrator (SLA: 24 business hours) — optional, configurable

Auto-escalation: if step not acted on within SLA + 2 hours → assign to backup approver
Withdraw: allowed only before Step 1 approver acts
Reassignment: any step's approver can be reassigned if marked as out
```

## Booking Idempotency

```php
// BookingService.php
// createBooking(array $data, string $clientKey): Booking
// Check IdempotencyKey table: if same clientKey used within 10 minutes → return existing booking
// clientKey generated by frontend as UUID on form open
// Prevents duplicate submits from double-clicks or network retry
```

## Docker Architecture (single docker-compose.yml)

```yaml
services:
  setup:        # alpine, copies .env.example → .env on first run
  mysql:        # mysql:8.0, health check
  backend:      # PHP 8.2-fpm + Nginx, port 8000
  frontend:     # Node build → Nginx serve, port 3000
  test:         # profile: test, runs run_tests.sh
  mysql-test:   # profile: test, separate test DB on port 3307
```

## run_tests.sh (Docker-first, also runnable locally)

```bash
#!/bin/sh
set -e
echo "========================================"
echo "  Workforce & Operations Hub Test Suite"
echo "========================================"

# Uses system php and node — available in Docker image
# Also runnable locally if PHP 8.2 + Node 20 installed
if ! command -v php > /dev/null; then
  echo "ERROR: php not found. Run via Docker."; exit 1
fi
if ! command -v node > /dev/null; then
  echo "ERROR: node not found. Run via Docker."; exit 1
fi

BACKEND_UNIT=0; BACKEND_API=0; FRONTEND_UNIT=0; FRONTEND_API=0

echo "--- Backend Unit Tests (tests/unit_tests/) ---"
cd /app/backend
php bin/phpunit tests/unit_tests/ --testdox 2>&1 || BACKEND_UNIT=1
[ $BACKEND_UNIT -eq 0 ] && echo "✅ Backend Unit PASSED" || echo "❌ Backend Unit FAILED"

echo "--- Backend API Tests (tests/api_tests/) ---"
php bin/phpunit tests/api_tests/ --testdox 2>&1 || BACKEND_API=1
[ $BACKEND_API -eq 0 ] && echo "✅ Backend API PASSED" || echo "❌ Backend API FAILED"

echo "--- Frontend Unit Tests (tests/unit_tests/) ---"
cd /app/frontend
npx vitest run tests/unit_tests/ 2>&1 || FRONTEND_UNIT=1
[ $FRONTEND_UNIT -eq 0 ] && echo "✅ Frontend Unit PASSED" || echo "❌ Frontend Unit FAILED"

echo "--- Frontend API Tests (tests/api_tests/) ---"
npx vitest run tests/api_tests/ 2>&1 || FRONTEND_API=1
[ $FRONTEND_API -eq 0 ] && echo "✅ Frontend API PASSED" || echo "❌ Frontend API FAILED"

echo "========================================"
TOTAL=$((BACKEND_UNIT+BACKEND_API+FRONTEND_UNIT+FRONTEND_API))
[ $TOTAL -eq 0 ] && echo "  ALL TESTS PASSED" && exit 0
echo "  SOME TESTS FAILED"
echo "  Backend Unit: $([ $BACKEND_UNIT -eq 0 ] && echo PASS || echo FAIL)"
echo "  Backend API:  $([ $BACKEND_API -eq 0 ] && echo PASS || echo FAIL)"
echo "  Frontend Unit:$([ $FRONTEND_UNIT -eq 0 ] && echo PASS || echo FAIL)"
echo "  Frontend API: $([ $FRONTEND_API -eq 0 ] && echo PASS || echo FAIL)"
exit 1
```

## UI Design Standards (Premium SaaS — NOT basic Bootstrap)

```
Theme: Deep slate dark background (#0F1117), bright accent (#6366F1 indigo or #3B82F6 blue),
       white text, muted gray secondary text, subtle card borders

Layout: Fixed sidebar (240px) + main content, sticky top bar with breadcrumbs

Cards: Dark surface (#1C1F26), 1px border (#2A2D36), rounded-xl, subtle hover shadow

Buttons: Primary = indigo gradient; Secondary = bordered; Danger = red-tinted

Tables: Dark alternating rows, sticky header, colored status badges with glow

Badges:
  Role: Employee=blue, Supervisor=purple, HR=red, Dispatcher=orange, Tech=green, Admin=gold
  Exception: LATE=amber, MISSED=red, ABSENT=red, EARLY_LEAVE=orange, OFFSITE=green
  Work Order: submitted=gray, dispatched=blue, accepted=indigo, in_progress=amber,
              completed=green, rated=teal

Every page: skeleton loading, empty state with icon + action, error state with retry
Sidebar: role-gated — each role ONLY sees their permitted nav items
```

## .env.example (committed to git)

```
APP_ENV=prod
APP_SECRET=change-this-32-char-secret-here!!
DATABASE_URL="mysql://wfops:wfops_pass@mysql:3306/wfops?serverVersion=8.0"
APP_ENCRYPTION_KEY=change-this-to-32-byte-aes-key!!
APP_SIGNING_KEY=change-this-signing-key-for-hmac
CORS_ALLOW_ORIGIN=http://localhost:3000
```

## .gitignore

```
/backend/vendor/
/backend/var/
/backend/.env.local
/frontend/node_modules/
/frontend/dist/
.env
*.log
mysql-data/
uploads/
```

## README (minimal)

```markdown
# Workforce & Operations Hub

## Run
```bash
docker compose up --build
```
Frontend: http://localhost:3000
API: http://localhost:8000

## Test
```bash
docker compose --profile test run --build test
```

## Stop
```bash
docker compose down
```

## Login
| Role | Username | Password |
|---|---|---|
| System Administrator | admin | Admin@WFOps2024! |
| HR Admin | hradmin | HRAdmin@2024! |
| Supervisor | supervisor | Super@2024! |
| Employee | employee | Emp@2024! |
| Dispatcher | dispatcher | Dispatch@2024! |
| Technician | technician | Tech@2024! |
```

## Open Questions (from business prompt)

[ ] "Business hours" for SLA: Mon-Fri 8AM-6PM (configurable by Admin)
[ ] "Approved offsite" exception: automatically resolved when approved trip/outing covers the day
[ ] Split issuance: one booking with N travelers creates N allocation records, one per traveler
[ ] Merged allocation: multiple bookings for same cost center → one combined allocation record
[ ] Rating scale: 1-5 stars, submitted within 72 hours of work order completion
[ ] Photo storage: encrypted local directory /app/uploads/, served via signed URL with expiry
[ ] Backup approver: designated per-department in user settings, fallback to HR Admin
[ ] CSV format: columns = employee_id, date (MM/DD/YYYY), event_type (IN/OUT), timestamp (HH:MM)
[ ] Data deletion: soft delete for users (anonymize PII), hard delete not allowed for audit records
