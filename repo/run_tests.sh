#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

export TZ=UTC

mkdir -p test-results

# ---------------------------------------------------------------------------
# 1. PHPUnit tests
# ---------------------------------------------------------------------------
echo "=============================================="
echo "Running PHPUnit Tests"
echo "=============================================="

# Prefer running inside the Docker container when it is available, so that no
# local PHP / xmllint installation is required.  Fall back to a local vendor
# binary when the container is not running (e.g. CI without Docker).
if docker compose ps --services --filter status=running 2>/dev/null | grep -q "^php$"; then
    echo "(using Docker container 'php')"
    docker compose exec -T php \
        ./vendor/bin/phpunit --configuration /var/www/phpunit.xml \
        --testdox --log-junit /var/www/test-results/junit.xml \
        2>&1 | tee test-results/output.txt
else
    echo "(using local vendor binary)"
    ./backend/vendor/bin/phpunit --configuration phpunit.xml --testdox --log-junit test-results/junit.xml 2>&1 | tee test-results/output.txt
fi

PHP_EXIT_CODE=${PIPESTATUS[0]}

# ---------------------------------------------------------------------------
# 2. Frontend Jest tests (node:20-alpine via Docker)
# ---------------------------------------------------------------------------
echo ""
echo "=============================================="
echo "Running Frontend Tests (Jest)"
echo "=============================================="

FRONTEND_EXIT_CODE=0
if docker compose ps 2>/dev/null | grep -q "node\|campusops"; then
    echo "(using Docker 'node' service)"
    docker compose run --rm node sh -c \
        "cd /var/www/frontend && npm install --prefer-offline --no-audit --no-fund --silent && npx jest --ci" \
        2>&1 | tee -a test-results/output.txt
    FRONTEND_EXIT_CODE=${PIPESTATUS[0]}
elif command -v node >/dev/null 2>&1; then
    echo "(using local node)"
    cd frontend && npm install --silent && npx jest --ci 2>&1 | tee -a ../test-results/output.txt
    FRONTEND_EXIT_CODE=${PIPESTATUS[0]}
    cd ..
else
    echo "(SKIP — Docker 'node' service not running and 'node' not found locally)"
fi

# Combine exit codes: fail if either suite failed
EXIT_CODE=0
[ "$PHP_EXIT_CODE" -ne 0 ] && EXIT_CODE=$PHP_EXIT_CODE
[ "$FRONTEND_EXIT_CODE" -ne 0 ] && EXIT_CODE=$FRONTEND_EXIT_CODE

echo ""
echo "=============================================="
echo "Test Results Summary"
echo "=============================================="

if [ -f test-results/junit.xml ]; then
    TOTAL_TESTS=$(xmllint --xpath "string(//testsuites/testsuite/@tests)" test-results/junit.xml 2>/dev/null || echo "0")
    TOTAL_FAILURES=$(xmllint --xpath "string(//testsuites/testsuite/@failures)" test-results/junit.xml 2>/dev/null || echo "0")
    TOTAL_ERRORS=$(xmllint --xpath "string(//testsuites/testsuite/@errors)" test-results/junit.xml 2>/dev/null || echo "0")
    TOTAL_TIME=$(xmllint --xpath "string(//testsuites/testsuite/@time)" test-results/junit.xml 2>/dev/null || echo "0")
    TOTAL_ASSERTIONS=$(xmllint --xpath "string(//testsuite/testcase/@assertions)" test-results/junit.xml 2>/dev/null || echo "0")
    
    TOTAL_ASSERTIONS=$(echo "$TOTAL_ASSERTIONS" | head -1)
    
    echo ""
    echo "Tests:        $TOTAL_TESTS"
    echo "Failures:     $TOTAL_FAILURES"
    echo "Errors:      $TOTAL_ERRORS"
    echo "Assertions:  $TOTAL_ASSERTIONS"
    echo "Time:        ${TOTAL_TIME}s"
    echo ""
fi

echo ""
echo "=============================================="
if [ $EXIT_CODE -eq 0 ]; then
    echo "All tests passed successfully!"
else
    echo "Tests failed with exit code: $EXIT_CODE"
fi
echo "=============================================="

exit $EXIT_CODE