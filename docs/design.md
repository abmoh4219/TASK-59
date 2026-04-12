# Design — Workforce & Operations Hub

Task ID: TASK-59-W2
Generated from actual implemented code in `repo/`.

## Architecture Overview

```
┌──────────────────────────────────────────────────────────────────────┐
│                           Host Machine                               │
│                                                                      │
│  ┌──────────────┐     ┌────────────────┐     ┌───────────────────┐  │
│  │   Browser    │────►│  Frontend:3000 │     │  Backend API:8000 │  │
│  │              │     │  (Nginx)       │     │  (PHP-FPM+Nginx)  │  │
│  │ React SPA    │     │                │     │                   │  │
│  └──────────────┘     │ /api/* proxy ──┼────►│ Symfony 7 / PHP 8.2│  │
│                       │ SPA fallback   │     │                   │  │
│                       └────────────────┘     └─────────┬─────────┘  │
│                                                        │            │
│                                                        ▼            │
│                                              ┌───────────────────┐  │
│                                              │    MySQL:3306     │  │
│                                              │  (wfops db)       │  │
│                                              └───────────────────┘  │
└──────────────────────────────────────────────────────────────────────┘
```

## Docker Service Map

Single `docker-compose.yml` with 6 services:

| Service       | Image/Build              | Role                                           | Port         |
|---------------|--------------------------|------------------------------------------------|--------------|
| `setup`       | alpine:3.19              | Copies .env.example → backend/.env on startup  | —            |
| `mysql`       | mysql:8.0                | Primary database (wfops), health-checked       | 3306         |
| `backend`     | PHP 8.2-FPM + Nginx      | Symfony REST API                               | 8000         |
| `frontend`    | Node 20 → Nginx          | Built React SPA + /api proxy to backend        | 3000         |
| `mysql-test`  | mysql:8.0 (profile:test) | Isolated test database (wfops_test)            | 3307         |
| `test`        | backend Dockerfile test  | Runs run_tests.sh (backend + frontend suites)  | —            |

Volumes: `mysql-data` (persistent DB), `uploads` (file storage).

Startup order: `setup` → `mysql` (healthy) → `backend` (runs migrations + fixtures) → `frontend`.

## Entity Model (Doctrine ORM)

18 entities across 9 domain groups:

```
┌─────────────┐     ┌──────────────────┐     ┌─────────────────┐
│    User     │────►│  ShiftSchedule   │     │ FailedLogin     │
│  (bcrypt)   │     │  (Mon-Fri ranges)│     │  Attempt        │
│  role,      │     └──────────────────┘     └─────────────────┘
│  phoneEnc,  │              ▲
│  lockUntil, │              │
│  deletedAt  │     ┌──────────────────┐     ┌─────────────────┐
└─────────────┘     │  PunchEvent      │────►│ AttendanceRecord│
     │              │  (IN/OUT CSV)    │     │  (JSON excs)    │
     │              └──────────────────┘     └─────────────────┘
     │                                                │
     │                                                ▼
     │                                      ┌─────────────────┐
     │                                      │AttendanceExceptn│
     │                                      │  (LATE/ABSENT)  │
     │                                      └─────────────────┘
     │
     │      ┌──────────────────┐     ┌──────────────────┐
     ├─────►│ ExceptionRequest │────►│  ApprovalStep    │
     │      │ (PTO/LEAVE/etc)  │     │  (1-3 steps)     │
     │      │ clientKey, status│     │  slaDeadline     │
     │      └──────────────────┘     └──────────────────┘
     │                                        │
     │                                        ▼
     │                               ┌──────────────────┐
     │                               │ ApprovalAction   │
     │                               │ (APPROVE/REJECT) │
     │                               └──────────────────┘
     │
     │      ┌──────────────────┐     ┌──────────────────┐
     ├─────►│   WorkOrder      │────►│ WorkOrderPhoto   │
     │      │ (state machine)  │     │  (SHA-256 dedup) │
     │      └──────────────────┘     └──────────────────┘
     │
     │      ┌──────────────────┐     ┌──────────────────┐
     ├─────►│    Booking       │────►│ BookingAllocation│
     │      │ (clientKey idem) │     │  (per traveler)  │
     │      └──────────────────┘     └──────────────────┘
     │              │
     │              ▼
     │      ┌──────────────────┐
     │      │  Resource        │
     │      │ (bookable_res.)  │
     │      └──────────────────┘
     │
     │      ┌──────────────────┐     ┌──────────────────┐
     ├─────►│   AuditLog       │     │  IdempotencyKey  │
     │      │  APPEND-ONLY     │     │  (10-min window) │
     │      └──────────────────┘     └──────────────────┘
     │
     │      ┌──────────────────┐     ┌──────────────────┐
     ├─────►│  ExceptionRule   │     │   FileUpload     │
     │      │ (configurable)   │     │  (hash-dedup)    │
     │      └──────────────────┘     └──────────────────┘
     │
     └─────►(all audit linkages via actor_id + entity references)
```

Key relationships use Doctrine `ManyToOne` with foreign keys. All datetime fields use `datetime_immutable` type. JSON columns used for flexible arrays: `AttendanceRecord.exceptions`, `Booking.allocations`, `AuditLog.oldValueMasked/newValueMasked`.

## Security Architecture

### 1. CSRF Protection (Explicit EventListener)

**File:** `src/EventListener/CsrfListener.php`

Flow:
```
Client POST /api/requests                 ┌─────────────────┐
  ├─ Cookie: PHPSESSID=xxx                │ CsrfListener    │
  └─ X-CSRF-Token: abc123        ────────►│ onKernelRequest │
                                          └────────┬────────┘
                                                   │
                                   skip GET/HEAD/OPTIONS
                                   skip /api/auth/login
                                   skip /api/auth/csrf-token
                                   skip /api/health
                                                   │
                                                   ▼
                                      read session.csrf_token
                                      hash_equals(session, header)
                                                   │
                                  ┌────────────────┴────────────────┐
                                  ▼                                 ▼
                           MATCH → continue               MISMATCH → 403 JSON
```

- Priority 10 kernel.request listener
- Uses `hash_equals()` for timing-safe comparison
- CSRF tokens generated via `bin2hex(random_bytes(32))` on login

### 2. Rate Limiting (Symfony RateLimiter)

**File:** `src/Service/RateLimitService.php`

Configuration in `config/packages/framework.yaml`:
```yaml
rate_limiter:
    standard_api:
        policy: token_bucket
        limit: 60
        rate: { interval: '1 minute', amount: 60 }
    upload_api:
        policy: token_bucket
        limit: 10
        rate: { interval: '1 minute', amount: 10 }
```

- Keyed per user: `api_user_{userId}` / `upload_user_{userId}`
- Injected via `#[Autowire(service: 'limiter.standard_api')]`
- Returns 429 + `Retry-After` header when exceeded
- Every API controller method calls `checkStandardLimit()` at start

### 3. Account Lockout (AnomalyDetectionService)

**File:** `src/Service/AnomalyDetectionService.php`

Flow:
1. Failed login → persist `FailedLoginAttempt(username, ipAddress, attemptedAt)`
2. Count attempts in last 15 minutes for this username
3. If count ≥ 5 → set `user.lockedUntil = now + 15 minutes`
4. `isLockedOut()` returns true if `lockedUntil > NOW()`
5. AuthenticationFailureHandler returns 423 (Locked) when account locked

### 4. Encryption at Rest (EncryptionService)

**File:** `src/Service/EncryptionService.php`

- Algorithm: **AES-256-GCM** (authenticated encryption)
- Primary: `sodium_crypto_aead_aes256gcm_encrypt`
- Fallback: OpenSSL if CPU lacks AES-NI support
- Key: 32 bytes from `APP_ENCRYPTION_KEY` env
- Format: `base64(nonce[12] + ciphertext + tag)`
- Used for: `User.phoneEncrypted`

### 5. Phone Masking (MaskingService)

**File:** `src/Service/MaskingService.php`

- `maskPhone("+15551234567")` → `"(555) ***-4567"` (shows area code + last 4)
- `maskForLog(array)` → redacts keys containing `phone`, `password`, `token`, `secret`, `key`, `encrypted`, `hash`

### 6. API Signature (Privileged Admin Endpoints)

**File:** `src/Security/ApiSignatureAuthenticator.php`

For `/api/admin/**`:
1. Read `X-Api-Signature` + `X-Timestamp` + optional `X-Idempotency-Key`
2. Compute `HMAC-SHA256(method + path + timestamp + sha256(body), APP_SIGNING_KEY)`
3. Verify timestamp within ±5 minutes (replay prevention)
4. Check nonce not reused via `IdempotencyKey` table
5. Timing-safe comparison via `hash_equals()`

## Attendance Engine Algorithm

**Files:** `src/Service/ExceptionDetectionService.php`, `src/Service/AttendanceEngineService.php`, `src/Command/ProcessAttendanceEngineCommand.php`

Pure, deterministic detection — same inputs always produce the same exception set.

```
For each active user on $date:
  1. Load shift schedule for day-of-week (0=Sun..6=Sat)
  2. Load punch events for (user, date)
  3. Check if user has approved BUSINESS_TRIP or OUTING covering $date
     ├─ YES → return [APPROVED_OFFSITE]  (skip other checks)
     └─ NO  → continue
  4. If no punches at all → return [ABSENCE]
  5. Find first IN punch, last OUT punch
  6. LATE_ARRIVAL:
       firstIn > shiftStart + toleranceMinutes  (default 5)
  7. EARLY_LEAVE:
       lastOut < shiftEnd - toleranceMinutes
  8. MISSED_PUNCH:
       firstIn > shiftStart + missedPunchWindowMinutes  (default 30)
       OR no IN punch at all
  9. Upsert AttendanceRecord with exceptions JSON
 10. If exceptions changed → write audit log
```

Configurable thresholds come from `ExceptionRule` entity (updatable by HR admin).

**Nightly schedule:** `php bin/console app:process-attendance --date=YYYY-MM-DD`
Defaults to yesterday. Run via cron at 2:00 AM.

## Approval Workflow

**Files:** `src/Service/ApprovalWorkflowService.php`, `src/Service/SlaService.php`, `src/Command/EscalateOverdueApprovalsCommand.php`

```
Employee creates ExceptionRequest → ApprovalWorkflowService::createRequest()
  │
  ├─ Check idempotency: IdempotencyKey lookup by clientKey (10-min window)
  ├─ Validate 7-day filing window
  ├─ Create ExceptionRequest + ApprovalStep(s):
  │     - Step 1: Supervisor (always)
  │     - Step 2: HR Admin (only for PTO, LEAVE, BUSINESS_TRIP)
  │     - Step 3: System Admin (optional, configurable)
  ├─ SLA deadline calculated by SlaService::calculateSlaDeadline()
  │     - Adds N business hours (Mon-Fri 8AM-6PM)
  │     - Skips weekends, non-business hours
  └─ Write audit log (CREATE action)

Supervisor queue query → filter by approver = current user + status = PENDING
  ├─ For each step: display SLA deadline + remainingMinutes + isOverdue
  │
  ├─ Approve → WorkflowService::approve(step, actor, comment)
  │     - Validate actor is assigned approver
  │     - Advance to next step OR mark request APPROVED
  │     - Set new step's SLA deadline (24 business hours from now)
  │     - Audit log
  │
  ├─ Reject → mark request REJECTED, audit log
  │
  └─ Reassign → change approver, reset SLA deadline, audit log

Cron every 15 min: EscalateOverdueApprovalsCommand
  ├─ Find pending non-escalated steps
  ├─ For each: if slaDeadline + 2 business hours < NOW() → escalate
  └─ Escalate: assign backup approver (or fallback to HR Admin)

Withdraw (requester only, only before step 1 acted):
  ├─ Validate user is owner
  ├─ Check step 1 has no actedAt
  └─ Mark WITHDRAWN + audit log
```

**SLA color thresholds (frontend):**
- Green: > 12 hours remaining
- Amber: 4–12 hours remaining
- Red: < 4 hours remaining or overdue

## Work Order State Machine

**File:** `src/Service/WorkOrderService.php`

```
     ┌──────────────┐ dispatcher    ┌──────────────┐ technician   ┌──────────────┐
     │  submitted   ├──────────────►│  dispatched  ├─────────────►│   accepted   │
     │              │ (assigns      │              │ (accepts)    │              │
     └──────────────┘  technician)  └──────────────┘              └──────┬───────┘
                                                                         │
                                                                technician│
                                                              (starts work)
                                                                         ▼
     ┌──────────────┐  employee     ┌──────────────┐ technician   ┌──────────────┐
     │    rated     │◄──────────────┤  completed   │◄─────────────┤  in_progress │
     │              │ (within 72h)  │              │ (finishes)   │              │
     └──────────────┘               └──────────────┘              └──────────────┘
```

Transition rules enforced in `WorkOrderService::transition()`:

| From        | To          | Allowed Role(s)                 | Side effect                        |
|-------------|-------------|---------------------------------|------------------------------------|
| submitted   | dispatched  | ROLE_DISPATCHER, ROLE_ADMIN     | Sets dispatchedAt, assigns tech    |
| dispatched  | accepted    | ROLE_TECHNICIAN (assigned one)  | Sets acceptedAt                    |
| accepted    | in_progress | ROLE_TECHNICIAN                 | Sets startedAt                     |
| in_progress | completed   | ROLE_TECHNICIAN                 | Sets completedAt, completionNotes  |
| completed   | rated       | ROLE_EMPLOYEE (submitter)       | Within 72h window, sets rating 1-5 |

Rating window: 72 hours from `completedAt`. Enforced server-side; rating outside window returns 400.

Photos: max 5 per work order, JPEG/PNG only, 10MB max each, SHA-256 dedup.

## Booking Idempotency

**File:** `src/Service/BookingService.php`

```
POST /api/bookings
  { resourceId, startDatetime, endDatetime, purpose,
    travelers: [1, 2, 3], clientKey: "uuid-xxx" }
           │
           ▼
IdempotencyKey lookup by clientKey
           │
    ┌──────┴──────┐
    │             │
   Found?        Not found / expired
    │             │
    ▼             ▼
Return existing   Create Booking
  Booking         ├─ Validate resource available
                  ├─ Check overlapping time conflicts
                  ├─ For each traveler → BookingAllocation row
                  │   (split issuance)
                  ├─ Store IdempotencyKey with expiresAt = now + 10 min
                  └─ Audit log
```

**Split issuance:** N travelers → N `BookingAllocation` rows (one per traveler, same cost center).
**Merged allocation:** When multiple bookings share a cost center, allocations can be reported as a combined sum (application-layer query).

Cancel: only the requester can cancel (validated), sets `status = cancelled`, writes audit log.

## Audit Log Design

**Files:** `src/Entity/AuditLog.php`, `src/Service/AuditService.php`, `src/Repository/AuditLogRepository.php`

**Design principles:**
1. **Append-only by contract** — the repository has NO update/delete methods (only `findPaginated`, `countFiltered`, plus inherited `find*`). Explicit comment in source: `// APPEND-ONLY: no update or delete operations permitted.`
2. **Masked values** — all `oldValueMasked` / `newValueMasked` JSON blobs pass through `MaskingService::maskForLog()` before persist, redacting any field containing `phone`/`password`/`token`/`secret`/`key`/`encrypted`/`hash`.
3. **IP last octet masked** in responses via `AuditController::maskIpAddress()` (e.g., `192.168.1.*`).
4. **7-year retention** — records never deleted even when the actor user is data-deleted. Response always includes `retention: "7 years"`.
5. **Immutable flag** — every API response entry includes `"immutable": true`.

**Actions logged:**
- Auth: `LOGIN_SUCCESS`, `LOGOUT`, `ACCOUNT_LOCKED`
- Entity: `CREATE`, `UPDATE`, `DATA_DELETION`
- Approval: `APPROVE`, `REJECT`, `ESCALATE`, `REASSIGN`, `WITHDRAW`
- Work order: `WORK_ORDER_TRANSITION`, `WORK_ORDER_RATE`
- File: `FILE_UPLOAD`
- Attendance: `CSV_IMPORT`, `ATTENDANCE_UPDATED`
- Config: `CONFIG_UPDATE`

**Access:** Only `ROLE_ADMIN` and `ROLE_HR_ADMIN` can call `GET /api/audit/logs`. Employees/supervisors/dispatchers/technicians receive 403.

## Privacy Tiers

Defined in `src/Controller/AuthController::me()` and `src/Controller/AdminController::serializeUserFull()`.

| Field              | ROLE_ADMIN | ROLE_HR_ADMIN | Other roles         |
|--------------------|------------|---------------|---------------------|
| username           | ✓          | ✓             | ✓                   |
| firstName/lastName | ✓          | ✓             | ✓                   |
| email              | ✓          | ✓             | ✓                   |
| phone (decrypted)  | **FULL**   | **FULL**      | `(555) ***-4567`    |
| passwordHash       | —          | —             | —                   |

**Decryption flow:** `User.phoneEncrypted` is stored as AES-256-GCM base64. `EncryptionService::decrypt()` reverses. For non-HR-admin roles, the decrypted value is then passed through `MaskingService::maskPhone()`.

## Data Deletion Process

**File:** `src/Controller/AdminController::deleteUserData()`

User-requested data deletion (GDPR-style):

```
POST /api/admin/users/{id}/delete-data
  │
  ▼
1. Load User entity
2. Capture oldValues for audit log
3. Anonymize in place (do NOT delete row):
     firstName       → "Deleted"
     lastName        → "User"
     email           → "deleted_{id}@removed.invalid"
     phoneEncrypted  → null
     isActive        → false
     deletedAt       → now
4. Write audit log (DATA_DELETION)
   └─ OLD values captured, stored masked for 7 years
5. DO NOT delete:
     - AuditLog rows (retained 7 years)
     - AttendanceRecord rows (policy)
     - PunchEvent rows (policy)
```

Response: `{"message": "User data anonymized. Audit log and attendance records preserved per retention policy."}`.
