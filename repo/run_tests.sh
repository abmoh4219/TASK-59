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
