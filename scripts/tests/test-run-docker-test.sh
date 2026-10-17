#!/usr/bin/env bash
# Tests the argument parsing of scripts/run-docker-test.sh (no Docker needed).
# Usage: bash scripts/tests/test-run-docker-test.sh

set -uo pipefail

SCRIPT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/run-docker-test.sh"
FAILED=0

# expect <name> <expected FILES=> <expected ARGS=> -- <runner args...>
expect() {
  local name="$1" want_files="$2" want_args="$3"
  shift 4
  local out
  out="$(AIPS_TEST_DRY_RUN=1 bash "$SCRIPT" "$@")"
  if [ "$out" != "FILES=$want_files"$'\n'"ARGS=$want_args" ]; then
    echo "FAIL: $name"
    echo "  got:  ${out//$'\n'/ | }"
    echo "  want: FILES=$want_files | ARGS=$want_args"
    FAILED=1
  else
    echo "ok:   $name"
  fi
}

expect "no args runs the whole suite" "" "--fail-on-empty-test-suite --no-coverage" --
expect "one file" "tests/A.php" "--fail-on-empty-test-suite --no-coverage" -- tests/A.php
expect "several files" "tests/A.php tests/B.php" "--fail-on-empty-test-suite --no-coverage" -- tests/A.php tests/B.php
expect "filter value is not a file" "tests/A.php" "--fail-on-empty-test-suite --filter foo.php --no-coverage" -- tests/A.php --filter foo.php
expect "leading-slash filter kept intact" "tests/A.php" "--fail-on-empty-test-suite --filter /^Test_A/ --no-coverage" -- tests/A.php --filter '/^Test_A/'
expect "option values that are not .php stay options" "" "--fail-on-empty-test-suite --default-time-limit 5 --testdox-html out.html --no-coverage" -- --default-time-limit 5 --testdox-html out.html
expect "--bootstrap value is not a test file" "tests/A.php" "--fail-on-empty-test-suite --bootstrap tests/boot.php --no-coverage" -- --bootstrap tests/boot.php tests/A.php
expect "--fresh is consumed" "" "--fail-on-empty-test-suite --no-coverage" -- --fresh
expect "explicit coverage disables --no-coverage" "tests/A.php tests/B.php" "--fail-on-empty-test-suite --coverage-text" -- tests/A.php tests/B.php --coverage-text
expect "--list-tests passes through" "" "--fail-on-empty-test-suite --list-tests --no-coverage" -- --list-tests

exit "$FAILED"
