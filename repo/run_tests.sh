#!/bin/sh
# run_tests.sh — runs all four test suites through Docker.
# Requirements on the host machine: Docker only.
# No local PHP, Composer, Node, or npm needed.
#
# Order: backend unit → backend API → frontend unit → Playwright e2e (headless)
#
# Exit 0 only if ALL four suites pass; non-zero if any fail.

set -e

echo "========================================"
echo "  Workforce & Operations Hub Test Suite"
echo "========================================"

BACKEND_UNIT=0
BACKEND_API=0
FRONTEND_UNIT=0
E2E=0

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

# -----------------------------------------------------------------------
# Detect execution mode
# -----------------------------------------------------------------------
if [ -d "/app/backend" ] && [ -d "/app/frontend" ]; then
  MODE="container"
  BACKEND_DIR="/app/backend"
  FRONTEND_DIR="/app/frontend"
else
  MODE="host"
  BACKEND_DIR="$SCRIPT_DIR/backend"
  FRONTEND_DIR="$SCRIPT_DIR/frontend"
fi

# -----------------------------------------------------------------------
# In container mode: run backend + frontend unit tests directly.
# Playwright e2e is skipped in container mode (no running app, no display).
# -----------------------------------------------------------------------
if [ "$MODE" = "container" ]; then
  echo "Running in CONTAINER mode"

  TEST_APP_ENV=test
  TEST_DATABASE_URL='mysql://wfops:wfops_pass@mysql-test:3306/wfops_test?serverVersion=8.0'

  echo "--- Preparing test database ---"
  cd "$BACKEND_DIR"
  APP_ENV="$TEST_APP_ENV" DATABASE_URL="$TEST_DATABASE_URL" \
    php bin/console doctrine:database:create --if-not-exists --env=test 2>&1 || true
  APP_ENV="$TEST_APP_ENV" DATABASE_URL="$TEST_DATABASE_URL" \
    php bin/console doctrine:migrations:migrate --no-interaction --env=test 2>&1 || true
  APP_ENV="$TEST_APP_ENV" DATABASE_URL="$TEST_DATABASE_URL" \
    php bin/console doctrine:fixtures:load --no-interaction --env=test 2>&1 || true

  echo "--- Backend Unit Tests ---"
  APP_ENV="$TEST_APP_ENV" DATABASE_URL="$TEST_DATABASE_URL" \
    php vendor/bin/phpunit tests/unit_tests/ --testdox 2>&1 || BACKEND_UNIT=1
  [ $BACKEND_UNIT -eq 0 ] && echo "✅ Backend Unit PASSED" || echo "❌ Backend Unit FAILED"

  echo "--- Backend API Tests ---"
  APP_ENV="$TEST_APP_ENV" DATABASE_URL="$TEST_DATABASE_URL" \
    php vendor/bin/phpunit tests/api_tests/ --testdox 2>&1 || BACKEND_API=1
  [ $BACKEND_API -eq 0 ] && echo "✅ Backend API PASSED" || echo "❌ Backend API FAILED"

  echo "--- Frontend Unit Tests ---"
  cd "$FRONTEND_DIR"
  if [ ! -d node_modules ] || [ ! -d node_modules/vitest ]; then
    npm ci --no-audit --no-fund 2>&1 || npm install --no-audit --no-fund 2>&1 || true
  fi
  npx vitest run tests/unit_tests/ --reporter=verbose 2>&1 || FRONTEND_UNIT=1
  [ $FRONTEND_UNIT -eq 0 ] && echo "✅ Frontend Unit PASSED" || echo "❌ Frontend Unit FAILED"

  echo "--- Playwright E2E: SKIPPED in container mode (requires running app) ---"
  echo "(Run 'bash run_tests.sh' from the host after 'docker compose up --build' for E2E tests)"

  echo "========================================"
  TOTAL=$((BACKEND_UNIT + BACKEND_API + FRONTEND_UNIT))
  if [ $TOTAL -eq 0 ]; then
    echo "  ALL TESTS PASSED (E2E skipped)"
    exit 0
  fi
  echo "  SOME TESTS FAILED"
  echo "  Backend Unit:  $([ $BACKEND_UNIT  -eq 0 ] && echo PASS || echo FAIL)"
  echo "  Backend API:   $([ $BACKEND_API   -eq 0 ] && echo PASS || echo FAIL)"
  echo "  Frontend Unit: $([ $FRONTEND_UNIT -eq 0 ] && echo PASS || echo FAIL)"
  exit 1
fi

# -----------------------------------------------------------------------
# HOST mode: all four suites run through Docker.
# -----------------------------------------------------------------------
echo "Running in HOST mode (Docker-based)"

if ! command -v docker > /dev/null; then
  echo "ERROR: docker is required on the host machine."
  exit 1
fi

if docker compose version > /dev/null 2>&1; then
  DC="docker compose"
elif command -v docker-compose > /dev/null; then
  DC="docker-compose"
else
  echo "ERROR: neither 'docker compose' nor 'docker-compose' found."
  exit 1
fi

cd "$SCRIPT_DIR"

# -----------------------------------------------------------------------
# Helpers: exec into running backend container with test env vars
# -----------------------------------------------------------------------
TEST_APP_ENV=test
TEST_DATABASE_URL='mysql://wfops:wfops_pass@mysql-test:3306/wfops_test?serverVersion=8.0'

backend_exec() {
  $DC exec -T -w /app/backend backend sh -c "$1"
}

backend_exec_test() {
  $DC exec -T -w /app/backend \
    -e APP_ENV="$TEST_APP_ENV" \
    -e DATABASE_URL="$TEST_DATABASE_URL" \
    backend sh -c "$1"
}

# -----------------------------------------------------------------------
# Step 1: Ensure backend is running and mysql-test is available
# -----------------------------------------------------------------------
echo "--- Ensuring backend and test database are running ---"
if ! $DC ps --services --filter "status=running" 2>/dev/null | grep -q '^backend$'; then
  echo "Starting backend..."
  $DC up -d backend 2>&1 || true
fi

if ! $DC --profile test ps --services --filter "status=running" 2>/dev/null | grep -q '^mysql-test$'; then
  echo "Starting mysql-test..."
  # Remove stale container (which may carry wrong credentials from a previous volume)
  docker rm -v repo-mysql-test-1 2>/dev/null || true
  $DC --profile test up -d mysql-test 2>&1 || true
fi

echo "--- Waiting for mysql-test to be ready ---"
for i in $(seq 1 60); do
  if $DC exec -T mysql-test mysqladmin ping -h127.0.0.1 -uroot -proot_pass --silent > /dev/null 2>&1; then
    echo "mysql-test ready (${i}s)"
    break
  fi
  if [ "$i" = "60" ]; then
    echo "WARNING: mysql-test may not be ready; continuing anyway"
  fi
  sleep 2
done

# -----------------------------------------------------------------------
# Step 2: Install backend dev dependencies if needed
# -----------------------------------------------------------------------
backend_exec '[ -f .env ] || cp .env.example .env 2>/dev/null || true' 2>/dev/null || true
if ! backend_exec 'test -f vendor/bin/phpunit' > /dev/null 2>&1; then
  echo "--- Installing backend dev dependencies ---"
  backend_exec 'composer install --optimize-autoloader --no-interaction --no-scripts' || true
fi

# -----------------------------------------------------------------------
# Step 2b: Sync latest test files into the backend container.
# The production image bakes tests at build time; syncing ensures edits
# made after the build are reflected without a full rebuild.
# -----------------------------------------------------------------------
echo "--- Syncing test files into backend container ---"
$DC cp "$BACKEND_DIR/tests/unit_tests/." backend:/app/backend/tests/unit_tests/ 2>/dev/null || true
$DC cp "$BACKEND_DIR/tests/api_tests/." backend:/app/backend/tests/api_tests/ 2>/dev/null || true

# -----------------------------------------------------------------------
# Step 3: Prepare test database
# -----------------------------------------------------------------------
echo "--- Preparing test database ---"
backend_exec_test 'rm -rf var/cache/test 2>/dev/null; exit 0' || true
backend_exec_test 'php bin/console doctrine:database:create --if-not-exists --env=test' 2>&1 || true
backend_exec_test 'php bin/console doctrine:migrations:migrate --no-interaction --env=test' 2>&1 || true
backend_exec_test 'php bin/console doctrine:fixtures:load --no-interaction --env=test' 2>&1 || true

# -----------------------------------------------------------------------
# Step 4: Backend unit tests
# -----------------------------------------------------------------------
echo "--- Backend Unit Tests (tests/unit_tests/) ---"
backend_exec_test 'php vendor/bin/phpunit tests/unit_tests/ --testdox' 2>&1 || BACKEND_UNIT=1
[ $BACKEND_UNIT -eq 0 ] && echo "✅ Backend Unit PASSED" || echo "❌ Backend Unit FAILED"

# -----------------------------------------------------------------------
# Step 5: Backend API tests
# -----------------------------------------------------------------------
echo "--- Backend API Tests (tests/api_tests/) ---"
backend_exec_test 'php vendor/bin/phpunit tests/api_tests/ --testdox' 2>&1 || BACKEND_API=1
[ $BACKEND_API -eq 0 ] && echo "✅ Backend API PASSED" || echo "❌ Backend API FAILED"

# -----------------------------------------------------------------------
# Step 6: Frontend unit tests (via Docker, no local Node needed)
# -----------------------------------------------------------------------
echo "--- Frontend Unit Tests (tests/unit_tests/) ---"
docker run --rm \
  -v "$SCRIPT_DIR/frontend:/app/frontend" \
  -w /app/frontend \
  node:20-alpine \
  sh -c "npm ci --no-audit --no-fund 2>&1 && npx vitest run tests/unit_tests/ --reporter=verbose 2>&1" \
  || FRONTEND_UNIT=1
[ $FRONTEND_UNIT -eq 0 ] && echo "✅ Frontend Unit PASSED" || echo "❌ Frontend Unit FAILED"

# -----------------------------------------------------------------------
# Step 7: Playwright E2E tests (headless, reusing pulled image)
# -----------------------------------------------------------------------
echo "--- Playwright E2E Tests (tests/e2e/, headless) ---"

# Ensure frontend is running (depends on backend which is already up)
if ! $DC ps --services --filter "status=running" 2>/dev/null | grep -q '^frontend$'; then
  echo "Starting frontend for E2E tests..."
  $DC up -d frontend 2>&1 || true
fi

# Wait for frontend to respond
echo "Waiting for frontend to be ready..."
FRONTEND_READY=0
for i in $(seq 1 30); do
  if docker run --rm --network host curlimages/curl:latest -sf http://localhost:3000 > /dev/null 2>&1 || \
     $DC exec -T frontend wget -qO- http://localhost:80 > /dev/null 2>&1; then
    echo "Frontend ready (${i}s)"
    FRONTEND_READY=1
    break
  fi
  sleep 2
done

if [ "$FRONTEND_READY" = "0" ]; then
  echo "WARNING: Frontend may not be ready; attempting E2E tests anyway"
fi

# Run Playwright using the already-pulled image (no download needed)
# Mounts frontend source read-only to a temp container path; installs
# @playwright/test inline with PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1 so
# browsers from the image (/ms-playwright) are reused without re-fetching.
docker run --rm \
  --network host \
  -v "$SCRIPT_DIR/frontend:/app/frontend" \
  -w /app/frontend \
  -e BASE_URL=http://localhost:3000 \
  -e PLAYWRIGHT_BROWSERS_PATH=/ms-playwright \
  -e PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1 \
  -e CI=true \
  mcr.microsoft.com/playwright:v1.59.1-jammy \
  bash -c "
    PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1 npm install --no-save @playwright/test --no-fund --no-audit --prefer-offline 2>&1
    npx playwright test tests/e2e/ --reporter=list 2>&1
  " || E2E=1
[ $E2E -eq 0 ] && echo "✅ Playwright E2E PASSED" || echo "❌ Playwright E2E FAILED"

# -----------------------------------------------------------------------
# Summary
# -----------------------------------------------------------------------
echo "========================================"
TOTAL=$((BACKEND_UNIT + BACKEND_API + FRONTEND_UNIT + E2E))
if [ $TOTAL -eq 0 ]; then
  echo "  ALL FOUR SUITES PASSED"
  echo "  Backend Unit:   PASS"
  echo "  Backend API:    PASS"
  echo "  Frontend Unit:  PASS"
  echo "  Playwright E2E: PASS"
  exit 0
fi

echo "  SOME TESTS FAILED"
echo "  Backend Unit:   $([ $BACKEND_UNIT  -eq 0 ] && echo PASS || echo FAIL)"
echo "  Backend API:    $([ $BACKEND_API   -eq 0 ] && echo PASS || echo FAIL)"
echo "  Frontend Unit:  $([ $FRONTEND_UNIT -eq 0 ] && echo PASS || echo FAIL)"
echo "  Playwright E2E: $([ $E2E           -eq 0 ] && echo PASS || echo FAIL)"
exit 1
