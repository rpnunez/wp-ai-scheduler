#!/usr/bin/env bash
# Run PHPUnit via scripts/run-docker-test.sh and leave the dev stack the way it
# was found: services that were NOT running before are stopped afterwards (data
# volumes are kept); services that were already running are left alone.
#
# Usage (normally through make):
#   make test-session ARGS="tests/Test_A.php tests/Test_B.php"
#   bash scripts/test-session.sh tests/Test_A.php --filter test_name
#
# The PHPUnit exit code is preserved. Cleanup also runs on Ctrl+C / termination.

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$REPO_ROOT"

if ! command -v docker >/dev/null 2>&1; then
  echo "docker is required to run the tests." >&2
  exit 1
fi
if docker compose version >/dev/null 2>&1; then
  COMPOSE=(docker compose)
else
  COMPOSE=(docker-compose)
fi

# Services the test runner brings up. Only these are ever stopped here.
MANAGED=(web db)

is_running() {
  "${COMPOSE[@]}" ps --status running --services 2>/dev/null | tr -d '\r' | grep -qx "$1"
}

TO_STOP=()
for svc in "${MANAGED[@]}"; do
  if is_running "$svc"; then
    echo "[test-session] $svc was already running; will leave it running."
  else
    TO_STOP+=("$svc")
  fi
done

cleanup() {
  if [ "${#TO_STOP[@]}" -gt 0 ]; then
    echo "[test-session] Stopping services this session started: ${TO_STOP[*]}"
    "${COMPOSE[@]}" stop "${TO_STOP[@]}"
  else
    echo "[test-session] Nothing to stop (stack was already running)."
  fi
}
trap cleanup EXIT
trap 'exit 130' INT TERM

bash "$SCRIPT_DIR/run-docker-test.sh" "$@"
rc=$?
echo "[test-session] PHPUnit exit code: $rc"
exit "$rc"
