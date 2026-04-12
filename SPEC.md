# SPEC.md — Workforce & Operations Hub
# Task ID: TASK-59-W2
# Single source of truth. All decisions trace back to this file.

## Original Business Prompt (Verbatim — Do Not Modify)

Build a Workforce & Operations Hub that unifies attendance exception handling and facilities repair work orders into one offline-first web system for a US-based organization. Employees sign in from a React web portal to view today's auto-generated attendance card (in/out punches, total hours, and flags for late arrival, early leave, missed punch, absence, or approved offsite time) and get clear, in-page guidance on what to do next. When an exception is detected, the employee can submit a correction, PTO/leave, business trip, or outing request by selecting dates (MM/DD/YYYY), time windows in 15-minute increments, and a reason; the UI must show policy hints such as "late after 9:05 AM," "missed punch if no event within 30 minutes of shift start," and "requests must be filed within 7 calendar days." A live status timeline shows who currently owns the approval and the remaining SLA time, and users can withdraw a pending request before the first approver acts or request reassignment if an approver is out. Facilities use the same portal: residents or staff can submit dormitory and facilities repair tickets with category, priority, description, and up to 5 photos (JPEG/PNG) plus a manually selected building/room (no map); they can track dispatch, acceptance, completion, and rate the work within 72 hours. Roles include Employee/Resident, Supervisor, HR Admin, Facilities Dispatcher, Technician, and System Administrator; supervisors approve attendance items, HR handles policy overrides, dispatchers assign work orders, and technicians update progress and add completion notes.
The backend uses Symfony to expose REST-style APIs consumed by the React frontend over the local network, with MySQL as the system of record for users, roles, attendance events, exception rules, approval workflows, work orders, attachments, and immutable audit logs. Attendance is generated nightly at 2:00 AM from locally imported punch files (CSV) and shift schedules; the engine applies configurable tolerances (default 5 minutes) and produces a deterministic exception set, then drives multi-level approvals (up to 3 steps) with an SLA of 24 business hours per step and automatic escalation to a designated backup approver after 2 hours past due. Orders use a strict state machine for any bookable internal resources tied to trips or outings, but "payment" is implemented as internal cost-center allocation only (no online payments); order placement must be idempotent for 10 minutes via a client-generated key to prevent duplicate submits, and the system supports merged allocations across multiple bookings and split issuance when a booking includes multiple travelers. Security and privacy are enforced with server-side validation, prepared statements, output encoding to prevent XSS, CSRF tokens for session-based auth, API signatures for privileged actions, and rate limits (for example, 60 requests/minute per user, 10 uploads/minute). File uploads are validated by MIME type, extension, size (max 10 MB each), and hash-based fingerprinting; sensitive fields such as phone numbers display as (555) ***-1234 and are encrypted at rest, and access is tiered so only HR Admin can view full identity data. The system writes traceable audit records for create/update/approve actions, masks sensitive values in logs, raises local anomaly alerts for repeated failed logins (lockout after 5 attempts for 15 minutes), supports user-requested data deletion where legally allowed, and retains audit records for 7 years—all without relying on any external network services.

## Project Metadata

- Task ID: TASK-59-W2
- Project Type: fullstack
- Language: PHP 8.2 (backend) + JavaScript/TypeScript (frontend)
- Frontend: React 18 + TypeScript + Vite + TailwindCSS + shadcn/ui
- Backend: Symfony 7 (PHP)
- Database: MySQL 8
- ORM: Doctrine ORM with migrations
- Infrastructure: Docker + single docker-compose.yml
- Testing: PHPUnit (backend unit + API), Vitest (frontend unit), real MySQL via Docker

> PRIORITY RULE: Original business prompt takes absolute priority over metadata.
> Metadata supports the prompt — never overrides it.

## Roles (all 6 must be implemented with distinct permissions)

| Role | Key Responsibilities |
|---|---|
| Employee / Resident | View attendance card, submit exception requests, submit work orders, track status |
| Supervisor | Approve/reject attendance exception requests (Step 1) |
| HR Admin | Policy overrides, view full identity data, final attendance approval, manage rules |
| Facilities Dispatcher | Assign and dispatch work orders to technicians |
| Technician | Accept work orders, update progress, add completion notes |
| System Administrator | Full access, user management, system config, audit log, CSV import |

## Core Modules (all must be fully implemented AND work in Docker)

1. **Auth & Security** — Session-based login, CSRF tokens on all state-changing requests, API signatures for privileged endpoints, rate limiting (60 req/min/user, 10 uploads/min), account lockout (5 failed attempts → 15-min lock), password hashing (bcrypt), output encoding (XSS prevention), prepared statements only
2. **Attendance Engine** — Nightly CSV import at 2:00 AM (Symfony Console command + cron), deterministic exception detection (late arrival, early leave, missed punch, absence, approved offsite), configurable tolerances (default 5 min), shift schedule management
3. **Attendance Card & Exception Requests** — Today's card with in/out punches, total hours, exception flags, in-page policy hints, date picker (MM/DD/YYYY), 15-minute time increments, 7-day filing window enforced, request types: correction/PTO/leave/business trip/outing
4. **Approval Workflow** — Multi-level up to 3 steps, SLA 24 business hours per step, auto-escalation to backup approver after 2 hours overdue, live status timeline with current owner and remaining SLA time, withdraw before first approver acts, request reassignment if approver is out
5. **Work Orders (Facilities)** — Submit ticket with category/priority/description/building/room, up to 5 photos (JPEG/PNG, 10MB each), strict state machine (submitted → dispatched → accepted → in_progress → completed → rated), 72-hour rating window, dispatch assignment, technician progress notes
6. **Booking & Resource Allocation** — Internal bookable resources for trips/outings, cost-center allocation only (no payments), idempotent order placement (10-min client key), merged allocations across bookings, split issuance for multiple travelers
7. **File Uploads** — MIME type + extension + size (10MB) + hash fingerprint validation, no duplicate files, encrypted storage path, 10 uploads/min rate limit
8. **Privacy & Sensitive Data** — Phone masking (555) ***-1234, encryption at rest for sensitive fields, tiered access (HR Admin only for full identity), user-requested data deletion (where legal), audit records retained 7 years
9. **Audit Log** — Immutable records for create/update/approve actions, sensitive values masked in logs, anomaly alerts (repeated failed logins), local only (no external services)

## QA Evaluation — TWO SIMULTANEOUS TESTS (both must pass)

### TEST 1 — Static Code Audit (AI reads every source file)
- All 9 modules explicitly coded — not stubs
- Security: CSRF, rate limiting, lockout, encryption — readable code with comments
- Doctrine entities use proper types, no raw SQL string concatenation
- Audit service is append-only — no UPDATE/DELETE on audit_logs table
- Tests exist with real assertions, real MySQL, no mocking of the DB
- No hardcoded data in React components — all from real API calls

### TEST 2 — Docker Runtime Manual Testing (human clicks every page)
```
docker compose up --build
→ http://localhost:3000 (React frontend)
→ http://localhost:8000 (Symfony API)
→ Login with all 6 role credentials from README
→ Test every feature end-to-end for every role
→ No broken pages, no 500 errors, no placeholder UI
→ Every form submits to real API, every table shows real MySQL data
```

QA logs in as each role and tests:
- **Employee:** view attendance card → submit exception request → track approval timeline → submit work order → rate completed work
- **Supervisor:** approve/reject pending attendance requests → see SLA countdown
- **HR Admin:** policy override → view full employee identity data → manage exception rules
- **Dispatcher:** assign work order to technician → change status
- **Technician:** accept work order → update progress → add completion notes
- **Admin:** manage users → import CSV → view audit log → view system config

PASS = Test 1 AND Test 2 both pass simultaneously.

## Non-Negotiable Delivery Rules

- Single `docker-compose.yml` (setup + app + frontend + test services)
- `docker compose up --build` → frontend at http://localhost:3000, API at http://localhost:8000
- `docker compose --profile test run --build test` → runs run_tests.sh via Docker
- `run_tests.sh` — Docker-first (uses system php/composer/node), also runnable locally
- Backend tests: PHPUnit with real MySQL (Testcontainers or test DB service)
- Frontend tests: Vitest for unit tests, real API calls for integration tests
- Both backend AND frontend have tests/unit_tests/ and tests/api_tests/ folders
- `.env.example` committed to git, auto-copied to `.env` by Docker setup service
- `.env` in `.gitignore`, `.env.example` is NOT ignored
- Minimal README: Run / Test / Stop / Login only
- All code inside `repo/`
- Zero manual setup after `git clone`
