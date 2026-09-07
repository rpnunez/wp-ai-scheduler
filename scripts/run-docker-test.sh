#!/usr/bin/env bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

# Default to --no-coverage so PHPUnit doesn't try to boot its coverage driver
# when Xdebug is off (the default since PR #2038). If the caller passes any
# explicit coverage flag, honor it and skip our injection so coverage still
# works via `bash scripts/run-docker-test.sh --coverage-html out`.
PHPUNIT_EXTRA_ARGS=()
_wants_coverage=0
for arg in "$@"; do
  case "$arg" in
    --coverage*|--no-coverage) _wants_coverage=1; break ;;
  esac
done
if [ "$_wants_coverage" -eq 0 ]; then
  PHPUNIT_EXTRA_ARGS+=(--no-coverage)
fi

# Ensure test config file exists in the web container
ensure_container_config() {
  docker compose exec -T web bash -c '
    if [ ! -f /tmp/wp-tests-config.php ]; then
      cat << "EOF" > /tmp/wp-tests-config.php
<?php
define("ABSPATH", "/var/www/html/");
define("WP_DEBUG", false);
define("DB_NAME", "wp_tests");
define("DB_USER", "root");
define("DB_PASSWORD", "root");
define("DB_HOST", "db");
define("DB_CHARSET", "utf8");
define("DB_COLLATE", "");
$table_prefix = "wptests_";
define("WP_TESTS_DOMAIN", "example.org");
define("WP_TESTS_EMAIL", "admin@example.org");
define("WP_TESTS_TITLE", "Test Blog");
define("WP_PHP_BINARY", "php");
define("WPLANG", "");
EOF
    fi
  '
}

# If running on host machine
if [ ! -f "/.dockerenv" ] && command -v docker >/dev/null 2>&1; then
  cd "$REPO_ROOT"
  
  # Ensure Docker containers are running
  docker compose up -d db web >/dev/null 2>&1
  ensure_container_config
  
  # Forward test arguments directly to phpunit inside the container
  docker compose exec -T \
    -w /var/www/html/wp-content/plugins/ai-post-scheduler \
    -e WP_TESTS_DIR=/var/www/html/wp-content/plugins/ai-post-scheduler/vendor/wp-phpunit/wp-phpunit \
    -e WP_CORE_DIR=/var/www/html \
    -e WP_PHPUNIT__TESTS_CONFIG=/tmp/wp-tests-config.php \
    -e AIPS_WP_TEST_SKIP_DB_CREATE=true \
    web php vendor/bin/phpunit "${PHPUNIT_EXTRA_ARGS[@]}" "$@"
  exit $?
fi

# If executing directly inside the web container
PLUGIN_DIR="/var/www/html/wp-content/plugins/ai-post-scheduler"
CONFIG_FILE="/tmp/wp-tests-config.php"

if [ ! -f "$CONFIG_FILE" ]; then
  cat << 'EOF' > "$CONFIG_FILE"
<?php
define('ABSPATH', '/var/www/html/');
define('WP_DEBUG', false);
define('DB_NAME', 'wp_tests');
define('DB_USER', 'root');
define('DB_PASSWORD', 'root');
define('DB_HOST', 'db');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');
$table_prefix = 'wptests_';
define('WP_TESTS_DOMAIN', 'example.org');
define('WP_TESTS_EMAIL', 'admin@example.org');
define('WP_TESTS_TITLE', 'Test Blog');
define('WP_PHP_BINARY', 'php');
define('WPLANG', '');
EOF
fi

cd "$PLUGIN_DIR"
export WP_TESTS_DIR="$PLUGIN_DIR/vendor/wp-phpunit/wp-phpunit"
export WP_CORE_DIR="/var/www/html"
export WP_PHPUNIT__TESTS_CONFIG="$CONFIG_FILE"
export AIPS_WP_TEST_SKIP_DB_CREATE="true"

php vendor/bin/phpunit "${PHPUNIT_EXTRA_ARGS[@]}" "$@"
