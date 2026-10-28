#!/usr/bin/env bash
# Full-suite test runner with a known-pre-existing-failures baseline.
#
# This repo's test suite has a backlog of pre-existing failures unrelated to
# any one change (confirmed 2026-10-27 by diffing against the exact
# pre-branch commit — see scripts/known-failing-tests.txt). Running the raw
# suite buries a real regression under ~175 lines of "this was already
# broken." This script tells you the one thing you actually want to know:
# did THIS run introduce anything new.
#
# Usage:
#   scripts/run-tests.sh                  # full suite
#   scripts/run-tests.sh tests/database/CourseOfferingMergeScheduleTest.php
#   scripts/run-tests.sh tests/database/Foo.php --filter testBar
#
# Exit code: 0 if no NEW failures (pre-existing ones are expected and do not
# fail the run), 1 if anything not in the baseline failed.
#
# Full phpunit output always goes to build/logs/phpunit-<timestamp>.log
# (build/ is gitignored) — this script's own stdout is just the summary.

set -uo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

export PATH="/c/xampp/php:$PATH"

LOG_DIR="build/logs"
mkdir -p "$LOG_DIR"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
LOG_FILE="$LOG_DIR/phpunit-$TIMESTAMP.log"
BASELINE="scripts/known-failing-tests.txt"

echo "Running: vendor/bin/phpunit $*"
echo "Full output: $LOG_FILE"
echo "(this can take several minutes for the full suite)"
echo

vendor/bin/phpunit "$@" >"$LOG_FILE" 2>&1
EXIT_CODE=$?

# PHPUnit prints failing/erroring tests as lines like "12) Some\Class::testName".
# Extract just "Class::testName", one per line, sorted, deduped.
CURRENT_FAILURES="$(grep -oE '^[0-9]+\) [A-Za-z0-9_\\]+::[A-Za-z0-9_]+' "$LOG_FILE" | sed -E 's/^[0-9]+\) //' | sort -u)"

if [ -f "$BASELINE" ]; then
    BASELINE_SORTED="$(grep -vE '^\s*#|^\s*$' "$BASELINE" | sort -u)"
else
    BASELINE_SORTED=""
fi

NEW_FAILURES="$(comm -23 <(echo "$CURRENT_FAILURES") <(echo "$BASELINE_SORTED") 2>/dev/null | grep -v '^$' || true)"
NOW_PASSING="$(comm -13 <(echo "$CURRENT_FAILURES") <(echo "$BASELINE_SORTED") 2>/dev/null | grep -v '^$' || true)"
KNOWN_COUNT="$(echo "$CURRENT_FAILURES" | grep -v '^$' | comm -12 - <(echo "$BASELINE_SORTED") | grep -cv '^$' || true)"

# PHPUnit's own one-line summary, e.g. "Tests: 459, Assertions: 1102, Errors: 40, Failures: 137."
SUMMARY_LINE="$(grep -E '^(OK|Tests:|FAILURES!|ERRORS!)' "$LOG_FILE" | tail -3 | tr '\n' ' ')"

echo "PHPUnit: $SUMMARY_LINE"
echo "Known pre-existing failures seen: ${KNOWN_COUNT:-0} (expected — see $BASELINE)"
echo

if [ -n "$NEW_FAILURES" ]; then
    echo "NEW FAILURES NOT IN THE BASELINE:"
    echo "$NEW_FAILURES" | sed 's/^/  - /'
    echo
    echo "These are real regressions (or the baseline is stale — see below). Check $LOG_FILE for details."
    FINAL_EXIT=1
else
    echo "No new failures beyond the known baseline."
    FINAL_EXIT=0
fi

if [ -n "$NOW_PASSING" ]; then
    echo
    echo "In the baseline but not failing here — this may mean they're fixed, or just that"
    echo "this run didn't include them. Only remove from $BASELINE after confirming with an"
    echo "isolated run of that specific file:"
    echo "$NOW_PASSING" | sed 's/^/  - /'
fi

echo
echo "Full log: $LOG_FILE"
exit $FINAL_EXIT
