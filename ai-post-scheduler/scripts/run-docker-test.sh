#!/usr/bin/env bash
set -e

PLUGIN_DIR="/var/www/html/wp-content/plugins/ai-post-scheduler"
CONFIG_FILE="/tmp/wp-tests-config.php"

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

cd "$PLUGIN_DIR"
export WP_TESTS_DIR="$PLUGIN_DIR/vendor/wp-phpunit/wp-phpunit"
export WP_CORE_DIR="/var/www/html"
export WP_PHPUNIT__TESTS_CONFIG="$CONFIG_FILE"
export AIPS_WP_TEST_SKIP_DB_CREATE="true"

php vendor/bin/phpunit "$@"
