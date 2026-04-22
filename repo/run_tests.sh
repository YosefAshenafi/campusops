#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

export TZ=UTC

mkdir -p test-results

# ---------------------------------------------------------------------------
# All tests run inside Docker (Rule 7). No host-side fallback.
# ---------------------------------------------------------------------------
if ! command -v docker >/dev/null 2>&1; then
    echo "ERROR: Docker is required to run the test suite." >&2
    echo "       Install Docker Desktop (https://docs.docker.com/get-docker/) and retry." >&2
    exit 127
fi

# Ensure the compose stack image for the php service is built and dependencies
# are installed. `docker compose run --rm --build php` rebuilds if needed and
# tears the container down after the command exits.
echo "=============================================="
echo "Preparing Docker test environment"
echo "=============================================="
docker compose build php composer >/dev/null
docker compose run --rm composer >/dev/null

# ---------------------------------------------------------------------------
# 1. PHPUnit tests (Docker)
# ---------------------------------------------------------------------------
echo "=============================================="
echo "Running PHPUnit Tests (via Docker)"
echo "=============================================="

PHP_EXIT_CODE=0
docker compose run --rm \
    -w /var/www \
    -v "$(pwd)/test-results:/var/www/test-results" \
    php \
    ./backend/vendor/bin/phpunit \
        --configuration /var/www/phpunit.xml \
        --testdox \
        --log-junit /var/www/test-results/junit.xml \
    2>&1 | tee test-results/output.txt
PHP_EXIT_CODE=${PIPESTATUS[0]}

# ---------------------------------------------------------------------------
# 2. Frontend Jest tests (Docker)
# ---------------------------------------------------------------------------
echo ""
echo "=============================================="
echo "Running Frontend Tests (Jest via Docker)"
echo "=============================================="

FRONTEND_EXIT_CODE=0
docker compose run --rm \
    -w /var/www/frontend \
    node \
    sh -c "npm install --prefer-offline --no-audit --no-fund --silent && npx jest --ci" \
    2>&1 | tee -a test-results/output.txt
FRONTEND_EXIT_CODE=${PIPESTATUS[0]}

# Combine exit codes: fail if either suite failed
EXIT_CODE=0
[ "$PHP_EXIT_CODE" -ne 0 ] && EXIT_CODE=$PHP_EXIT_CODE
[ "$FRONTEND_EXIT_CODE" -ne 0 ] && EXIT_CODE=$FRONTEND_EXIT_CODE

echo ""
echo "=============================================="
echo "Test Results Summary"
echo "=============================================="

if [ -f test-results/junit.xml ]; then
    # xmllint is available inside the PHP image. Run a single container that
    # extracts every summary attribute at once to avoid per-call startup cost.
    SUMMARY=$(docker compose run --rm -T \
        -v "$(pwd)/test-results:/var/www/test-results" \
        php sh -c '
            x() { xmllint --xpath "string($1)" /var/www/test-results/junit.xml 2>/dev/null || echo 0; }
            echo "TESTS=$(x //testsuites/testsuite/@tests)"
            echo "FAILURES=$(x //testsuites/testsuite/@failures)"
            echo "ERRORS=$(x //testsuites/testsuite/@errors)"
            echo "TIME=$(x //testsuites/testsuite/@time)"
            echo "ASSERTIONS=$(x //testsuite/testcase/@assertions | head -1)"
        ' 2>/dev/null || true)

    TOTAL_TESTS=$(echo "$SUMMARY" | awk -F= '/^TESTS=/{print $2}')
    TOTAL_FAILURES=$(echo "$SUMMARY" | awk -F= '/^FAILURES=/{print $2}')
    TOTAL_ERRORS=$(echo "$SUMMARY" | awk -F= '/^ERRORS=/{print $2}')
    TOTAL_TIME=$(echo "$SUMMARY" | awk -F= '/^TIME=/{print $2}')
    TOTAL_ASSERTIONS=$(echo "$SUMMARY" | awk -F= '/^ASSERTIONS=/{print $2}')

    echo ""
    echo "Tests:       ${TOTAL_TESTS:-0}"
    echo "Failures:    ${TOTAL_FAILURES:-0}"
    echo "Errors:      ${TOTAL_ERRORS:-0}"
    echo "Assertions:  ${TOTAL_ASSERTIONS:-0}"
    echo "Time:        ${TOTAL_TIME:-0}s"
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
