#!/usr/bin/env bash
# =============================================================================
# qa-lib.sh — shared helpers for the QA build tooling.
#
# Sourced by qa-build.sh, qa-up.sh, qa-seed.sh and qa-down.sh. Not executable
# on its own.
#
# A "QA build" is a bundle of N open pull requests merged onto a fresh branch
# off main, plus an isolated Docker stack that runs that branch. Every build is
# identified by its sorted, de-duplicated PR id list:
#
#   PR ids   1888,1887      (order and duplicates do not matter)
#   key      1887-1888      (filesystem / compose safe)
#   branch   qa-build-1887,1888
#   project  qabuild-1887-1888   (docker compose -p)
#   dir      .qa-builds/1887-1888/
#
# Because the key is derived from *sorted* ids, asking for the same PR set
# twice always lands on the same build instead of creating a duplicate.
# =============================================================================

# Separator used between PR ids in the git branch name. Defaults to a comma so
# the branch reads qa-build-1887,1888. Commas are legal in git refs but show up
# URL-encoded (%2C) on github.com; set QA_ID_SEPARATOR=- for hyphens instead.
: "${QA_ID_SEPARATOR:=,}"

# Port ranges, one per service and deliberately non-overlapping. A build draws
# a random free port from each range and remembers it, so builds created at
# different times do not queue up behind each other on adjacent numbers and a
# torn-down build's port is not immediately handed to the next one. Pass an
# explicit WordPress port with --port when you want a stable, memorable URL.
# All four ranges sit clear of the main dev stack (8080/3307/8082/9003).
: "${QA_WP_PORT_MIN:=8100}"
: "${QA_WP_PORT_MAX:=8299}"
: "${QA_PMA_PORT_MIN:=8300}"
: "${QA_PMA_PORT_MAX:=8399}"
: "${QA_DB_PORT_MIN:=3400}"
: "${QA_DB_PORT_MAX:=3499}"
: "${QA_XDEBUG_PORT_MIN:=9100}"
: "${QA_XDEBUG_PORT_MAX:=9199}"

: "${QA_DIR_NAME:=.qa-builds}"
: "${QA_BASE_BRANCH:=main}"
: "${QA_REMOTE:=origin}"

# One image shared by every QA build; only the bind-mounted plugin source and
# the database differ between them.
: "${QA_IMAGE:=wp-ai-scheduler-qa:latest}"

# -----------------------------------------------------------------------------
# Output
# -----------------------------------------------------------------------------

if [[ -t 1 ]]; then
	QA_BLUE=$'\033[0;34m'
	QA_GREEN=$'\033[0;32m'
	QA_YELLOW=$'\033[0;33m'
	QA_RED=$'\033[0;31m'
	QA_DIM=$'\033[2m'
	QA_NC=$'\033[0m'
else
	QA_BLUE="" QA_GREEN="" QA_YELLOW="" QA_RED="" QA_DIM="" QA_NC=""
fi

qa_info() { printf '%s==>%s %s\n' "$QA_BLUE" "$QA_NC" "$*"; }
qa_ok() { printf '%s  ok%s %s\n' "$QA_GREEN" "$QA_NC" "$*"; }
qa_warn() { printf '%swarn%s %s\n' "$QA_YELLOW" "$QA_NC" "$*" >&2; }
qa_err() { printf '%s err%s %s\n' "$QA_RED" "$QA_NC" "$*" >&2; }
qa_dim() { printf '%s%s%s\n' "$QA_DIM" "$*" "$QA_NC"; }
qa_die() {
	qa_err "$*"
	exit 1
}

# -----------------------------------------------------------------------------
# Paths and naming
# -----------------------------------------------------------------------------

qa_repo_root() {
	git rev-parse --show-toplevel 2>/dev/null ||
		qa_die "not inside a git repository"
}

# Normalize a PR list into sorted, de-duplicated, comma-separated ids.
# Accepts "1888,1887", "1888 1887", "#1888, 1887" and mixtures thereof.
qa_normalize_prs() {
	local raw="$*"
	local -a ids=()
	local token

	raw="${raw//#/ }"
	raw="${raw//,/ }"

	for token in $raw; do
		[[ "$token" =~ ^[0-9]+$ ]] ||
			qa_die "invalid pull request id: '$token' (expected a number)"
		ids+=("$((10#$token))")
	done

	[[ ${#ids[@]} -gt 0 ]] || qa_die "no pull request ids given"

	printf '%s\n' "${ids[@]}" | sort -n -u | paste -sd, -
}

# 1887,1888 -> 1887-1888
qa_key_from_prs() {
	local prs="$1"
	printf '%s' "${prs//,/-}"
}

# 1887,1888 -> qa-build-1887,1888 (separator configurable)
qa_branch_from_prs() {
	local prs="$1"
	printf 'qa-build-%s' "${prs//,/$QA_ID_SEPARATOR}"
}

# Compose project names must be [a-z0-9][a-z0-9_-]*, so no commas.
qa_project_from_key() {
	printf 'qabuild-%s' "$1"
}

qa_root_dir() { printf '%s/%s' "$(qa_repo_root)" "$QA_DIR_NAME"; }
qa_build_dir() { printf '%s/%s' "$(qa_root_dir)" "$1"; }
qa_src_dir() { printf '%s/%s/src' "$(qa_root_dir)" "$1"; }
qa_state_file() { printf '%s/%s/build.env' "$(qa_root_dir)" "$1"; }
qa_seed_dir() { printf '%s/_seed' "$(qa_root_dir)"; }
qa_seed_file() { printf '%s/prod.sql' "$(qa_seed_dir)"; }
qa_uploads_dir() { printf '%s/uploads' "$(qa_seed_dir)"; }
qa_has_uploads() { [[ -d "$(qa_uploads_dir)" ]] && [[ -n "$(ls -A "$(qa_uploads_dir)" 2>/dev/null)" ]]; }

# -----------------------------------------------------------------------------
# Build state
#
# Each build persists its identity and its allocated ports to build.env so that
# every later command (up, seed, down, urls) agrees on where the stack lives.
# -----------------------------------------------------------------------------

qa_state_exists() { [[ -f "$(qa_state_file "$1")" ]]; }

# Load a build's state into the current shell as QA_* variables.
qa_load_state() {
	local key="$1"
	local file
	file="$(qa_state_file "$key")"

	[[ -f "$file" ]] || qa_die "unknown QA build '$key' (no $file). Run 'make qa-build PRS=...' first."

	# shellcheck disable=SC1090
	set -a
	source "$file"
	set +a
}

qa_save_state() {
	local key="$1"
	shift
	local file
	file="$(qa_state_file "$key")"

	mkdir -p "$(dirname "$file")"
	printf '# Generated by scripts/qa-*.sh — do not edit by hand.\n' >"$file"
	printf '%s\n' "$@" >>"$file"
}

# Rewrite a single KEY=VALUE in an existing state file.
qa_set_state() {
	local key="$1" name="$2" value="$3"
	local file
	file="$(qa_state_file "$key")"

	[[ -f "$file" ]] || qa_die "no state file for build '$key'"

	if grep -q "^${name}=" "$file"; then
		local tmp="${file}.tmp"
		grep -v "^${name}=" "$file" >"$tmp"
		printf '%s=%s\n' "$name" "$value" >>"$tmp"
		mv "$tmp" "$file"
	else
		printf '%s=%s\n' "$name" "$value" >>"$file"
	fi
}

qa_list_keys() {
	local root
	root="$(qa_root_dir)"
	[[ -d "$root" ]] || return 0

	local dir
	for dir in "$root"/*/; do
		[[ -f "${dir}build.env" ]] || continue
		basename "$dir"
	done
}

# -----------------------------------------------------------------------------
# Port allocation
# -----------------------------------------------------------------------------

# True when nothing is listening on the port locally.
qa_port_free() {
	local port="$1"
	if (exec 3<>"/dev/tcp/127.0.0.1/${port}") 2>/dev/null; then
		return 1
	fi
	return 0
}

# Ports already recorded by other builds, so two builds never collide even
# while both are stopped and nothing is listening.
qa_claimed_ports() {
	local self="$1"
	local key file

	while read -r key; do
		[[ -n "$key" ]] || continue
		[[ "$key" != "$self" ]] || continue
		file="$(qa_state_file "$key")"
		[[ -f "$file" ]] || continue
		sed -n 's/^QA_\(WP\|DB\|PMA\|XDEBUG\)_PORT=//p' "$file"
	done < <(qa_list_keys)
}

# Pick a free port from a range. With a preferred port, validate and use it;
# otherwise sample the range at random, falling back to a linear scan so a
# crowded range still resolves rather than failing by bad luck.
qa_pick_port() {
	local min="$1" max="$2" self="$3" preferred="${4:-}" label="${5:-port}"
	local -a claimed
	mapfile -t claimed < <(qa_claimed_ports "$self")

	_qa_claimed() {
		local candidate="$1" c
		for c in "${claimed[@]:-}"; do
			[[ "$c" == "$candidate" ]] && return 0
		done
		return 1
	}

	if [[ -n "$preferred" ]]; then
		[[ "$preferred" =~ ^[0-9]+$ ]] && [[ "$preferred" -ge 1 && "$preferred" -le 65535 ]] ||
			qa_die "invalid $label '$preferred' (expected 1-65535)"
		_qa_claimed "$preferred" &&
			qa_die "$label $preferred is already assigned to another QA build (see 'make qa-list')"
		qa_port_free "$preferred" ||
			qa_die "$label $preferred is already in use on this machine"
		printf '%s' "$preferred"
		return 0
	fi

	local span=$((max - min + 1))
	local attempt port
	for ((attempt = 0; attempt < 60; attempt++)); do
		port=$((min + RANDOM % span))
		if ! _qa_claimed "$port" && qa_port_free "$port"; then
			printf '%s' "$port"
			return 0
		fi
	done

	for ((port = min; port <= max; port++)); do
		if ! _qa_claimed "$port" && qa_port_free "$port"; then
			printf '%s' "$port"
			return 0
		fi
	done

	qa_die "no free $label available in ${min}-${max} — tear down an old build with 'make qa-down'"
}

# Assign this build's four ports, echoing them as WP DB PMA XDEBUG.
qa_allocate_ports() {
	local self="$1" preferred_wp="${2:-}"

	# Trailing newline matters: callers use `read`, which reports failure on an
	# unterminated line and would abort them under `set -e`.
	printf '%s %s %s %s\n' \
		"$(qa_pick_port "$QA_WP_PORT_MIN" "$QA_WP_PORT_MAX" "$self" "$preferred_wp" "WordPress port")" \
		"$(qa_pick_port "$QA_DB_PORT_MIN" "$QA_DB_PORT_MAX" "$self" "" "database port")" \
		"$(qa_pick_port "$QA_PMA_PORT_MIN" "$QA_PMA_PORT_MAX" "$self" "" "phpMyAdmin port")" \
		"$(qa_pick_port "$QA_XDEBUG_PORT_MIN" "$QA_XDEBUG_PORT_MAX" "$self" "" "Xdebug port")"
}

# -----------------------------------------------------------------------------
# Docker compose
# -----------------------------------------------------------------------------

# Run docker compose against one build's isolated project. Requires the build's
# state to already be loaded (qa_load_state).
qa_compose() {
	local root
	root="$(qa_repo_root)"

	local -a files=(
		-f "$root/docker-compose.yml"
		-f "$root/docker-compose.qa.yml"
	)

	# In 'mount' uploads mode the shared media cache replaces this build's
	# uploads volume, which needs a third overlay. Every command for the build
	# must carry it, otherwise compose sees a changed mount and recreates the
	# container — so it is driven by persisted state, not by a flag.
	if [[ "${QA_UPLOADS_MODE:-}" == "mount" ]] && qa_has_uploads; then
		files+=(-f "$root/docker-compose.qa-uploads.yml")
	fi

	# WP_PORT/MYSQL_PORT/PHPMYADMIN_PORT/XDEBUG_PORT are what docker-compose.yml
	# actually reads; shell values take precedence over any repo-root .env.
	QA_PROJECT="$QA_PROJECT" \
		QA_SRC="$QA_SRC" \
		QA_UPLOADS_SRC="$(qa_uploads_dir)" \
		WP_PORT="$QA_WP_PORT" \
		MYSQL_PORT="$QA_DB_PORT" \
		PHPMYADMIN_PORT="$QA_PMA_PORT" \
		XDEBUG_PORT="$QA_XDEBUG_PORT" \
		docker compose \
		-p "$QA_PROJECT" \
		"${files[@]}" \
		"$@"
}

qa_require_docker() {
	command -v docker >/dev/null 2>&1 || qa_die "docker is not installed or not on PATH"
	docker compose version >/dev/null 2>&1 || qa_die "docker compose v2 is required"
	docker info >/dev/null 2>&1 || qa_die "the Docker daemon is not reachable — is Docker Desktop running?"
}

qa_wait_for_db() {
	local id status
	qa_info "Waiting for MariaDB to report healthy..."

	for _ in $(seq 1 60); do
		id="$(qa_compose ps -q db 2>/dev/null | head -n 1)"
		if [[ -n "$id" ]]; then
			status="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$id" 2>/dev/null || true)"
			[[ "$status" == "healthy" ]] && {
				qa_ok "database healthy"
				return 0
			}
		fi
		sleep 2
	done

	qa_die "database did not become healthy in time (try: make qa-logs PRS=$QA_PRS)"
}

qa_wait_for_wp() {
	qa_info "Waiting for WordPress to finish provisioning..."

	# Apache is started by CMD, which only runs once the entrypoint has finished
	# installing core and activating the plugin. A listening port is therefore
	# the signal that provisioning is complete. Polling `wp core is-installed`
	# alone is not: it returns true partway through the entrypoint, so callers
	# race the activation the entrypoint is still doing.
	local listening=0 _
	for _ in $(seq 1 120); do
		if ! qa_port_free "$QA_WP_PORT"; then
			listening=1
			break
		fi
		sleep 2
	done

	[[ "$listening" -eq 1 ]] ||
		qa_die "WordPress did not start serving on port ${QA_WP_PORT} in time (try: make qa-logs PRS=$QA_PRS)"

	for _ in $(seq 1 30); do
		if qa_compose exec -T web wp core is-installed \
			--path=/var/www/html --skip-plugins --skip-themes --allow-root >/dev/null 2>&1; then
			qa_ok "WordPress ready"
			return 0
		fi
		sleep 2
	done

	qa_die "WordPress is serving but not installed (try: make qa-logs PRS=$QA_PRS)"
}

# WP-CLI inside the build's web container. --skip-plugins/--skip-themes keeps
# maintenance commands working even when a production dump has active plugins
# that are not installed locally.
qa_wp() {
	qa_compose exec -T web wp "$@" \
		--path=/var/www/html --skip-plugins --skip-themes --allow-root
}

# WP-CLI with plugins and themes loaded. Needed for anything that must run
# activation hooks or plugin migrations — and useful because it surfaces fatals
# a production dump's plugin set might introduce.
qa_wp_full() {
	qa_compose exec -T web wp "$@" --path=/var/www/html --allow-root
}

qa_volume_exists() {
	docker volume inspect "$1" >/dev/null 2>&1
}

# -----------------------------------------------------------------------------
# Git
# -----------------------------------------------------------------------------

# Retry a git network operation with exponential backoff (2s, 4s, 8s, 16s).
qa_git_retry() {
	local attempt=1 delay=2
	while true; do
		if git "$@"; then
			return 0
		fi
		if [[ $attempt -ge 5 ]]; then
			qa_die "git $1 failed after 5 attempts"
		fi
		qa_warn "git $1 failed (attempt $attempt) — retrying in ${delay}s"
		sleep "$delay"
		attempt=$((attempt + 1))
		delay=$((delay * 2))
	done
}

qa_has_gh() { command -v gh >/dev/null 2>&1 && gh auth status >/dev/null 2>&1; }

# -----------------------------------------------------------------------------
# Plugin autoloader
# -----------------------------------------------------------------------------

# A fresh worktree carries only the autoloader files the repo commits, and those
# were generated *with* dev requirements — autoload_real.php requires
# myclabs/deep-copy, a PHPUnit transitive that only exists after `composer
# install`. Loading the plugin from an unprovisioned worktree therefore fatals.
#
# Regenerating with --no-dev fixes it, needs no network (the plugin has no
# runtime requirements beyond PHP itself), and is what the build should run
# anyway.
qa_provision_vendor() {
	local src="$1"
	local plugin_dir="${src}/ai-post-scheduler"

	[[ -f "${plugin_dir}/composer.json" ]] || return 0

	if command -v composer >/dev/null 2>&1; then
		if (cd "$plugin_dir" && composer dump-autoload --no-dev --no-interaction --quiet) 2>/dev/null; then
			qa_ok "plugin autoloader regenerated (--no-dev)"
			return 0
		fi
		qa_warn "host composer could not regenerate the autoloader — trying the QA image"
	fi

	if docker image inspect "$QA_IMAGE" >/dev/null 2>&1; then
		if docker run --rm -v "${plugin_dir}:/app" -w /app \
			-e COMPOSER_ALLOW_SUPERUSER=1 --entrypoint composer \
			"$QA_IMAGE" dump-autoload --no-dev --no-interaction --quiet 2>/dev/null; then
			qa_ok "plugin autoloader regenerated in the QA image (--no-dev)"
			return 0
		fi
	fi

	qa_warn "could not regenerate the plugin autoloader; activation will fatal. Fix with:"
	qa_warn "  (cd ${plugin_dir} && composer dump-autoload --no-dev)"
	return 0
}
