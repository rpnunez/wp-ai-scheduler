#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
INSTALL_SCRIPT="$PLUGIN_DIR/../scripts/install-wp-tests.sh"

if [[ ! -f "$PLUGIN_DIR/vendor/bin/phpunit" ]]; then
	echo "phpunit binary not found. Installing Composer dependencies..."
	(
		cd "$PLUGIN_DIR"
		composer install --no-interaction --prefer-dist
	)
fi

if [[ ! -f "$INSTALL_SCRIPT" ]]; then
	echo "WordPress test installer not found: $INSTALL_SCRIPT" >&2
	exit 1
fi

: "${WP_TESTS_DIR:=/tmp/wordpress-tests-lib}"
: "${WP_CORE_DIR:=/tmp/wordpress}"

export WP_TESTS_DIR
export WP_CORE_DIR

needs_install=0
if [[ ! -f "$WP_TESTS_DIR/includes/functions.php" ]]; then
	needs_install=1
fi
if [[ ! -f "$WP_CORE_DIR/wp-load.php" ]]; then
	needs_install=1
fi
if [[ ! -f "$WP_TESTS_DIR/wp-tests-config.php" ]]; then
	needs_install=1
fi

if [[ "$needs_install" -eq 1 ]]; then
	DB_NAME="${AIPS_WP_TEST_DB_NAME:-${WP_TESTS_DB_NAME:-wp_tests}}"
	DB_USER="${AIPS_WP_TEST_DB_USER:-${WP_TESTS_DB_USER:-root}}"
	DB_PASS="${AIPS_WP_TEST_DB_PASS:-${WP_TESTS_DB_PASS:-root}}"
	DB_HOST="${AIPS_WP_TEST_DB_HOST:-${WP_TESTS_DB_HOST:-127.0.0.1}}"
	WP_VERSION="${AIPS_WP_TEST_WP_VERSION:-${WP_TESTS_WP_VERSION:-latest}}"
	SKIP_DB_CREATE="${AIPS_WP_TEST_SKIP_DB_CREATE:-${WP_TESTS_SKIP_DB_CREATE:-false}}"

	echo "Setting up WordPress test library and test config..."
	bash "$INSTALL_SCRIPT" "$DB_NAME" "$DB_USER" "$DB_PASS" "$DB_HOST" "$WP_VERSION" "$SKIP_DB_CREATE"
fi
