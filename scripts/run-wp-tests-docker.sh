#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
PLUGIN_DIR="$REPO_ROOT/ai-post-scheduler"
INSTALL_SCRIPT="$REPO_ROOT/scripts/install-wp-tests.sh"

MODE="${1:-test}"
case "$MODE" in
  test)
    COMPOSER_COMMAND="composer test:wp"
    ;;
  coverage|--coverage)
    COMPOSER_COMMAND="XDEBUG_MODE=coverage composer test:wp:coverage"
    ;;
  *)
    echo "Usage: bash scripts/run-wp-tests-docker.sh [test|coverage]" >&2
    exit 1
    ;;
esac

DB_CONTAINER="${AIPS_DOCKER_DB_CONTAINER:-wp-ai-scheduler-db}"
DB_NAME="${AIPS_WP_TEST_DB_NAME:-wp_ns_tests_docker}"
DB_USER="${AIPS_WP_TEST_DB_USER:-root}"
DB_PASS="${AIPS_WP_TEST_DB_PASS:-root}"
DB_HOST="${AIPS_WP_TEST_DB_HOST:-127.0.0.1:3307}"
WP_VERSION="${AIPS_WP_TEST_WP_VERSION:-latest}"
WP_TESTS_DIR_WIN="${WP_TESTS_DIR:-C:/tmp/wordpress-tests-lib-docker}"
WP_CORE_DIR_WIN="${WP_CORE_DIR:-C:/tmp/wordpress-docker}"

if command -v cygpath >/dev/null 2>&1; then
  WP_TESTS_DIR_UNIX="$(cygpath -u "$WP_TESTS_DIR_WIN")"
  WP_CORE_DIR_UNIX="$(cygpath -u "$WP_CORE_DIR_WIN")"
else
  WP_TESTS_DIR_UNIX="$WP_TESTS_DIR_WIN"
  WP_CORE_DIR_UNIX="$WP_CORE_DIR_WIN"
fi

require_command() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "Required command not found: $1" >&2
    exit 1
  fi
}

require_command docker
require_command svn

if ! command -v curl >/dev/null 2>&1 && ! command -v wget >/dev/null 2>&1; then
  echo "Either curl or wget is required." >&2
  exit 1
fi

if [[ ! -f "$INSTALL_SCRIPT" ]]; then
  echo "Install script not found: $INSTALL_SCRIPT" >&2
  exit 1
fi

echo "Ensuring Docker DB container is up..."
(
  cd "$REPO_ROOT"
  docker compose up -d db >/dev/null
)

echo "Waiting for Docker DB health..."
for _ in {1..60}; do
  STATUS="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$DB_CONTAINER" 2>/dev/null || true)"
  if [[ "$STATUS" == "healthy" ]]; then
    break
  fi
  sleep 2
done

STATUS="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$DB_CONTAINER" 2>/dev/null || true)"
if [[ "$STATUS" != "healthy" ]]; then
  echo "Docker DB container is not healthy: ${STATUS:-unknown}" >&2
  exit 1
fi

echo "Recreating disposable test database: $DB_NAME"
(
  cd "$REPO_ROOT"
  docker compose exec -T db mysql -uroot -proot -e "DROP DATABASE IF EXISTS \`$DB_NAME\`; CREATE DATABASE \`$DB_NAME\`;"
)

echo "Refreshing WordPress test library and core paths..."
rm -rf "$WP_TESTS_DIR_UNIX" "$WP_CORE_DIR_UNIX"

export WP_TESTS_DIR="$WP_TESTS_DIR_WIN"
export WP_CORE_DIR="$WP_CORE_DIR_WIN"

echo "Installing WordPress test library/config..."
bash "$INSTALL_SCRIPT" "$DB_NAME" "$DB_USER" "$DB_PASS" "$DB_HOST" "$WP_VERSION" true

echo "Running full WordPress PHPUnit suite against Docker-backed MySQL..."
cd "$PLUGIN_DIR"
eval "$COMPOSER_COMMAND"
