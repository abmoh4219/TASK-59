# PLAN.md — Workforce & Operations Hub Execution Plan
# Task ID: TASK-59-W2
# [ ] = pending  [x] = complete
# Rule: Complete ALL tasks in a phase without stopping. Pause ONLY at phase boundaries.
# Fix PHP/TypeScript errors within the same task before marking [x].
# CRITICAL: QA reads code (static) AND runs Docker (runtime). Both must be perfect.
# TEST RULE: Both backend AND frontend have tests/unit_tests/ and tests/api_tests/.

---

## PHASE 0 — Project Foundation, Docker & Scaffolding
> Goal: Both apps scaffold, Docker builds, .env.example committed, test folders created
> Complete all tasks continuously, then pause. Wait for "proceed".

- [x] 0.1 Create repo/.gitignore (content from CLAUDE.md)
- [x] 0.2 Create repo/.env.example (content from CLAUDE.md — committed, not in .gitignore)
- [x] 0.3 Create repo/README.md (minimal format from CLAUDE.md)
- [x] 0.4 Create repo/run_tests.sh (exact content from CLAUDE.md — chmod +x)
- [x] 0.5 Create repo/docker-compose.yml — single file with services: setup (alpine copy .env), mysql (8.0 healthcheck), backend (PHP-FPM + Nginx port 8000), frontend (Node build + Nginx port 3000), test (profile:test runs run_tests.sh), mysql-test (profile:test port 3307 separate DB)
- [x] 0.6 Create repo/backend/Dockerfile — php:8.2-fpm, install extensions (pdo_mysql, intl, sodium, opcache), install composer, copy app, run composer install --no-dev, configure PHP-FPM + Nginx
- [x] 0.7 Create repo/frontend/Dockerfile — node:20-alpine build stage (npm ci + npm run build), nginx:alpine serve stage (copy dist, nginx.conf)
- [x] 0.8 Create repo/nginx/nginx.conf — serves frontend on port 3000, proxies /api to backend:8000
- [x] 0.9 Scaffold Symfony 7 backend: composer create-project symfony/skeleton backend, install all packages from CLAUDE.md (security-bundle, doctrine-bundle, rate-limiter, scheduler, league/csv, phpunit)
- [x] 0.10 Scaffold React frontend: npm create vite@latest frontend -- --template react-ts, install all packages from CLAUDE.md (react-router-dom, axios, @tanstack/react-query, tailwindcss, @radix-ui, react-hook-form, zod, date-fns, vitest)
- [x] 0.11 Configure Tailwind with dark SaaS theme from CLAUDE.md (colors: slate bg, indigo accent, custom badge colors)
- [x] 0.12 Create backend test folder skeleton:
       backend/tests/unit_tests/AttendanceEngineTest.php — placeholder testPlaceholder()
       backend/tests/unit_tests/ExceptionDetectionTest.php — placeholder
       backend/tests/unit_tests/SlaServiceTest.php — placeholder
       backend/tests/unit_tests/MaskingServiceTest.php — placeholder
       backend/tests/unit_tests/EncryptionServiceTest.php — placeholder
       backend/tests/unit_tests/BookingIdempotencyTest.php — placeholder
       backend/tests/unit_tests/WorkOrderStateMachineTest.php — placeholder
       backend/tests/api_tests/AuthApiTest.php — placeholder
       backend/tests/api_tests/AttendanceApiTest.php — placeholder
       backend/tests/api_tests/ExceptionRequestApiTest.php — placeholder
       backend/tests/api_tests/ApprovalApiTest.php — placeholder
       backend/tests/api_tests/WorkOrderApiTest.php — placeholder
       backend/tests/api_tests/BookingApiTest.php — placeholder
       backend/tests/api_tests/AuditApiTest.php — placeholder
- [x] 0.13 Create frontend test folder skeleton:
       frontend/tests/unit_tests/maskPhone.test.ts — placeholder test
       frontend/tests/unit_tests/policyHints.test.ts — placeholder
       frontend/tests/unit_tests/slaCountdown.test.ts — placeholder
       frontend/tests/unit_tests/timeIncrement.test.ts — placeholder
       frontend/tests/api_tests/auth.api.test.ts — placeholder
       frontend/tests/api_tests/attendance.api.test.ts — placeholder
       frontend/tests/api_tests/workorder.api.test.ts — placeholder
- [x] 0.14 Create frontend/src/api/client.ts — axios instance with baseURL=http://localhost:8000/api, withCredentials:true (session cookie), request interceptor to attach X-CSRF-Token from cookie, response interceptor to redirect to /login on 401
- [x] 0.15 Create frontend/src/context/AuthContext.tsx — user state, login(), logout(), current role, CSRF token management
- [x] 0.16 Create frontend/src/components/layout/Sidebar.tsx — role-gated nav. Each role sees ONLY:
       ROLE_ADMIN: Dashboard, Attendance, Approvals, Work Orders, Bookings, Admin (Users/Audit/CSV/Config)
       ROLE_SUPERVISOR: Dashboard, Attendance, Approvals
       ROLE_HR_ADMIN: Dashboard, Attendance, Approvals, Admin (Users/Config)
       ROLE_DISPATCHER: Dashboard, Work Orders
       ROLE_TECHNICIAN: Dashboard, Work Orders
       ROLE_EMPLOYEE: Dashboard, Attendance, Work Orders, Bookings
- [x] 0.17 Verify: docker compose up --build starts both services with no build errors. Frontend loads at :3000, backend health check passes at :8000/api/health.

**Phase 0 checkpoint: docker compose up --build succeeds. Frontend at :3000, API at :8000. Both test folder trees created. Sidebar nav is role-gated.**

---

## PHASE 1 — Database Schema (Doctrine Entities + Migrations)
> Goal: All entities created, migrations run in Docker, seed data for all 6 roles
> Complete all tasks continuously, then pause. Wait for "proceed".

- [ ] 1.1 Create Entity/User.php — id, username, email, passwordHash, role (ENUM), firstName, lastName, phoneEncrypted (AES-encrypted), isActive, backupApproverId, isOut (bool, for reassignment), failedLoginCount, lockedUntil, deletedAt (soft delete), createdAt, updatedAt. Add #[ORM\Column] annotations with proper types.
- [ ] 1.2 Create Entity/ShiftSchedule.php — id, userId, dayOfWeek, shiftStart (time), shiftEnd (time), isActive
- [ ] 1.3 Create Entity/PunchEvent.php — id, userId, eventDate, eventTime, eventType (IN/OUT), source (CSV/MANUAL), importedAt
- [ ] 1.4 Create Entity/AttendanceRecord.php — id, userId, recordDate, firstPunchIn, lastPunchOut, totalMinutes, exceptions (JSON array of exception types), generatedAt
- [ ] 1.5 Create Entity/ExceptionRule.php — id, ruleType, toleranceMinutes (default 5), missedPunchWindowMinutes (default 30), filingWindowDays (default 7), isActive, updatedBy, updatedAt
- [ ] 1.6 Create Entity/AttendanceException.php — id, attendanceRecordId, exceptionType (LATE_ARRIVAL/EARLY_LEAVE/MISSED_PUNCH/ABSENCE/APPROVED_OFFSITE), detectedAt, resolvedAt, resolvedBy
- [ ] 1.7 Create Entity/ExceptionRequest.php — id, userId, requestType (CORRECTION/PTO/LEAVE/BUSINESS_TRIP/OUTING), startDate, endDate, startTime, endTime, reason, status (PENDING/APPROVED/REJECTED/WITHDRAWN), currentApproverId, stepNumber, clientKey (idempotency), filedAt, updatedAt
- [ ] 1.8 Create Entity/ApprovalStep.php — id, exceptionRequestId, stepNumber (1-3), approverId, backupApproverId, status, slaDeadline, escalatedAt, actedAt
- [ ] 1.9 Create Entity/ApprovalAction.php — id, approvalStepId, actorId, action (APPROVE/REJECT/ESCALATE/REASSIGN/WITHDRAW), comment, actedAt
- [ ] 1.10 Create Entity/WorkOrder.php — id, submittedById, category, priority (LOW/MEDIUM/HIGH/URGENT), description, building, room, status (state machine), assignedDispatcherId, assignedTechnicianId, dispatchedAt, acceptedAt, startedAt, completedAt, ratedAt, rating (1-5 nullable), completionNotes, createdAt, updatedAt
- [ ] 1.11 Create Entity/WorkOrderPhoto.php — id, workOrderId, originalFilename, storedPath, mimeType, sizeBytes, sha256Hash, uploadedAt
- [ ] 1.12 Create Entity/Resource.php — id, name, type, costCenter, capacity, isAvailable, description
- [ ] 1.13 Create Entity/Booking.php — id, requesterId, resourceId, startDatetime, endDatetime, purpose, status, clientKey, allocations (JSON), createdAt
- [ ] 1.14 Create Entity/IdempotencyKey.php — id, clientKey (unique), entityType, entityId, expiresAt, createdAt
- [ ] 1.15 Create Entity/FileUpload.php — id, uploaderId, entityType, entityId, originalFilename, storedPath, mimeType, sizeBytes, sha256Hash, uploadedAt
- [ ] 1.16 Create Entity/AuditLog.php — id, actorId, actorUsername, action, entityType, entityId, oldValueMasked (JSON), newValueMasked (JSON), ipAddress, userAgent, createdAt. NO updatedAt. This is append-only.
- [ ] 1.17 Create Entity/FailedLoginAttempt.php — id, username, ipAddress, attemptedAt
- [ ] 1.18 Generate initial migration: php bin/console doctrine:migrations:diff. Verify SQL is correct.
- [ ] 1.19 Create DataFixtures or migration seed: INSERT 6 users (one per role) with bcrypt hashed passwords, basic shift schedules, sample exception rules, sample resources, sample work orders in various states, sample attendance records.
- [ ] 1.20 Verify: docker compose up --build → migrations run → all 6 logins work → MySQL has all tables.

**Phase 1 checkpoint: docker compose up --build → migrations run → all 6 credentials log in successfully → MySQL tables visible.**

---

## PHASE 2 — Auth, Security & Middleware
> Goal: Login works for all 6 roles, CSRF active, rate limiting, lockout, encryption — all explicitly coded
> Complete all tasks continuously, then pause. Wait for "proceed".

- [ ] 2.1 Create src/Security/LoginFormAuthenticator.php — Symfony authenticator: validate credentials, bcrypt verify, check isActive and lockedUntil, on success create session + CSRF token + write audit log, on failure call AnomalyDetectionService.recordFailedLogin()
- [ ] 2.2 Create src/Service/AnomalyDetectionService.php — recordFailedLogin(username, ip): INSERT into failed_login_attempts; if count >= 5 in last 15 min → set user.lockedUntil = now+15min + write ACCOUNT_LOCKED audit log; isLockedOut(username): check lockedUntil > NOW()
- [ ] 2.3 Create src/EventListener/CsrfListener.php — onKernelRequest: for POST/PUT/PATCH/DELETE requests, read X-CSRF-Token header, compare to session token using hash_equals(), return JsonResponse 403 if mismatch. Skip /api/auth/login.
- [ ] 2.4 Create src/Service/RateLimitService.php — checkStandardLimit(userId): 60 req/min using Symfony RateLimiter (token_bucket policy, keyed by user ID); checkUploadLimit(userId): 10 uploads/min; return false + write header Retry-After when exceeded
- [ ] 2.5 Create src/Service/EncryptionService.php — encrypt(plaintext): sodium_crypto_aead_aes256gcm_encrypt with random nonce, return base64(nonce+ciphertext); decrypt(encoded): reverse. Key from APP_ENCRYPTION_KEY env. Explicit doc comment explaining AES-256-GCM usage.
- [ ] 2.6 Create src/Service/MaskingService.php — maskPhone(phone): format as (NXX) ***-XXXX showing only area code and last 4; maskForLog(data): redact keys phone/password/token in arrays; used in ALL API responses for non-HR_ADMIN roles and ALL audit log entries.
- [ ] 2.7 Create src/Security/ApiSignatureAuthenticator.php — for /api/admin/** endpoints: read X-Api-Signature + X-Timestamp headers, verify HMAC-SHA256(method+path+timestamp+body_hash, APP_SIGNING_KEY), check timestamp within ±5min, check nonce not reused (IdempotencyKey table), return 401 if invalid.
- [ ] 2.8 Create src/Service/AuditService.php — log(actor, action, entityType, entityId, oldData, newData, request): persist AuditLog entity. maskForLog() applied to old/new data. ONLY method is log(). No update, no delete, no find-and-modify. Comment: "Append-only by design."
- [ ] 2.9 Create src/Controller/AuthController.php — POST /api/auth/login (no CSRF check), POST /api/auth/logout, GET /api/auth/me (returns user + role + CSRF token), GET /api/auth/csrf-token
- [ ] 2.10 Create src/Controller/AdminController.php stub — GET /api/admin/health → {"status":"ok","timestamp":"..."}. Protected by ROLE_ADMIN + API signature.
- [ ] 2.11 Create frontend/src/pages/Login.tsx — Premium dark login page: centered card on dark background, company logo/wordmark at top, username + password inputs with dark styling, indigo "Sign In" button with loading state, error message for wrong credentials and locked account, CSRF token fetched before submit.
- [ ] 2.12 Fill in backend/tests/unit_tests/MaskingServiceTest.php — testMaskPhoneFullNumber(), testMaskPhoneShortNumber(), testMaskForLogRedactsPassword(), testMaskForLogRedactsPhone()
- [ ] 2.13 Fill in backend/tests/unit_tests/EncryptionServiceTest.php — testEncryptDecryptRoundtrip(), testDifferentNonceEachEncryption(), testWrongKeyFailsDecryption()
- [ ] 2.14 Fill in backend/tests/api_tests/AuthApiTest.php — testLoginSuccess(), testLoginWrongPassword401(), testLoginLockedAccount423(), testLogoutClearSession(), testCsrfMissingOnProtectedRoute403(), testRateLimitExceeded429()
- [ ] 2.15 Fill in frontend/tests/unit_tests/maskPhone.test.ts — test full US number masking, test short number, test null input
- [ ] 2.16 Verify: docker compose up --build → all 6 logins succeed → wrong password returns 401 → POST without CSRF returns 403 → login page looks premium and polished.

**Phase 2 checkpoint: All 6 credentials log in → correct role dashboard shown. CSRF enforced. Lockout works after 5 failures. Login page is visually stunning.**

---

## PHASE 3 — Attendance Engine & Exception Detection
> Goal: Attendance card shows real data, exception detection runs, nightly import command works
> Complete all tasks continuously, then pause. Wait for "proceed".

- [ ] 3.1 Create src/Service/ExceptionDetectionService.php — detectExceptions(userId, date, punches, schedule, rules): pure function, deterministic. Checks: LATE_ARRIVAL (firstPunch > shiftStart + toleranceMin), EARLY_LEAVE (lastPunch < shiftEnd - toleranceMin), MISSED_PUNCH (no punch within 30min of shiftStart), ABSENCE (no punches at all), APPROVED_OFFSITE (has approved trip/outing covering date). Returns array of exception types with details.
- [ ] 3.2 Create src/Service/AttendanceEngineService.php — processDate(date): loads all active users + their schedules + punch events for date; for each user calls detectExceptions(); upserts AttendanceRecord with exceptions JSON; writes audit log for any changes. Deterministic: calling twice produces same result.
- [ ] 3.3 Create src/Command/ImportAttendanceCsvCommand.php — php bin/console app:import-attendance --file=path.csv. Parses CSV (columns: employee_id, date MM/DD/YYYY, event_type IN/OUT, time HH:MM:SS). Validates each row. Inserts PunchEvent records (skips duplicates). Writes audit log for import. Shows progress and error summary.
- [ ] 3.4 Create src/Command/ProcessAttendanceEngineCommand.php — php bin/console app:process-attendance --date=YYYY-MM-DD (defaults to yesterday). Calls AttendanceEngineService::processDate(). Configures Symfony Scheduler to run at 2:00 AM daily.
- [ ] 3.5 Create src/Controller/AttendanceController.php — GET /api/attendance/today (current user's attendance card), GET /api/attendance/{date} (specific date), GET /api/attendance/history?from=&to= (paginated list), POST /api/admin/attendance/import (CSV upload, ADMIN only). Rate limited.
- [ ] 3.6 Create frontend/src/components/attendance/AttendanceCard.tsx — Today's card showing: date, shift time, punch events timeline (in/out pairs with times), total hours worked, exception flags as colored badges (LATE=amber, MISSED=red, ABSENT=red, EARLY=orange, OFFSITE=green). Loading skeleton while fetching.
- [ ] 3.7 Create frontend/src/components/attendance/PolicyHint.tsx — displays contextual policy text based on exception type: "Late arrival: after 9:05 AM (5-minute tolerance)", "Missed punch: no event within 30 minutes of shift start", "Requests must be filed within 7 calendar days of the exception". Text is dynamic from ExceptionRule config.
- [ ] 3.8 Create frontend/src/pages/attendance/AttendancePage.tsx — shows AttendanceCard for today, PolicyHint if exception present, "Submit Request" button if exception within 7-day window, history list below card. All data from real API.
- [ ] 3.9 Fill in backend/tests/unit_tests/AttendanceEngineTest.php — testLateArrivalDetected(), testOnTimeNotFlagged(), testMissedPunchDetected(), testAbsenceDetected(), testApprovedOffsiteNotFlagged(), testEngineIsDeterministic(), testToleranceConfigurable()
- [ ] 3.10 Fill in backend/tests/unit_tests/ExceptionDetectionTest.php — testLateArrivalThreshold(), testEarlyLeaveThreshold(), testMissedPunchWindow30Min(), testAllExceptionsCanCoexist()
- [ ] 3.11 Fill in backend/tests/api_tests/AttendanceApiTest.php — testGetTodayAttendanceCard(), testGetHistoryPaginated(), testEmployeeCannotSeeOtherEmployeeCard(), testCsvImportAdminOnly(), testCsvImportInvalidFormat()
- [ ] 3.12 Fill in frontend/tests/unit_tests/policyHints.test.ts — testLateHintText(), testMissedPunchHintText(), testFilingWindowText(), testHintUpdatesWithTolerance()

**Phase 3 checkpoint: QA logs in as employee → attendance card shows today's data → exception badges visible → policy hints shown → history loads. CSV import command works.**

---

## PHASE 4 — Exception Requests & Approval Workflow
> Goal: Full request-to-approval flow works, SLA countdown visible, escalation logic coded
> Complete all tasks continuously, then pause. Wait for "proceed".

- [ ] 4.1 Create src/Service/SlaService.php — calculateSlaDeadline(startTime, slaHours, businessHoursConfig): adds N business hours (Mon-Fri 8AM-6PM configurable). isOverdue(step): NOW() > step.slaDeadline. getRemainingMinutes(step): business minutes until deadline. getBackupApprover(userId): returns user.backupApproverId or HR Admin fallback.
- [ ] 4.2 Create src/Service/ApprovalWorkflowService.php — createRequest(userId, type, data, clientKey): check idempotency (10min window by clientKey), validate 7-day filing window, create ExceptionRequest + ApprovalStep records (up to 3 steps based on type), write audit log. approve(stepId, actorId, comment): validate actor is assigned approver, advance workflow, if last step → mark APPROVED, write audit. reject(stepId, actorId, comment): mark REJECTED, write audit. escalate(stepId): assign backup approver, write audit. withdraw(requestId, userId): only if step 1 not yet acted → mark WITHDRAWN, write audit. reassign(stepId, newApproverId, reason): mark old step reassigned, write audit.
- [ ] 4.3 Create src/Command/EscalateOverdueApprovalsCommand.php — php bin/console app:escalate-approvals. Finds ApprovalStep records where isOverdue() AND escalatedAt IS NULL AND step.slaDeadline + 2 hours < NOW(). For each: calls ApprovalWorkflowService::escalate(). Runs every 15 minutes via Symfony Scheduler.
- [ ] 4.4 Create src/Controller/ExceptionRequestController.php — POST /api/requests (create, idempotent by clientKey), GET /api/requests (my requests list), GET /api/requests/{id} (detail with timeline), POST /api/requests/{id}/withdraw, POST /api/requests/{id}/reassign. Rate limited.
- [ ] 4.5 Create src/Controller/ApprovalController.php — GET /api/approvals/queue (pending steps for current approver, with remaining SLA time), POST /api/approvals/{stepId}/approve, POST /api/approvals/{stepId}/reject, POST /api/approvals/{stepId}/reassign. Supervisor: step 1 only. HR Admin: all steps.
- [ ] 4.6 Create frontend/src/pages/attendance/ExceptionRequestForm.tsx — multi-field form: request type select (CORRECTION/PTO/LEAVE/BUSINESS_TRIP/OUTING), date range picker (MM/DD/YYYY format), time window select (15-minute increments from 00:00 to 23:45), reason textarea. Shows policy hints inline. Shows remaining filing days ("5 days remaining to file"). Submit button generates clientKey UUID before submit (idempotency). Loading and success states.
- [ ] 4.7 Create frontend/src/components/attendance/ApprovalTimeline.tsx — live status timeline showing: each approval step as a node, current step highlighted with indigo glow, completed steps with checkmark, pending steps with clock icon. Shows: approver name, role, SLA deadline, time remaining (e.g., "18h 23m remaining"). Overdue steps shown in red. Auto-refreshes every 60 seconds.
- [ ] 4.8 Create frontend/src/pages/attendance/RequestDetailPage.tsx — full request detail: submitted info, approval timeline, action buttons (Withdraw if pending+step1 not acted, Reassign if approver marked out). All data from real API.
- [ ] 4.9 Create frontend/src/pages/approvals/ApprovalQueuePage.tsx — list of pending approvals for current approver. Each row: employee name, request type, exception date, request filed date, SLA remaining (colored: green > 12h, amber 4-12h, red < 4h). Approve/Reject buttons open confirmation modal with comment field.
- [ ] 4.10 Fill in backend/tests/unit_tests/SlaServiceTest.php — testSlaDeadlineCalculation(), testBusinessHoursSkipWeekend(), testIsOverdue(), testRemainingMinutes(), testEscalationThreshold()
- [ ] 4.11 Fill in backend/tests/api_tests/ExceptionRequestApiTest.php — testCreateRequestSuccess(), testCreateRequestIdempotent(), testCreateRequestOutsideFilingWindow(), testWithdrawBeforeFirstApproval(), testCannotWithdrawAfterApproval(), testEmployeeCannotSeeOtherRequests()
- [ ] 4.12 Fill in backend/tests/api_tests/ApprovalApiTest.php — testSupervisorApproveStep1(), testHrAdminApproveStep2(), testSupervisorCannotApproveStep2(), testAutoEscalationAfterSla(), testReassignApprover()
- [ ] 4.13 Fill in frontend/tests/unit_tests/slaCountdown.test.ts — testSlaCountdownColors(), testOverdueDisplay(), testRemainingHoursCalc()
- [ ] 4.14 Fill in frontend/tests/unit_tests/timeIncrement.test.ts — test15MinuteIncrements(), testStartEndValidation(), testMmDdYyyyFormat()

**Phase 4 checkpoint: QA as employee submits exception request → approval timeline shows → QA as supervisor sees queue with SLA countdown → approve/reject works → timeline updates. Withdraw works before step 1 acted.**

---

## PHASE 5 — Work Orders (Facilities)
> Goal: Full work order lifecycle works, state machine enforced, photos upload, rating works
> Complete all tasks continuously, then pause. Wait for "proceed".

- [ ] 5.1 Create src/Service/WorkOrderService.php — create(userId, data, photos): validate, create WorkOrder entity, handle photo uploads via FileUploadService, write audit log. transition(workOrderId, newStatus, actorId, notes): validate state machine (submitted→dispatched DISPATCHER only, dispatched→accepted TECHNICIAN only etc.), update status + timestamps, write audit log. rate(workOrderId, userId, rating): validate 72-hour window from completedAt, validate 1-5 range, save rating, write audit log. getQueue(role, userId): returns appropriate list for role.
- [ ] 5.2 Create src/Service/FileUploadService.php — upload(file, entityType, entityId, uploaderId): validate MIME type (image/jpeg, image/png only), validate extension (.jpg/.jpeg/.png), validate size (max 10MB = 10485760 bytes), compute SHA-256 hash, check for duplicate hash (reject if exists for same entity), check upload rate limit (10/min), encrypt filename, store to /app/uploads/{entityType}/{entityId}/, save FileUpload entity, write audit log. getSignedUrl(fileId): generate time-limited URL for serving photo.
- [ ] 5.3 Create src/Controller/WorkOrderController.php — POST /api/work-orders (submit, max 5 photos), GET /api/work-orders (role-filtered list), GET /api/work-orders/{id} (detail + photos + history), PATCH /api/work-orders/{id}/status (transition), POST /api/work-orders/{id}/rate, GET /api/work-orders/{id}/photos/{photoId} (serve photo). Rate limited.
- [ ] 5.4 Create frontend/src/pages/workorders/WorkOrderForm.tsx — form with: category select (Plumbing/Electrical/HVAC/General/Other), priority select (LOW/MEDIUM/HIGH/URGENT) with color indicators, description textarea (min 20 chars), building select + room input, photo upload area (drag-drop or click, shows thumbnails with remove button, max 5, shows count "3/5 photos"), submit button. All validation shown inline. Photo upload shows progress per file.
- [ ] 5.5 Create frontend/src/pages/workorders/WorkOrderListPage.tsx — table/card list filtered by role: employee sees own orders, dispatcher sees all unassigned + assigned, technician sees assigned to them. Status badges colored. Filter by status, priority, date. Pagination.
- [ ] 5.6 Create frontend/src/pages/workorders/WorkOrderDetailPage.tsx — full detail: status badge + state machine progress bar showing all steps, submitted info, photos gallery (grid of thumbnails), assignment info (dispatcher/technician), status history timeline, completion notes. Role-specific action buttons: Dispatcher sees "Assign Technician" dropdown, Technician sees "Update Status" + "Add Notes", Employee sees "Rate Work" (only after completed, within 72h) — star rating 1-5. All real API data.
- [ ] 5.7 Fill in backend/tests/unit_tests/WorkOrderStateMachineTest.php — testValidTransitionSubmittedToDispatched(), testInvalidTransitionSubmittedToCompleted(), testOnlyDispatcherCanDispatch(), testOnlyTechnicianCanAccept(), testRatingWindowValid(), testRatingWindowExpired(), testDuplicatePhotoHashRejected()
- [ ] 5.8 Fill in backend/tests/api_tests/WorkOrderApiTest.php — testSubmitWorkOrder(), testSubmitMoreThan5PhotosRejected(), testPhotoExceedsSize(), testInvalidMimeType(), testDispatcherAssignsTechnician(), testTechnicianUpdatesStatus(), testRateWithin72Hours(), testRateAfter72HoursRejected(), testEmployeeCannotSeeOtherOrders()
- [ ] 5.9 Fill in frontend/tests/api_tests/workorder.api.test.ts — testWorkOrderCreateRequest(), testWorkOrderListFiltering(), testPhotoUploadValidation()

**Phase 5 checkpoint: QA submits work order with 3 photos → dispatcher assigns technician → technician updates status → completes → employee rates within 72h. State machine blocks invalid transitions.**

---

## PHASE 6 — Booking & Resource Allocation
> Goal: Booking flow works, idempotency enforced, merged/split allocations work
> Complete all tasks continuously, then pause. Wait for "proceed".

- [ ] 6.1 Create src/Service/BookingService.php — createBooking(userId, resourceId, data, clientKey, travelers): check IdempotencyKey (10min window → return existing if found), validate resource availability, create Booking entity, create BookingAllocation records (one per traveler = split issuance), check if same cost center has other bookings → merge into combined allocation, write audit log. cancelBooking(bookingId, userId): validate ownership + status, cancel, write audit log. getAvailability(resourceId, date): returns available time slots.
- [ ] 6.2 Create src/Controller/BookingController.php — GET /api/resources (list bookable resources), GET /api/resources/{id}/availability?date=, POST /api/bookings (create, idempotent), GET /api/bookings (my bookings), GET /api/bookings/{id}, DELETE /api/bookings/{id} (cancel). Rate limited.
- [ ] 6.3 Create frontend/src/pages/bookings/BookingPage.tsx — resource catalog (cards for each bookable resource with type/capacity/cost-center), availability calendar/grid for selected resource (shows booked vs available slots), booking form (select travelers from employee list, date/time, purpose, cost center), submit with clientKey UUID pre-generated. Shows existing bookings list. Loading skeletons. Empty state if no resources.
- [ ] 6.4 Fill in backend/tests/unit_tests/BookingIdempotencyTest.php — testSameClientKeyWithin10MinReturnsExisting(), testSameClientKeyAfter10MinCreatesNew(), testDifferentClientKeyCreatesNew(), testSplitIssuanceCreatesOneAllocationPerTraveler(), testMergedAllocationSameCostCenter()
- [ ] 6.5 Fill in backend/tests/api_tests/BookingApiTest.php — testCreateBookingSuccess(), testIdempotentBooking(), testResourceUnavailableConflict(), testCancelOwnBooking(), testCannotCancelOthersBooking()

**Phase 6 checkpoint: QA creates booking for resource → duplicate submit returns same booking → cancellation works → allocations visible in detail.**

---

## PHASE 7 — Admin, Audit Log & Privacy
> Goal: Admin panel works, audit log immutable, data deletion, retention, anomaly alerts
> Complete all tasks continuously, then pause. Wait for "proceed".

- [ ] 7.1 Create src/Controller/AuditController.php — GET /api/audit/logs?entity=&actor=&from=&to= (ADMIN + HR_ADMIN only, paginated, masked values shown). Confirm AuditLogRepository has NO update/delete methods — only findBy/findOneBy/findPaginated. Add comment in repository: "// APPEND-ONLY: no update or delete operations permitted."
- [ ] 7.2 Create src/Controller/AdminController.php (full) — GET/POST/PUT /api/admin/users (ADMIN only, full identity visible), GET /api/admin/config (system config like SLA hours, business hours, tolerances), PUT /api/admin/config, POST /api/admin/attendance/import, GET /api/admin/anomaly-alerts, POST /api/admin/users/{id}/delete-data (anonymize PII where legally allowed). All protected by ROLE_ADMIN + API signature.
- [ ] 7.3 Implement data deletion: AdminController::deleteUserData() — anonymize firstName to "Deleted", lastName to "User", email to "deleted_{id}@removed.invalid", phoneEncrypted to null, set deletedAt. Write audit log. Do NOT delete AuditLog records (retained 7 years). Do NOT delete attendance records (retain per policy).
- [ ] 7.4 Verify tiered identity access: AttendanceController and all others — phone field masked via MaskingService in response for all roles except HR_ADMIN. HR_ADMIN gets decrypted + full phone. Add integration test verifying this.
- [ ] 7.5 Create frontend/src/pages/admin/AuditLogPage.tsx — paginated table: timestamp, actor, action, entity type, entity ID, IP (masked last octet), changes summary (masked). Filter by entity type, actor, date range. Export button (CSV). No edit/delete UI anywhere — badge saying "IMMUTABLE RECORD".
- [ ] 7.6 Create frontend/src/pages/admin/UserManagementPage.tsx — full user list for ADMIN: create user form (all fields including role), edit user, deactivate/activate toggle, role change (confirmation modal), "Delete Data" button (with strong warning modal "This action anonymizes PII and cannot be undone"). Phone shown full for ADMIN, masked for others.
- [ ] 7.7 Create frontend/src/pages/admin/CsvImportPage.tsx — drag-drop CSV upload zone, format guide ("Required columns: employee_id, date (MM/DD/YYYY), event_type (IN/OUT), time (HH:MM:SS)"), upload progress, result summary (imported: N, skipped: M, errors: list), recent import history table.
- [ ] 7.8 Create frontend/src/pages/admin/SystemConfigPage.tsx — form for configurable settings: tolerance minutes (default 5), missed punch window (default 30), SLA hours (default 24), business hours (start/end time), escalation threshold (default 2 hours), filing window days (default 7). Save writes to ExceptionRule table.
- [ ] 7.9 Fill in backend/tests/api_tests/AuditApiTest.php — testAuditLogAppendsOnly(), testAuditLogImmutable(), testHrAdminCanReadAuditLog(), testEmployeeCannotReadAuditLog(), testPhoneOnlyFullForHrAdmin(), testPhoneMaskedForSupervisor(), testDataDeletionAnonymizesPii(), testAuditRecordNotDeletedAfterUserDeletion()

**Phase 7 checkpoint: QA as admin → audit log shows all actions → no edit/delete buttons anywhere on audit page → phone shown masked for supervisor → HR Admin sees full phone → delete data anonymizes PII.**

---

## PHASE 8 — Complete Frontend UI (All Pages, Premium Dark SaaS Theme)
> Goal: Every page is stunning, every role has correct sidebar, everything works in Docker
> QA manually clicks every page — must look and feel like a premium paid product.
> Complete all tasks continuously, then pause. Wait for "proceed".

- [ ] 8.1 Create frontend/src/components/layout/TopBar.tsx — sticky top bar with: breadcrumb navigation, page title, notification bell (anomaly alerts for admin), user avatar with role badge, sign out button. Dark slate background, subtle border-bottom.
- [ ] 8.2 Create frontend/src/components/layout/Layout.tsx — wrapper: sidebar (240px, fixed, dark gradient) + main content area (scroll, light dark bg). Sidebar shows ScholarVault→ "Workforce Hub" logo at top. Mobile-responsive hamburger.
- [ ] 8.3 Create frontend/src/components/ui/ complete set — all styled per CLAUDE.md design system:
       Button.tsx: Primary (indigo gradient), Secondary (bordered), Danger (red-tinted), Ghost — all with loading spinner
       Card.tsx: dark surface, 1px border, rounded-xl, hover shadow
       Badge.tsx: exception badges (LATE=amber, MISSED=red, ABSENT=red, EARLY=orange, OFFSITE=green), work order status badges (all from CLAUDE.md), role badges
       Modal.tsx: dark overlay, escape-to-close, focus trap
       Table.tsx: dark alternating rows, sticky header, sort indicators, pagination
       Skeleton.tsx: shimmer animation
       EmptyState.tsx: centered icon + heading + action button
       Timeline.tsx: vertical step timeline with status icons
- [ ] 8.4 Create frontend/src/pages/Dashboard.tsx — role-specific dashboards:
       Employee: today's attendance card summary, any pending exception requests, recent work orders
       Supervisor: pending approval count badge, overdue items in red, quick approve links
       HR Admin: pending policy overrides, anomaly alerts, user stats
       Dispatcher: unassigned work orders count, high priority items
       Technician: assigned work orders, in-progress items
       Admin: system health cards (total users, today's exceptions, pending approvals, open work orders)
- [ ] 8.5 Final pass — ALL pages loading/empty/error states — verify every page has skeleton, empty state, error retry
- [ ] 8.6 Final pass — Sidebar role-gating verified — each of the 6 roles only sees their permitted items. Test by logging in as each role.
- [ ] 8.7 Final pass — All forms submit to real API. Verify no form has an onClick that does console.log or nothing. All mutations use axios POST/PATCH/DELETE with real endpoints.
- [ ] 8.8 Final pass — Mobile responsive check — sidebar collapses on mobile, tables scroll horizontally, forms stack vertically.
- [ ] 8.9 Fill in frontend/tests/api_tests/auth.api.test.ts — testLoginSuccess(), testLoginFails401(), testCsrfAttachedToRequests(), testLogoutClearsSession()
- [ ] 8.10 Fill in frontend/tests/api_tests/attendance.api.test.ts — testFetchTodayCard(), testSubmitExceptionRequest(), testFetchRequestTimeline()
- [ ] 8.11 Verify: docker compose up --build → all 6 logins work → each role sees correct sidebar → dashboard shows real data → no broken pages.

**Phase 8 checkpoint: QA logs in as each of 6 roles → correct sidebar items → premium dark UI throughout → all features work end-to-end → no placeholder content anywhere.**

---

## PHASE 9 — Test Suite Completion & Docker Verification
> Goal: All 4 test suites pass via Docker, static audit clean, zero broken features
> Complete all tasks continuously, then pause. Wait for "proceed".

- [ ] 9.1 Audit: grep -r "EntityManager\|createQuery\|->query\(" backend/src/ | grep -v "createQueryBuilder\|DQL\|repository" → verify no raw SQL string queries. All must use QueryBuilder or DQL.
- [ ] 9.2 Audit: grep -r "AuditLog" backend/src/Repository/ → verify AuditLogRepository has ONLY select methods. No update/delete.
- [ ] 9.3 Audit: grep -rn "console\.log\|var_dump\|dd(" backend/src/ frontend/src/ | grep -v "test\|spec" → must be zero results in production code.
- [ ] 9.4 Audit: verify every API controller method calls $this->rateLimitService->check*() at the start. Add any missing calls.
- [ ] 9.5 Audit: verify every state-changing controller method calls $this->auditService->log() before returning. Add any missing calls.
- [ ] 9.6 Write any missing tests to complete all 4 suites:
       Security: testCsrfMissingReturns403, testRateLimitReturns429, testLockedAccountReturns423, testApiSignatureMissingReturns401
       Attendance: testEngineRunsForAllUsers, testCsvImportSkipsDuplicates
       Privacy: testPhoneMaskedInApiResponse, testAuditLogRetainsAfterUserDeletion
       Work Orders: testPhotoCountLimit, testRatingStarRange
- [ ] 9.7 Run: docker compose --profile test run --build test → fix ALL failures until all 4 suites show PASS and exit code is 0.
- [ ] 9.8 Run: docker compose up --build → full end-to-end verification:
       Login as each of 6 roles → verify correct sidebar + dashboard
       Employee: submit exception request → see timeline → submit work order with photo
       Supervisor: approve the request → queue updates
       HR Admin: view full phone number → masked for supervisor
       Dispatcher: assign work order to technician
       Technician: update to in_progress → complete
       Employee: rate the completed work order
       Admin: view audit log → all actions recorded → no delete button visible
- [ ] 9.9 Verify: grep -r "TODO\|FIXME\|placeholder\|stub\|hardcode" backend/src/ frontend/src/ | grep -v "test\|spec\|\.md" → must be zero.
- [ ] 9.10 Verify: docker compose --profile test run test exits with code 0 showing "ALL TESTS PASSED".

**Phase 9 checkpoint: docker compose --profile test run test → ALL TESTS PASSED exit 0. Full end-to-end scenario tested. Zero TODO/stub comments.**

---

## PHASE 10 — Documentation Generation
> Final phase — generate docs from actual implemented code.

- [ ] 10.1 Create docs/design.md — from actual implemented code:
       ASCII architecture (React SPA → Nginx → Symfony API → MySQL)
       Docker service map (setup/mysql/backend/frontend/test/mysql-test)
       All Doctrine entities and their relationships
       Security architecture (CSRF flow, rate limiting, lockout, encryption, API signature)
       Attendance engine algorithm (exception detection rules with thresholds)
       Approval workflow (step progression, SLA calculation, escalation logic)
       Work order state machine (all transitions, who can trigger each)
       Booking idempotency (clientKey lifecycle, split/merge allocation)
       Audit log design (why append-only, what triggers each action, 7-year retention)
       Privacy tiers (who sees what, masking rules, data deletion process)

- [ ] 10.2 Create docs/api-spec.md — from actual implemented code:
       Every API endpoint: method, path, role required, CSRF required, request body, response shape, error codes
       Auth endpoints (session + CSRF cookie behavior)
       File upload multipart spec (MIME types, size limits, hash dedup)
       Approval workflow endpoints (step transitions, withdraw, reassign)
       Work order endpoints (state machine transitions)
       Booking endpoint (idempotency header, traveler split)
       Admin endpoints (API signature requirement)
       Standard error response format {error, message, code}
       Rate limit headers (X-RateLimit-Remaining, Retry-After)

---

## Execution Notes for Claude

- Complete ALL tasks in a phase without stopping between tasks
- Mark [x] immediately then continue — never pause mid-phase
- Fix PHP/TypeScript errors within the same task before marking [x]
- Only pause after entire phase checkpoint passes
- At each pause: brief summary (files created, checkpoint result)
- Wait for "proceed" before next phase
- Sidebar rule: ALWAYS render role-gated nav — never show forbidden links
- Audit rule: NEVER add update/delete methods to AuditLogRepository
- Real data rule: NEVER hardcode display data in React components — always useQuery/axios
- UI rule: every page must look premium — dark theme, colored badges, smooth interactions
