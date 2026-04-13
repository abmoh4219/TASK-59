#!/bin/sh
set -e
echo "========================================"
echo "  Workforce & Operations Hub Test Suite"
echo "========================================"

BACKEND_UNIT=0; BACKEND_API=0; FRONTEND_UNIT=0; FRONTEND_API=0

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

# ----------------------------------------------------------------------
# Execution-mode detection
#
# This script supports two modes:
#
#   1. HOST mode (company validator + local dev machines):
#      - Runs on the host where `docker` / `docker compose` is available.
#      - Uses `docker compose exec backend ...` to run every PHP command
#        inside the already-running backend container. That container has
#        all required PHP extensions (sodium, intl, pdo_mysql, ...), so we
#        never touch the host PHP. This also avoids the earlier bug where
#        `composer.phar` ran against a host PHP missing ext-sodium.
#
#   2. IN-CONTAINER mode (docker compose --profile test run test):
#      - Runs inside the pre-provisioned `test` image which already has
#        dev dependencies. Executes PHP/Node commands directly.
#
# Mode is chosen at runtime: if /app/backend exists we are inside a
# container; otherwise we shell out to docker compose on the host.
# ----------------------------------------------------------------------

if [ -d "/app/backend" ] && [ -d "/app/frontend" ]; then
  MODE="container"
  BACKEND_DIR="/app/backend"
  FRONTEND_DIR="/app/frontend"
else
  MODE="host"
  BACKEND_DIR="$SCRIPT_DIR/backend"
  FRONTEND_DIR="$SCRIPT_DIR/frontend"
  if ! command -v docker > /dev/null; then
    echo "ERROR: docker not found on host and not running inside /app/."
    exit 1
  fi
  # Prefer v2 (`docker compose`); fall back to v1 (`docker-compose`).
  if docker compose version > /dev/null 2>&1; then
    DC="docker compose"
  elif command -v docker-compose > /dev/null; then
    DC="docker-compose"
  else
    echo "ERROR: neither 'docker compose' nor 'docker-compose' is available."
    exit 1
  fi
  # Ensure the backend container is running; if not, start it.
  cd "$SCRIPT_DIR"
  if ! $DC ps --services --filter "status=running" 2>/dev/null | grep -q '^backend$'; then
    echo "--- Starting backend container for tests ---"
    $DC up -d backend 2>&1 || true
  fi
  # Ensure the test-database container is running too. mysql-test lives
  # in the "test" profile so it is only started on demand. phpunit boots
  # against this instance via DATABASE_URL=mysql://...@mysql-test:3306.
  if ! $DC ps --services --filter "status=running" 2>/dev/null | grep -q '^mysql-test$'; then
    echo "--- Starting mysql-test container for tests ---"
    $DC --profile test up -d mysql-test 2>&1 || true
  fi

  # Wait until mysql-test is actually accepting connections. "Container
  # Started" only means the entrypoint is running — MySQL itself needs
  # ~20-30s to initialize on a fresh volume. Without this loop every
  # downstream doctrine command fails with "[2002] Connection refused",
  # which is exactly the cascade we were hitting in the validator logs.
  echo "--- Waiting for mysql-test to be ready ---"
  for i in $(seq 1 60); do
    if $DC exec -T mysql-test mysqladmin ping -h127.0.0.1 -uroot -proot_pass --silent > /dev/null 2>&1; then
      echo "mysql-test is ready (after ${i}s)"
      break
    fi
    if [ "$i" = "60" ]; then
      echo "WARNING: mysql-test did not become ready within 120s; continuing anyway"
    fi
    sleep 2
  done
fi

# Test-env overrides. The production backend container boots with
# APP_ENV=prod and the prod DATABASE_URL; phpunit needs APP_ENV=test
# (to enable framework.test) and the test DSN pointing at mysql-test.
# These are passed via `docker compose exec -e` in host mode, or via
# inline env in container mode.
TEST_APP_ENV='test'
TEST_DATABASE_URL='mysql://wfops:wfops_pass@mysql-test:3306/wfops_test?serverVersion=8.0'

# Helper: run a command inside the backend container (host mode) or
# directly (container mode). Stdout/stderr are passed through. The
# backend_exec wrapper is used for commands that just need php/composer.
backend_exec() {
  if [ "$MODE" = "host" ]; then
    # -T disables TTY allocation so CI logs stay clean.
    $DC exec -T -w /app/backend backend sh -c "$1"
  else
    (cd "$BACKEND_DIR" && sh -c "$1")
  fi
}

# backend_exec_test: same as backend_exec but forces APP_ENV=test and
# the test DATABASE_URL. Used for cache:clear (test env), migrations,
# fixtures, and phpunit — all of which must hit the test database.
backend_exec_test() {
  if [ "$MODE" = "host" ]; then
    $DC exec -T -w /app/backend \
      -e APP_ENV="$TEST_APP_ENV" \
      -e DATABASE_URL="$TEST_DATABASE_URL" \
      backend sh -c "$1"
  else
    (cd "$BACKEND_DIR" && APP_ENV="$TEST_APP_ENV" DATABASE_URL="$TEST_DATABASE_URL" sh -c "$1")
  fi
}

# ----------------------------------------------------------------------
# Backend: provision dev deps INSIDE the container (it has ext-sodium
# and every other PHP extension the project needs). The production image
# is built --no-dev, so we install dev deps into the container's mutable
# filesystem on demand. This never rebakes or publishes any image.
# ----------------------------------------------------------------------

# Make sure Symfony Dotenv has a .env to read at cache:clear time.
backend_exec '[ -f .env ] || [ ! -f .env.example ] || cp .env.example .env || true' || true

# Install dev dependencies only if phpunit is missing. Use --no-scripts
# so post-install cache:clear does not fire during provisioning.
if ! backend_exec 'test -f vendor/bin/phpunit' > /dev/null 2>&1; then
  echo "--- Installing backend dev dependencies inside the backend container ---"
  backend_exec 'composer install --optimize-autoloader --no-interaction --no-scripts' || true
fi

# Prepare test database (migrations + fixtures) against mysql-test.
echo "--- Preparing test database ---"
backend_exec_test 'rm -rf var/cache/test 2>/dev/null; exit 0' || true
backend_exec_test 'php bin/console doctrine:database:create --if-not-exists --env=test' 2>&1 || true
backend_exec_test 'php bin/console doctrine:migrations:migrate --no-interaction --env=test' 2>&1 || true
backend_exec_test 'php bin/console doctrine:fixtures:load --no-interaction --env=test' 2>&1 || true

echo "--- Backend Unit Tests (tests/unit_tests/) ---"
backend_exec_test 'php vendor/bin/phpunit tests/unit_tests/ --testdox' || BACKEND_UNIT=1
[ $BACKEND_UNIT -eq 0 ] && echo "✅ Backend Unit PASSED" || echo "❌ Backend Unit FAILED"

echo "--- Backend API Tests (tests/api_tests/) ---"
backend_exec_test 'php vendor/bin/phpunit tests/api_tests/ --testdox' || BACKEND_API=1
[ $BACKEND_API -eq 0 ] && echo "✅ Backend API PASSED" || echo "❌ Backend API FAILED"

# ----------------------------------------------------------------------
# Frontend: Vitest runs either directly inside /app/frontend (container
# mode) or on the host (host mode) where Node 20 is expected. Frontend
# tests were already passing with this shape — only install node_modules
# on demand when missing. Vitest tests do NOT require Docker.
# ----------------------------------------------------------------------
echo "--- Frontend Unit Tests (tests/unit_tests/) ---"
cd "$FRONTEND_DIR"

if [ ! -d node_modules ] || [ ! -d node_modules/vitest ]; then
  if command -v npm > /dev/null; then
    echo "--- Installing frontend dev dependencies (vitest, @testing-library) ---"
    npm ci --no-audit --no-fund 2>&1 || npm install --no-audit --no-fund 2>&1 || true
  else
    echo "WARNING: npm not found; frontend tests may fail"
  fi
fi

if command -v npx > /dev/null; then
  npx vitest run tests/unit_tests/ 2>&1 || FRONTEND_UNIT=1
else
  echo "ERROR: npx not found, cannot run frontend unit tests"
  FRONTEND_UNIT=1
fi
[ $FRONTEND_UNIT -eq 0 ] && echo "✅ Frontend Unit PASSED" || echo "❌ Frontend Unit FAILED"

echo "--- Frontend API Tests (tests/api_tests/) ---"
if command -v npx > /dev/null; then
  npx vitest run tests/api_tests/ 2>&1 || FRONTEND_API=1
else
  FRONTEND_API=1
fi
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
