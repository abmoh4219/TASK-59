# fullstack

A full-stack Workforce & Operations Hub for attendance tracking, exception requests, approval workflows, work orders, and resource bookings. Built with PHP 8.2 / Symfony 7 on the backend and React 18 on the frontend, backed by MySQL 8.

## Architecture and Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2 / Symfony 7 (REST API, port 8000) |
| Frontend | React 18 + TypeScript + Vite + TailwindCSS (port 3000) |
| Database | MySQL 8 (Doctrine ORM) |
| Infrastructure | Docker + Docker Compose |

## Project Structure

```
repo/
├── docker-compose.yml
├── run_tests.sh
├── backend/                  # Symfony 7 PHP 8.2 app
│   ├── src/
│   │   ├── Controller/       # AuthController, AttendanceController, WorkOrderController, ...
│   │   ├── Entity/           # User, ExceptionRequest, ApprovalStep, WorkOrder, ...
│   │   ├── Service/          # AttendanceEngineService, ApprovalWorkflowService, ...
│   │   ├── Security/         # LoginFormAuthenticator, ApiSignatureAuthenticator
│   │   └── DataFixtures/     # AppFixtures (6 seed users + sample data)
│   └── tests/
│       ├── unit_tests/       # PHPUnit service logic tests — no database
│       └── api_tests/        # PHPUnit HTTP endpoint tests — real MySQL
├── frontend/                 # React 18 + TypeScript app
│   ├── src/
│   │   ├── api/              # axios client, auth, attendance, workOrders, bookings
│   │   ├── components/       # layout/, ui/, attendance/
│   │   ├── pages/            # Login, Dashboard, attendance/, approvals/, workorders/, ...
│   │   ├── hooks/            # useAuth, useAttendance, useWorkOrders
│   │   └── context/          # AuthContext
│   └── tests/
│       ├── unit_tests/       # Vitest component and logic tests
│       └── e2e/              # Playwright end-to-end tests
└── nginx/
    └── nginx.conf            # Serves frontend static files, proxies /api to backend
```

## Prerequisites

- Docker
- Docker Compose

## Running the Application

```bash
docker compose up --build
```

```bash
docker-compose up --build
```

- Frontend: http://localhost:3000
- API: http://localhost:8000

## Testing

```bash
bash run_tests.sh
```

Runs all four test suites through Docker in order:

1. **Backend unit tests** — PHPUnit, service logic, no database
2. **Backend API tests** — PHPUnit WebTestCase, real HTTP requests, real MySQL
3. **Frontend unit tests** — Vitest, component and logic tests
4. **Playwright E2E tests** — headless browser, real running application

Requirements: Docker only. No local PHP, Composer, Node, or npm needed on the host.

## Verifying the Application Works

After running `docker compose up --build`, follow these steps to confirm each role:

**1. Visit http://localhost:3000** — You should see the login screen with username and password fields.

**2. System Administrator (admin / Admin@WFOps2024!)**
- Log in → you are redirected to the Dashboard
- In the sidebar, confirm you see: Work Orders, Admin (Users, Audit Log, CSV Import, Config)
- Navigate to Admin → Users → verify a table of 6 users loads from the database
- Navigate to Admin → Config → verify exception rules are shown (e.g., LATE_ARRIVAL, ABSENCE)

**3. HR Admin (hradmin / HRAdmin@2024!)**
- Log in → Dashboard
- Navigate to Attendance → verify today's attendance card loads
- Navigate to Admin → Audit Log → verify audit log entries appear

**4. Supervisor (supervisor / Super@2024!)**
- Log in → Dashboard
- Navigate to Approvals → verify the approval queue loads (may be empty if no pending requests)
- Navigate to Attendance → verify the attendance page loads

**5. Employee (employee / Emp@2024!)**
- Log in → Dashboard
- Navigate to Attendance → verify today's card shows punch times and exception badges
- Click "Submit Request" (if a LATE_ARRIVAL exception is shown) → verify the request form opens with type dropdown, date fields, and reason textarea
- Navigate to Work Orders → verify the list loads (seeded orders should appear)
- Click "New Work Order" → fill in category, priority, description, building, room → submit → verify 201 and the order appears in the list

**6. Dispatcher (dispatcher / Dispatch@2024!)**
- Log in → Dashboard
- Navigate to Work Orders → verify the list shows submitted orders
- Click on a submitted work order → verify you see a "Dispatch" button and a technician selector

**7. Technician (technician / Tech@2024!)**
- Log in → Dashboard
- Navigate to Work Orders → verify the list shows orders dispatched to this technician
- Click on a dispatched work order → verify "Accept" button is shown

**API health check:** `curl http://localhost:8000/api/health` should return `{"status":"ok",...}`

## Seeded Credentials

| Role | Username | Password |
|---|---|---|
| System Administrator | admin | Admin@WFOps2024! |
| HR Admin | hradmin | HRAdmin@2024! |
| Supervisor | supervisor | Super@2024! |
| Employee | employee | Emp@2024! |
| Dispatcher | dispatcher | Dispatch@2024! |
| Technician | technician | Tech@2024! |
