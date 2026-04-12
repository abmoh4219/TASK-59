# API Specification — Workforce & Operations Hub

Task ID: TASK-59-W2
Generated from actual implemented code. Base URL: `http://localhost:8000`

## Conventions

- **Content-Type**: `application/json` for all JSON bodies
- **Session auth**: PHP session cookie `PHPSESSID` obtained from `/api/auth/login`
- **CSRF**: `X-CSRF-Token` header required on all state-changing methods (POST, PUT, PATCH, DELETE) EXCEPT `/api/auth/login`, `/api/auth/csrf-token`, `/api/health`
- **Rate limiting**: 60 requests/minute per user for standard endpoints, 10/min for uploads. Exceeded → `429 Too Many Requests` with `Retry-After` header.
- **Error format**: `{"error": "message"}` (also `{"error": "..."}` with HTTP status)
- **Dates**: ISO 8601 format (`YYYY-MM-DDTHH:MM:SS+00:00`) in responses; `YYYY-MM-DD` in date-only query params

## Standard HTTP Status Codes

| Code | Meaning                                                |
|------|--------------------------------------------------------|
| 200  | OK                                                     |
| 201  | Created (resource creation)                            |
| 400  | Bad Request — validation error, state machine violation|
| 401  | Unauthorized — no session / invalid credentials        |
| 403  | Forbidden — CSRF missing/invalid OR role insufficient  |
| 404  | Not Found                                              |
| 423  | Locked — account locked after 5 failed logins         |
| 429  | Too Many Requests — rate limit exceeded                |
| 500  | Internal Server Error                                  |

---

## Auth Endpoints

### POST /api/auth/login
**Role:** Public. **CSRF:** Not required.

```json
Request: { "username": "admin", "password": "Admin@WFOps2024!" }
Response 200:
{
  "user": {
    "id": 1, "username": "admin", "email": "admin@wfops.local",
    "firstName": "System", "lastName": "Administrator",
    "role": "ROLE_ADMIN", "isActive": true, "isOut": false
  },
  "csrfToken": "a1b2c3..."  // 64-char hex
}
```
Errors: `401` invalid credentials, `423` account locked.
Side effects: Creates session, writes `LOGIN_SUCCESS` audit log.

### POST /api/auth/logout
**Role:** Authenticated. **CSRF:** Required.
Response: `{"message": "Logged out successfully"}`
Side effect: invalidates session, writes `LOGOUT` audit log.

### GET /api/auth/me
**Role:** Authenticated. **Rate limited.**
Returns current user + CSRF token. Phone masked for non-HR-Admin roles.

### GET /api/auth/csrf-token
**Role:** Public. Returns fresh CSRF token stored in session.
Response: `{"csrfToken": "..."}`

---

## Health

### GET /api/health
**Role:** Public. **CSRF:** Not required.
Response: `{"status": "ok", "timestamp": "2026-04-13T..."}`

### GET /api/admin/health
**Role:** ROLE_ADMIN.
Response: `{"status": "ok", "timestamp": "...", "role": "ROLE_ADMIN"}`

---

## Attendance Endpoints

### GET /api/attendance/today
**Role:** Authenticated. **Rate limited.**
Response:
```json
{
  "recordDate": "2026-04-13",
  "shiftStart": "09:00", "shiftEnd": "17:00",
  "firstPunchIn": "09:12", "lastPunchOut": "17:05",
  "totalMinutes": 473,
  "exceptions": ["LATE_ARRIVAL"],
  "punches": [
    {"id": 1, "eventTime": "09:12:00", "eventType": "IN"},
    {"id": 2, "eventTime": "17:05:00", "eventType": "OUT"}
  ],
  "rules": [{ "ruleType": "LATE_ARRIVAL", "toleranceMinutes": 5, ... }]
}
```

### GET /api/attendance/{date}
**Role:** Authenticated. Path: `date` in `YYYY-MM-DD` format.

### GET /api/attendance/history?page=1&limit=20&from=YYYY-MM-DD&to=YYYY-MM-DD
**Role:** Authenticated. Paginated list scoped to current user.
Response: `{"data": [...], "total": N, "page": 1, "limit": 20}`

### GET /api/attendance/rules
**Role:** Authenticated. Returns active ExceptionRule config for policy hints.

---

## Exception Request Endpoints

### POST /api/requests
**Role:** Authenticated. **CSRF:** Required. **Rate limited.**
```json
Request: {
  "requestType": "PTO",      // CORRECTION | PTO | LEAVE | BUSINESS_TRIP | OUTING
  "startDate": "2026-04-15", "endDate": "2026-04-15",
  "startTime": "09:00", "endTime": "17:00",
  "reason": "Personal day",
  "clientKey": "uuid-..."     // for idempotency (10-min window)
}
Response 201:
{
  "id": 1, "requestType": "PTO", "status": "PENDING",
  "stepNumber": 1, "filedAt": "2026-04-13T...",
  "steps": [{
    "id": 1, "stepNumber": 1,
    "approverName": "Sarah Mitchell", "approverRole": "ROLE_SUPERVISOR",
    "status": "PENDING",
    "slaDeadline": "2026-04-14T12:00:00+00:00",
    "remainingMinutes": 1440, "actedAt": null
  }]
}
```
Errors: `400` missing field / outside 7-day filing window / invalid requestType.
Idempotency: same `clientKey` within 10 minutes returns the existing request (same ID).

### GET /api/requests
**Role:** Authenticated. Returns current user's requests.

### GET /api/requests/{id}
**Role:** Authenticated. Employees can only see their own requests (403 otherwise).

### POST /api/requests/{id}/withdraw
**Role:** Authenticated. **CSRF:** Required.
Allowed only when: user owns request, status=PENDING, step 1 has no actedAt.
Errors: `400` if already acted on.

### POST /api/requests/{id}/reassign
**Role:** Authenticated. **CSRF:** Required.
Request: `{"newApproverId": 2, "reason": "..."}`

---

## Approval Endpoints

### GET /api/approvals/queue
**Role:** ROLE_SUPERVISOR, ROLE_HR_ADMIN, ROLE_ADMIN. **Rate limited.**
Returns pending approval steps assigned to current approver.
```json
[{
  "stepId": 1, "stepNumber": 1, "requestId": 1, "requestType": "PTO",
  "employeeName": "John Doe", "employeeUsername": "employee",
  "startDate": "2026-04-15", "endDate": "2026-04-15",
  "reason": "Personal day",
  "filedAt": "2026-04-13T...", "slaDeadline": "2026-04-14T12:00:00+00:00",
  "remainingMinutes": 1440, "isOverdue": false
}]
```

### POST /api/approvals/{stepId}/approve
**Role:** Assigned approver. **CSRF:** Required.
Request: `{"comment": "Approved for PTO"}`
Advances to next step or marks request APPROVED if final step.

### POST /api/approvals/{stepId}/reject
**Role:** Assigned approver. **CSRF:** Required.
Request: `{"comment": "Reason for rejection"}`
Marks entire request REJECTED.

### POST /api/approvals/{stepId}/reassign
**Role:** Assigned approver. **CSRF:** Required.
Request: `{"newApproverId": 2, "reason": "..."}`

---

## Work Order Endpoints

### POST /api/work-orders
**Role:** Authenticated. **CSRF:** Required. **Rate limited.**
Supports both JSON body and multipart/form-data (with photos).
```
Fields: category, priority (LOW|MEDIUM|HIGH|URGENT), description, building, room
Files: photos[] (max 5, image/jpeg|image/png, max 10MB each)
```
Response 201: Full WorkOrder object with `status: "submitted"`.
Errors: `400` missing fields, invalid priority, >5 photos, invalid file type/size.

### GET /api/work-orders?status=&priority=&page=
**Role:** Authenticated. Role-filtered:
- Employee: own orders only
- Dispatcher: all orders
- Technician: orders assigned to them
- Admin/HR: all orders
Response: `{"data": [...], "total": N}`

### GET /api/work-orders/{id}
**Role:** Authenticated. Access controlled (employee/technician can only see own/assigned).
Response includes `photos` array with URL paths.

### GET /api/work-orders/technicians
**Role:** ROLE_DISPATCHER, ROLE_HR_ADMIN, ROLE_ADMIN.
Returns list of available technicians for assignment.
```json
[{"id": 6, "name": "Mike Johnson", "isOut": false}]
```

### PATCH /api/work-orders/{id}/status
**Role:** Authenticated (role-checked per transition). **CSRF:** Required.
```json
Request: {
  "status": "dispatched",    // next state in state machine
  "technicianId": 6,         // only for submitted → dispatched
  "notes": "..."             // only for in_progress → completed
}
```
State machine enforced server-side. Returns 400 on invalid transition or wrong role.

### POST /api/work-orders/{id}/rate
**Role:** Submitter (ROLE_EMPLOYEE). **CSRF:** Required.
Request: `{"rating": 5}` (1-5 range)
Constraints: order must be `completed`, within 72 hours of `completedAt`, submitter only.

### GET /api/work-orders/{id}/photos/{photoId}
**Role:** Authenticated (with access to work order). Serves the binary file (image/jpeg or image/png).

---

## Booking & Resource Endpoints

### GET /api/resources
**Role:** Authenticated. **Rate limited.**
List of available bookable resources.
```json
[{
  "id": 1, "name": "Conference Room A", "type": "meeting_room",
  "costCenter": "ADMIN-001", "capacity": 12,
  "isAvailable": true, "description": "..."
}]
```

### GET /api/resources/{id}/availability?date=YYYY-MM-DD
**Role:** Authenticated.
Response: `{"available": true, "bookedSlots": [{"start": "...", "end": "...", "purpose": "..."}]}`

### POST /api/bookings
**Role:** Authenticated. **CSRF:** Required. **Rate limited.**
```json
Request: {
  "resourceId": 1,
  "startDatetime": "2026-06-01T09:00:00Z",
  "endDatetime": "2026-06-01T11:00:00Z",
  "purpose": "Team meeting",
  "travelers": [4, 5, 6],     // User IDs for split issuance
  "clientKey": "uuid-..."      // 10-min idempotency window
}
Response 201:
{
  "id": 1, "resourceName": "Conference Room A",
  "startDatetime": "...", "endDatetime": "...",
  "status": "active",
  "allocations": [
    {"travelerId": 4, "travelerName": "John Doe", "costCenter": "ADMIN-001", "amount": 0},
    ...
  ]
}
```
Errors: `400` time conflict / invalid dates / resource unavailable.
**Idempotency:** same `clientKey` within 10 min returns the existing booking ID.

### GET /api/bookings
**Role:** Authenticated. Returns current user's bookings.

### GET /api/bookings/{id}
**Role:** Authenticated. Employees can only see their own.

### DELETE /api/bookings/{id}
**Role:** Owner of booking. **CSRF:** Required.
Soft-cancels (`status = "cancelled"`). Returns `400` if not owner or already cancelled.

---

## Admin Endpoints

All endpoints under `/api/admin/**` require `ROLE_ADMIN` (enforced by `security.yaml` access_control).
Privileged endpoints additionally support optional `X-Api-Signature` + `X-Timestamp` + `X-Idempotency-Key` for HMAC-SHA256 verification via `ApiSignatureAuthenticator`.

### GET /api/admin/users
**Role:** ROLE_ADMIN.
Returns all users with **full phone** (decrypted) and `deletedAt` status.

### POST /api/admin/users
**Role:** ROLE_ADMIN. **CSRF:** Required.
```json
Request: {
  "username": "newuser", "email": "new@example.com",
  "password": "Strong@2026!",
  "firstName": "New", "lastName": "User",
  "role": "ROLE_EMPLOYEE",
  "phone": "+15551234567"       // Encrypted at rest
}
```
Response 201: Full user object.

### PUT /api/admin/users/{id}
**Role:** ROLE_ADMIN. **CSRF:** Required.
Updatable fields: `role`, `isActive`, `isOut`, `firstName`, `lastName`, `phone`.

### POST /api/admin/users/{id}/delete-data
**Role:** ROLE_ADMIN. **CSRF:** Required.
Anonymizes PII: firstName→"Deleted", lastName→"User", email→"deleted_{id}@removed.invalid", phoneEncrypted→null, deletedAt→now.
Does NOT delete audit log or attendance records.
Response: `{"message": "User data anonymized. Audit log and attendance records preserved per retention policy."}`

### GET /api/admin/config
**Role:** ROLE_ADMIN.
```json
{
  "rules": [{"id": 1, "ruleType": "LATE_ARRIVAL", "toleranceMinutes": 5, ...}],
  "slaHours": 24,
  "businessHoursStart": "08:00", "businessHoursEnd": "18:00",
  "escalationThresholdHours": 2
}
```

### PUT /api/admin/config
**Role:** ROLE_ADMIN. **CSRF:** Required.
Updates ExceptionRule entries. Writes CONFIG_UPDATE audit log per change.

### GET /api/admin/anomaly-alerts
**Role:** ROLE_ADMIN.
Returns FailedLoginAttempt rows from the last 24 hours (up to 100).

---

## Audit Endpoints

### GET /api/audit/logs?entity=&actor=&from=&to=&page=1&limit=25
**Role:** ROLE_ADMIN or ROLE_HR_ADMIN.
Others receive `403 "Access denied — audit log is restricted"`.
```json
{
  "data": [{
    "id": 1, "actorUsername": "admin", "actorId": 1,
    "action": "LOGIN_SUCCESS", "entityType": "User", "entityId": 1,
    "oldValue": null, "newValue": {"username": "admin"},
    "ipAddress": "127.0.0.*",        // last octet masked
    "createdAt": "2026-04-13T...",
    "immutable": true                 // always true — no edit/delete
  }],
  "total": 100,
  "page": 1,
  "limit": 25,
  "retention": "7 years"
}
```

**No POST/PUT/PATCH/DELETE endpoints exist** — audit log is append-only by design. Write access is through `AuditService::log()` only, which is called internally by other services/controllers.

---

## File Upload Multipart Spec

All photo uploads go through `WorkOrderController::create()` (POST /api/work-orders) and `FileUploadService::upload()`:

**Validation:**
| Constraint    | Rule                              |
|---------------|-----------------------------------|
| MIME type     | image/jpeg OR image/png           |
| Extension     | .jpg / .jpeg / .png               |
| Max size      | 10 MB (10,485,760 bytes)          |
| Max per order | 5 photos                          |
| Dedup         | SHA-256 hash per entity           |
| Rate limit    | 10 uploads/minute per user        |

**Storage:** `/app/uploads/WorkOrder/{workOrderId}/{sha256}.{ext}`
**Serving:** `GET /api/work-orders/{id}/photos/{photoId}` (access-controlled binary)

---

## Rate Limit Headers

When request is allowed (under limit): no special headers beyond standard Symfony.
When exceeded (429):
```
HTTP/1.1 429 Too Many Requests
Retry-After: 30   # seconds until next token
Content-Type: application/json

{"error": "Rate limit exceeded"}
```

---

## CSRF Flow Summary

1. Client calls `GET /api/auth/csrf-token` or receives `csrfToken` from `POST /api/auth/login`
2. Client stores the token (frontend uses `localStorage`)
3. For every POST/PUT/PATCH/DELETE, client includes `X-CSRF-Token: <token>` header
4. `CsrfListener` (kernel.request priority 10) validates via `hash_equals()` against session
5. Mismatch → `403 {"error": "CSRF token invalid or missing"}`
6. Exempt paths: `/api/auth/login`, `/api/auth/csrf-token`, `/api/health`

Session cookie: `PHPSESSID`, HttpOnly, SameSite=lax.

---

## Seeded Credentials (from repo/backend/src/DataFixtures/AppFixtures.php)

| Role                 | Username    | Password           |
|----------------------|-------------|--------------------|
| System Administrator | admin       | Admin@WFOps2024!   |
| HR Admin             | hradmin     | HRAdmin@2024!      |
| Supervisor           | supervisor  | Super@2024!        |
| Employee             | employee    | Emp@2024!          |
| Dispatcher           | dispatcher  | Dispatch@2024!     |
| Technician           | technician  | Tech@2024!         |
