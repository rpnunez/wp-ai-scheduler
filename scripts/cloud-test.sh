#!/usr/bin/env bash
# Test runner for Claude Code cloud sessions, which have no Docker daemon and
# so cannot use `make test-session`. Entry point: `make test-cloud ARGS="..."`.
#
# Mode comes from AIPS_CLOUD_TEST_MODE (default: full) and can be overridden
# per run with --syntax or --full as the first argument.
#
#   full    WordPress PHPUnit suite against the local MariaDB (same steps as
#           .github/workflows/phpunit-tests.yml). Several test files run in
#           one PHPUnit process; other arguments (e.g. --filter) pass through.
#           No test files means the whole suite.
#   syntax  php -l on the given PHP files (or, with none, every PHP file changed
#           against origin/main plus uncommitted changes), then
#           `composer lint:repository-boundary`.
set -uo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_DIR="$REPO_DIR/ai-post-scheduler"
MODE="${AIPS_CLOUD_TEST_MODE:-full}"

case "${1:-}" in
	--syntax) MODE="syntax"; shift ;;
	--full) MODE="full"; shift ;;
esac

export WP_TESTS_DIR="${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}"
export WP_CORE_DIR="${WP_CORE_DIR:-/tmp/wordpress}"

FILES=()
OPTS=()
for arg in "$@"; do
	if [[ "$arg" == -* || "${#OPTS[@]}" -gt 0 && "${OPTS[-1]}" == "--filter" ]]; then
		OPTS+=("$arg")
	else
		FILES+=("${arg#ai-post-scheduler/}")
	fi
done

run_syntax() {
	local status=0 targets=()
	if [[ "${#FILES[@]}" -gt 0 ]]; then
		for f in "${FILES[@]}"; do
			if [[ "$f" == /* ]]; then targets+=("$f")
			elif [[ -f "$PLUGIN_DIR/$f" ]]; then targets+=("$PLUGIN_DIR/$f")
			else targets+=("$REPO_DIR/$f"); fi
		done
	else
		local base
		base="$(git -C "$REPO_DIR" merge-base HEAD origin/main 2>/dev/null || echo HEAD)"
		while IFS= read -r f; do
			[[ -f "$REPO_DIR/$f" ]] && targets+=("$REPO_DIR/$f")
		done < <( { git -C "$REPO_DIR" diff --name-only "$base" -- '*.php'; git -C "$REPO_DIR" ls-files --others --exclude-standard -- '*.php'; } | grep -v '/vendor/' | sort -u)
	fi

	if [[ "${#targets[@]}" -eq 0 ]]; then
		echo "No changed PHP files to lint."
	else
		echo "php -l on ${#targets[@]} file(s)..."
		for f in "${targets[@]}"; do
			php -l "$f" >/dev/null || { php -l "$f"; status=1; }
		done
		[[ "$status" -eq 0 ]] && echo "Syntax OK."
	fi

	echo "Repository boundary lint..."
	(cd "$PLUGIN_DIR" && php tools/check-repository-boundary.php) || status=1
	return "$status"
}

run_full() {
	bash "$REPO_DIR/scripts/cloud-session-start.sh" --force

	if ! mysqladmin ping --silent >/dev/null 2>&1; then
		echo "MariaDB is not running; cannot run the WordPress suite. Try: make test-cloud ARGS=\"--syntax\"" >&2
		return 1
	fi

	if [[ ! -f "$WP_TESTS_DIR/includes/functions.php" || ! -f "$WP_CORE_DIR/wp-settings.php" ]]; then
		echo "Installing the WordPress test library..."
		(cd "$PLUGIN_DIR" && bash ../scripts/install-wp-tests.sh wordpress_test root '' 127.0.0.1 latest true) || return 1
	fi

	cd "$PLUGIN_DIR" || return 1
	local config="phpunit.xml" session_config=""
	if [[ "${#FILES[@]}" -gt 1 ]]; then
		# PHPUnit 9 takes one path: list the files in a temporary config.
		session_config="phpunit.cloud-session.xml"
		local suite='<testsuites><testsuite name="Selected">'
		for f in "${FILES[@]}"; do suite+="<file>$f</file>"; done
		suite+='</testsuite></testsuites>'
		sed "/<testsuites>/,/<\/testsuites>/c\\
$suite" phpunit.xml > "$session_config"
		config="$session_config"
		FILES=()
	fi

	local status=0
	php vendor/bin/phpunit --configuration "$config" "${OPTS[@]}" "${FILES[@]}" || status=$?
	[[ -n "$session_config" ]] && rm -f "$session_config"
	return "$status"
}

case "$MODE" in
	syntax) run_syntax ;;
	full) run_full ;;
	*) echo "Unknown AIPS_CLOUD_TEST_MODE '$MODE' (use full or syntax)." >&2; exit 2 ;;
esac
