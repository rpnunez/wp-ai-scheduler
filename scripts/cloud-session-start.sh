#!/usr/bin/env bash
# Prepares a Claude Code cloud session for testing. Runs from the SessionStart
# hook in .claude/settings.json and from scripts/cloud-test.sh.
#
# The cloud environment's setup script installs MariaDB, svn and the WordPress
# test library once per container build, but services it starts do not survive
# into the session, so MariaDB is started here.
#
# AIPS_CLOUD_TEST_MODE (environment variable, set in the cloud environment):
#   full   (default) start MariaDB so the WordPress PHPUnit suite can run
#   syntax           skip the database; only php -l and lint checks are used
#
# Does nothing outside cloud sessions (CLAUDE_CODE_REMOTE != true) and never
# fails the session: problems are reported and the script exits 0.

MODE="${AIPS_CLOUD_TEST_MODE:-full}"
WP_TESTS_DIR="${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}"
WP_CORE_DIR="${WP_CORE_DIR:-/tmp/wordpress}"

if [[ "${CLAUDE_CODE_REMOTE:-}" != "true" && "${1:-}" != "--force" ]]; then
	exit 0
fi

if [[ "$MODE" == "syntax" ]]; then
	echo "[aips-cloud] AIPS_CLOUD_TEST_MODE=syntax: database not started; use 'make test-cloud' for syntax/lint checks."
	exit 0
fi

if ! command -v mysqladmin >/dev/null 2>&1; then
	echo "[aips-cloud] MariaDB is not installed (the environment setup script did not run). Use AIPS_CLOUD_TEST_MODE=syntax or fix the setup script."
	exit 0
fi

if ! mysqladmin ping --silent >/dev/null 2>&1; then
	service mariadb start >/dev/null 2>&1 || (mysqld_safe >/dev/null 2>&1 &)
	for _ in $(seq 1 30); do
		mysqladmin ping --silent >/dev/null 2>&1 && break
		sleep 1
	done
fi

if ! mysqladmin ping --silent >/dev/null 2>&1; then
	echo "[aips-cloud] MariaDB did not start; WordPress PHPUnit tests will fail."
	exit 0
fi

mysql -uroot -e "CREATE DATABASE IF NOT EXISTS wordpress_test;" >/dev/null 2>&1

if [[ ! -f "$WP_TESTS_DIR/includes/functions.php" || ! -f "$WP_CORE_DIR/wp-settings.php" ]]; then
	echo "[aips-cloud] MariaDB is up, but the WordPress test library is missing from $WP_TESTS_DIR; 'make test-cloud' will install it."
	exit 0
fi

echo "[aips-cloud] MariaDB is up and the WordPress test library is installed. Run tests with: make test-cloud ARGS=\"tests/Test_X.php\""
exit 0
