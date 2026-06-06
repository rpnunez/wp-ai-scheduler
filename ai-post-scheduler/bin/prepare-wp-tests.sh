#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
INSTALL_SCRIPT="$PLUGIN_DIR/../scripts/install-wp-tests.sh"

try_install_svn() {
	if command -v svn >/dev/null 2>&1; then
		return 0
	fi

	echo "svn not found. Attempting best-effort installation..."

	if command -v apt-get >/dev/null 2>&1; then
		if command -v sudo >/dev/null 2>&1; then
			sudo apt-get update && sudo apt-get install -y subversion && return 0
		fi
		apt-get update && apt-get install -y subversion && return 0
	fi

	if command -v apk >/dev/null 2>&1; then
		apk add --no-cache subversion && return 0
	fi

	if command -v dnf >/dev/null 2>&1; then
		if command -v sudo >/dev/null 2>&1; then
			sudo dnf install -y subversion && return 0
		fi
		dnf install -y subversion && return 0
	fi

	if command -v yum >/dev/null 2>&1; then
		if command -v sudo >/dev/null 2>&1; then
			sudo yum install -y subversion && return 0
		fi
		yum install -y subversion && return 0
	fi

	if command -v zypper >/dev/null 2>&1; then
		if command -v sudo >/dev/null 2>&1; then
			sudo zypper --non-interactive install subversion && return 0
		fi
		zypper --non-interactive install subversion && return 0
	fi

	if command -v pacman >/dev/null 2>&1; then
		if command -v sudo >/dev/null 2>&1; then
			sudo pacman -Sy --noconfirm subversion && return 0
		fi
		pacman -Sy --noconfirm subversion && return 0
	fi

	if command -v brew >/dev/null 2>&1; then
		brew install subversion && return 0
	fi

	echo "Unable to auto-install svn in this environment. Continuing with the packaged wp-phpunit fallback if available."
	return 1
}

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

	if [[ "${AIPS_WP_TEST_AUTO_INSTALL_SVN:-true}" == "true" ]]; then
		try_install_svn || true
	fi

	echo "Setting up WordPress test library and test config..."
	bash "$INSTALL_SCRIPT" "$DB_NAME" "$DB_USER" "$DB_PASS" "$DB_HOST" "$WP_VERSION" "$SKIP_DB_CREATE"
fi
