#!/usr/bin/env bash
# Run the plugin's PHPUnit suite INSIDE the `web` container of the dev stack,
# against a separate throwaway database (default: wp_tests) on the `db` service.
# No host PHP, composer or svn needed.
#
# Usage:
#   bash scripts/run-docker-test.sh                       # whole suite
#   bash scripts/run-docker-test.sh tests/Test_X.php      # one file
#   bash scripts/run-docker-test.sh tests/A.php tests/B.php   # several files
#   bash scripts/run-docker-test.sh --filter test_name    # any PHPUnit args
#   bash scripts/run-docker-test.sh --fresh               # drop/recreate the test DB first
#   bash scripts/run-docker-test.sh --coverage-text       # coverage (enables Xdebug coverage)
#
# `--no-coverage` is added by default so PHPUnit doesn't try to boot a coverage
# driver while Xdebug is off. Any explicit --coverage*/--no-coverage argument is
# honored instead.
#
# PHPUnit 9 accepts a single positional test path and silently ignores the rest,
# so several `*.php` test files are run as ONE PHPUnit process through a
# temporary config that lists them (one bootstrap, one summary, coverage and log
# outputs cover all files). `--fail-on-empty-test-suite` is always added so a
# run that executes zero tests (bad path or --filter) exits non-zero.
#
# Git Bash path rewriting of args like --filter "/^Foo/" is disabled by the
# Makefile (MSYS_NO_PATHCONV); always run through `make`.
#
# AIPS_TEST_DRY_RUN=1 prints the parsed files/args and exits (used by
# scripts/tests/test-run-docker-test.sh).
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
PHPUNIT_ARGS=(--fail-on-empty-test-suite)
TEST_FILES=()
# Only `*.php` arguments are treated as test files, so just the options whose
# value can itself be a .php file need listing here.
VALUE_OPTS=" --filter --bootstrap --printer --include-path --cache-result-file "
EXPECT_VALUE=0
for arg in "$@"; do
  if [ "$EXPECT_VALUE" -eq 1 ]; then
    EXPECT_VALUE=0
    PHPUNIT_ARGS+=("$arg")
    continue
  fi
  case "$arg" in
    --fresh) FRESH=1; continue ;;
    --coverage*) WANTS_COVERAGE=1; HONOR_COVERAGE_FLAG=1 ;;
    --no-coverage) HONOR_COVERAGE_FLAG=1 ;;
  esac
  case "$arg" in
    -*)
      case "$VALUE_OPTS" in *" $arg "*) EXPECT_VALUE=1 ;; esac
      PHPUNIT_ARGS+=("$arg")
      ;;
    *.php)
      TEST_FILES+=("$arg")
      ;;
    *)
      PHPUNIT_ARGS+=("$arg")
      ;;
  esac
done
if [ "$HONOR_COVERAGE_FLAG" -eq 0 ]; then
  PHPUNIT_ARGS+=(--no-coverage)
fi
# Newline-separated so it survives an env var; test paths never contain newlines.
TEST_FILES_JOINED=""
if [ "${#TEST_FILES[@]}" -gt 0 ]; then
  TEST_FILES_JOINED="$(printf '%s\n' "${TEST_FILES[@]}")"
fi

if [ "${AIPS_TEST_DRY_RUN:-0}" = "1" ]; then
  echo "FILES=${TEST_FILES[*]:-}"
  echo "ARGS=${PHPUNIT_ARGS[*]}"
  exit 0
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

# PHPUnit 9 only takes one positional path. One file: pass it through. Several:
# list them in a temporary copy of phpunit.xml and run a single process.
FILES=()
if [ -n "${AIPS_TEST_FILES:-}" ]; then
  while IFS= read -r f; do
    FILES+=("$f")
  done <<< "$AIPS_TEST_FILES"
fi

PHPUNIT_TARGET=()
if [ "${#FILES[@]}" -eq 1 ]; then
  PHPUNIT_TARGET=("${FILES[0]}")
elif [ "${#FILES[@]}" -gt 1 ]; then
  SESSION_CONFIG="phpunit.session.$$.xml"
  trap 'rm -f "$SESSION_CONFIG"' EXIT
  SUITE='<testsuites><testsuite name="Selected">'
  for f in "${FILES[@]}"; do SUITE+="<file>$f</file>"; done
  SUITE+='</testsuite></testsuites>'
  sed "/<testsuites>/,/<\/testsuites>/c\\
$SUITE" phpunit.xml > "$SESSION_CONFIG"
  PHPUNIT_TARGET=(--configuration "$SESSION_CONFIG")
fi

rc=0
php vendor/bin/phpunit "$@" ${PHPUNIT_TARGET[@]+"${PHPUNIT_TARGET[@]}"} || rc=$?
exit "$rc"
EOF

# ---- inside the container already ------------------------------------------
if [ -f "/.dockerenv" ]; then
  AIPS_TEST_FRESH="$FRESH" AIPS_TEST_COVERAGE="$WANTS_COVERAGE" \
    AIPS_TEST_FILES="$TEST_FILES_JOINED" \
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
  -e AIPS_TEST_FILES="$TEST_FILES_JOINED" \
  web bash -c "$CONTAINER_SCRIPT" _ "${PHPUNIT_ARGS[@]}"
