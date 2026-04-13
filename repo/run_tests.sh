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

# Portable path detection: prefer the Docker layout (/app/*), fall back to
# the script's own location so the suite can be run locally as well.
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
if [ -d "/app/backend" ] && [ -d "/app/frontend" ]; then
  BACKEND_DIR="/app/backend"
  FRONTEND_DIR="/app/frontend"
elif [ -d "$SCRIPT_DIR/backend" ] && [ -d "$SCRIPT_DIR/frontend" ]; then
  BACKEND_DIR="$SCRIPT_DIR/backend"
  FRONTEND_DIR="$SCRIPT_DIR/frontend"
else
  echo "ERROR: could not locate backend/ and frontend/ directories."
  exit 1
fi

# ----------------------------------------------------------------------
# Self-provisioning: the company validator runs this script inside the
# production backend container (which was built with `composer install
# --no-dev`). PHPUnit and Symfony dev runtime aren't present in that
# image, and the frontend has no node_modules. Install them just-in-time
# here so the same script works in:
#   - docker compose --profile test run test   (pre-provisioned)
#   - docker compose exec backend sh run_tests.sh   (production image)
#   - local developer machines with PHP 8.2 + Node 20
# The production image is NOT modified — this is runtime provisioning
# inside the container where the tests execute.
# ----------------------------------------------------------------------

cd "$BACKEND_DIR"

# Ensure a .env exists on disk for the Symfony Dotenv loader (composer
# post-install / cache:clear need it). Prefer the committed backend stub;
# never fail the whole suite if it is already present.
if [ ! -f .env ] && [ -f .env.example ]; then
  cp .env.example .env || true
fi

# If PHPUnit / dev deps are missing, install them. We pass --no-scripts
# so cache:clear does not run at install time (we call it ourselves only
# if it is available, below). This keeps provisioning idempotent and
# avoids blowing up on any Symfony bootstrap quirk in the validator env.
if [ ! -f vendor/bin/phpunit ]; then
  echo "--- Installing backend dev dependencies (phpunit, symfony/runtime) ---"
  composer install --optimize-autoloader --no-interaction --no-scripts 2>&1 || true
fi

# Prepare test database: run migrations and load fixtures
echo "--- Preparing test database ---"
php bin/console doctrine:database:create --if-not-exists --env=test 2>&1 || true
php bin/console doctrine:migrations:migrate --no-interaction --env=test 2>&1 || true
php bin/console doctrine:fixtures:load --no-interaction --env=test 2>&1 || true

echo "--- Backend Unit Tests (tests/unit_tests/) ---"
php vendor/bin/phpunit tests/unit_tests/ --testdox 2>&1 || BACKEND_UNIT=1
[ $BACKEND_UNIT -eq 0 ] && echo "✅ Backend Unit PASSED" || echo "❌ Backend Unit FAILED"

echo "--- Backend API Tests (tests/api_tests/) ---"
php vendor/bin/phpunit tests/api_tests/ --testdox 2>&1 || BACKEND_API=1
[ $BACKEND_API -eq 0 ] && echo "✅ Backend API PASSED" || echo "❌ Backend API FAILED"

echo "--- Frontend Unit Tests (tests/unit_tests/) ---"
cd "$FRONTEND_DIR"

# Install frontend dev deps on demand so vitest.config.ts (which imports
# vitest) resolves. The production frontend image is served by Nginx and
# does not carry node_modules; the test run must install them JIT.
if [ ! -d node_modules ] || [ ! -d node_modules/vitest ]; then
  if command -v npm > /dev/null; then
    echo "--- Installing frontend dev dependencies (vitest, @testing-library) ---"
    npm ci --no-audit --no-fund 2>&1 || npm install --no-audit --no-fund 2>&1 || true
  else
    echo "WARNING: npm not found in container; frontend tests may fail"
  fi
fi

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
