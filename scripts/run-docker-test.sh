#!/usr/bin/env bash
# Run the plugin's PHPUnit suite INSIDE the `web` container of the dev stack,
# against a separate throwaway database (default: wp_tests) on the `db` service.
# No host PHP, composer or svn needed.
#
# Usage:
#   bash scripts/run-docker-test.sh                       # whole suite
#   bash scripts/run-docker-test.sh tests/Test_X.php      # one file
#   bash scripts/run-docker-test.sh --filter test_name    # any PHPUnit args
#   bash scripts/run-docker-test.sh --fresh               # drop/recreate the test DB first
#   bash scripts/run-docker-test.sh --coverage-text       # coverage (enables Xdebug coverage)
#
# `--no-coverage` is added by default so PHPUnit doesn't try to boot a coverage
# driver while Xdebug is off. Any explicit --coverage*/--no-coverage argument is
# honored instead.
#
# For the host-side CI-style runner (needs host PHP/composer), see
# scripts/run-wp-tests-docker.sh (`make test-ci`).

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

# ---- argument handling ------------------------------------------------------
FRESH=0
WANTS_COVERAGE=0
HONOR_COVERAGE_FLAG=0
PHPUNIT_ARGS=()
for arg in "$@"; do
  case "$arg" in
    --fresh) FRESH=1; continue ;;
    --coverage*) WANTS_COVERAGE=1; HONOR_COVERAGE_FLAG=1 ;;
    --no-coverage) HONOR_COVERAGE_FLAG=1 ;;
  esac
  PHPUNIT_ARGS+=("$arg")
done
if [ "$HONOR_COVERAGE_FLAG" -eq 0 ]; then
  PHPUNIT_ARGS+=(--no-coverage)
fi

# ---- what runs inside the web container ------------------------------------
# Single-quoted heredoc: everything expands in the container, not on the host.
read -r -d '' CONTAINER_SCRIPT <<'EOF' || true
set -e
DB_HOST_ONLY="${WORDPRESS_DB_HOST:-db:3306}"
DB_HOST_ONLY="${DB_HOST_ONLY%%:*}"
ROOT_PW="${MYSQL_ROOT_PASSWORD:-root}"
TEST_DB="${AIPS_WP_TEST_DB_NAME:-wp_tests}"
CLIENT=mariadb
command -v mariadb >/dev/null 2>&1 || CLIENT=mysql

if [ "${AIPS_TEST_FRESH:-0}" = "1" ]; then
  echo "[test] Dropping test database ${TEST_DB}..."
  MYSQL_PWD="$ROOT_PW" "$CLIENT" --skip-ssl -h "$DB_HOST_ONLY" -uroot -e "DROP DATABASE IF EXISTS \`${TEST_DB}\`"
fi
MYSQL_PWD="$ROOT_PW" "$CLIENT" --skip-ssl -h "$DB_HOST_ONLY" -uroot -e "CREATE DATABASE IF NOT EXISTS \`${TEST_DB}\`"

CONFIG_FILE=/tmp/wp-tests-config.php
PW_ESCAPED="${ROOT_PW//\'/\\\'}"
cat > "$CONFIG_FILE" <<PHP
<?php
define('ABSPATH', '/var/www/html/');
define('WP_DEBUG', false);
define('DB_NAME', '${TEST_DB}');
define('DB_USER', 'root');
define('DB_PASSWORD', '${PW_ESCAPED}');
define('DB_HOST', '${DB_HOST_ONLY}');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');
\$table_prefix = 'wptests_';
define('WP_TESTS_DOMAIN', 'example.org');
define('WP_TESTS_EMAIL', 'admin@example.org');
define('WP_TESTS_TITLE', 'Test Blog');
define('WP_PHP_BINARY', 'php');
define('WPLANG', '');
PHP

PLUGIN_DIR=/var/www/html/wp-content/plugins/ai-post-scheduler
cd "$PLUGIN_DIR"
export WP_TESTS_DIR="$PLUGIN_DIR/vendor/wp-phpunit/wp-phpunit"
export WP_CORE_DIR=/var/www/html
export WP_PHPUNIT__TESTS_CONFIG="$CONFIG_FILE"
export AIPS_WP_TEST_SKIP_DB_CREATE=true

if [ ! -x vendor/bin/phpunit ] && [ ! -f vendor/bin/phpunit ]; then
  echo "[test] vendor/bin/phpunit not found. Run: make composer-install (dev dependencies required)." >&2
  exit 1
fi

# An env var overrides the runtime xdebug.mode = off set by the entrypoint.
if [ "${AIPS_TEST_COVERAGE:-0}" = "1" ]; then
  export XDEBUG_MODE=coverage
fi

exec php vendor/bin/phpunit "$@"
EOF

# ---- inside the container already ------------------------------------------
if [ -f "/.dockerenv" ]; then
  AIPS_TEST_FRESH="$FRESH" AIPS_TEST_COVERAGE="$WANTS_COVERAGE" \
    exec bash -c "$CONTAINER_SCRIPT" _ "${PHPUNIT_ARGS[@]}"
fi

# ---- on the host ------------------------------------------------------------
if ! command -v docker >/dev/null 2>&1; then
  echo "docker is required to run the tests." >&2
  exit 1
fi
if docker compose version >/dev/null 2>&1; then
  COMPOSE=(docker compose)
else
  COMPOSE=(docker-compose)
fi

cd "$REPO_ROOT"

echo "[test] Ensuring db and web services are up and healthy..."
if "${COMPOSE[@]}" up --help 2>/dev/null | grep -q -- '--wait'; then
  "${COMPOSE[@]}" up -d --pull never --wait --wait-timeout 300 db web
else
  "${COMPOSE[@]}" up -d --pull never db web
fi

"${COMPOSE[@]}" exec -T \
  -e AIPS_TEST_FRESH="$FRESH" \
  -e AIPS_TEST_COVERAGE="$WANTS_COVERAGE" \
  web bash -c "$CONTAINER_SCRIPT" _ "${PHPUNIT_ARGS[@]}"
