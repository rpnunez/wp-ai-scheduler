#!/usr/bin/env bash
# =============================================================================
# qa-up.sh — start the isolated Docker stack for a QA build.
#
#   ./scripts/qa-up.sh 1887 1888              # build it if needed, then run it
#   ./scripts/qa-up.sh --prs 1887,1888 --db keep
#   ./scripts/qa-up.sh --prs 1887,1888 --db clone:dev
#
# Each build runs on its own compose project, so its containers, network,
# database and WordPress core are separate from the dev stack on 8080 and from
# every other QA build. Ports are allocated once per build and remembered.
#
# DATABASE MODES (--db)
#   seed          Wipe this build's database and import the cached production
#                 dump, then rewrite URLs for this build's port
#   keep          Reuse whatever is already in this build's database
#   fresh         Vanilla WordPress install, no production data
#   clone:<key>   Copy another build's database ('clone:dev' = the main stack)
#
# When --db is omitted the mode is inferred, and auto-seeding only ever happens
# for a build that has no database yet:
#   database already exists          -> keep
#   no database, cached seed exists  -> seed
#   no database, no cached seed      -> fresh
#
# MEDIA (--uploads-mode)
#   copy    Duplicate the cached production media into this build (default).
#           Isolated and writable, so media the build generates stays with it.
#   mount   Bind the shared cached media in instead. One copy on disk for all
#           builds, but writes are visible to every mount-mode build.
#   skip    Leave uploads empty; imported posts will show broken images.
#
# OPTIONS
#   --prs <list>          PR ids (also accepted positionally)
#   --build <key>         Existing build key, e.g. 1887-1888
#   --db <mode>           Database mode, see above
#   --uploads-mode <mode> Media mode, see above
#   --port <n>            Move this build to a specific WordPress port
#   --rebuild             Rebuild the shared QA web image first
#   --no-auto-build       Fail instead of creating the branch when it is missing
#   -h, --help            Show this help
# =============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=scripts/qa-lib.sh
source "$SCRIPT_DIR/qa-lib.sh"

PRS_INPUT=""
BUILD_KEY=""
DB_MODE=""
UPLOADS_MODE=""
WP_PORT_INPUT=""
REBUILD=0
AUTO_BUILD=1

usage() { sed -n '2,/^# ====/p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//;$d'; }

parse_args() {
	local -a positional=()
	while [[ $# -gt 0 ]]; do
		case "$1" in
		--prs) PRS_INPUT="$PRS_INPUT $2" && shift 2 ;;
		--prs=*) PRS_INPUT="$PRS_INPUT ${1#*=}" && shift ;;
		--build) BUILD_KEY="$2" && shift 2 ;;
		--build=*) BUILD_KEY="${1#*=}" && shift ;;
		--db) DB_MODE="$2" && shift 2 ;;
		--db=*) DB_MODE="${1#*=}" && shift ;;
		--uploads-mode) UPLOADS_MODE="$2" && shift 2 ;;
		--uploads-mode=*) UPLOADS_MODE="${1#*=}" && shift ;;
		--port) WP_PORT_INPUT="$2" && shift 2 ;;
		--port=*) WP_PORT_INPUT="${1#*=}" && shift ;;
		--rebuild) REBUILD=1 && shift ;;
		--no-auto-build) AUTO_BUILD=0 && shift ;;
		-h | --help)
			usage
			exit 0
			;;
		-*) qa_die "unknown option: $1" ;;
		*)
			positional+=("$1")
			shift
			;;
		esac
	done
	[[ ${#positional[@]} -eq 0 ]] || PRS_INPUT="$PRS_INPUT ${positional[*]}"
}

resolve_build() {
	local prs

	if [[ -n "$BUILD_KEY" ]]; then
		qa_state_exists "$BUILD_KEY" || qa_die "unknown build key '$BUILD_KEY'"
		return 0
	fi

	[[ -n "${PRS_INPUT// /}" ]] || {
		usage
		qa_die "no build selected (pass PR ids or --build)"
	}

	prs="$(qa_normalize_prs "$PRS_INPUT")"
	BUILD_KEY="$(qa_key_from_prs "$prs")"

	if ! qa_state_exists "$BUILD_KEY"; then
		[[ "$AUTO_BUILD" -eq 1 ]] ||
			qa_die "build '$BUILD_KEY' does not exist yet (run: make qa-build PRS=$prs)"
		qa_info "No build for ${prs//,/, } yet — creating it first"
		echo
		bash "$SCRIPT_DIR/qa-build.sh" --prs "$prs"
		echo
	fi
}

# Auto-seeding is deliberately conservative: it only fires for a build that has
# no database at all, so re-running qa-up never silently discards test state.
resolve_db_mode() {
	local db_volume="${QA_PROJECT}_db_data_v2"

	if [[ -n "$DB_MODE" ]]; then
		case "$DB_MODE" in
		seed | keep | fresh | clone:*) return 0 ;;
		*) qa_die "invalid --db mode '$DB_MODE' (expected seed, keep, fresh or clone:<key>)" ;;
		esac
	fi

	if qa_volume_exists "$db_volume"; then
		DB_MODE="keep"
		qa_dim "  database   : keep (this build already has one; pass --db seed to reload)"
	elif [[ -f "$(qa_seed_file)" ]]; then
		DB_MODE="seed"
		qa_dim "  database   : seed (first run, cached production dump found)"
	else
		DB_MODE="fresh"
		qa_dim "  database   : fresh (first run, no cached dump — register one with FILE=...)"
	fi
}

# Must run before `compose up`: 'mount' changes the web container's volume set,
# and qa_compose only adds the uploads overlay when QA_UPLOADS_MODE says so.
resolve_uploads_mode() {
	if [[ -z "$UPLOADS_MODE" ]]; then
		UPLOADS_MODE="${QA_UPLOADS_MODE:-copy}"
	fi

	case "$UPLOADS_MODE" in
	copy | mount | skip) ;;
	*) qa_die "invalid --uploads-mode '$UPLOADS_MODE' (expected copy, mount or skip)" ;;
	esac

	export QA_UPLOADS_MODE="$UPLOADS_MODE"

	if [[ "$UPLOADS_MODE" != "skip" ]] && ! qa_has_uploads; then
		qa_dim "  media      : none cached (register with UPLOADS=/path/to/uploads.zip)"
	else
		qa_dim "  media      : ${UPLOADS_MODE}"
	fi
}

ensure_image() {
	if [[ "$REBUILD" -eq 1 ]] || ! docker image inspect "$QA_IMAGE" >/dev/null 2>&1; then
		qa_info "Building the shared QA web image (${QA_IMAGE})..."
		qa_compose build web
		qa_ok "image ready"
	fi
}

main() {
	parse_args "$@"
	cd "$(qa_repo_root)"
	qa_require_docker

	resolve_build
	qa_load_state "$BUILD_KEY"

	[[ -d "$QA_SRC" ]] ||
		qa_die "worktree missing at $QA_SRC — rebuild with: make qa-build PRS=$QA_PRS FORCE=1"

	# Must settle before `compose up`, since it decides what gets published.
	PREVIOUS_WP_PORT="$QA_WP_PORT"
	if [[ -n "$WP_PORT_INPUT" && "$WP_PORT_INPUT" != "$QA_WP_PORT" ]]; then
		QA_WP_PORT="$(qa_pick_port "$QA_WP_PORT_MIN" "$QA_WP_PORT_MAX" \
			"$QA_KEY" "$WP_PORT_INPUT" "WordPress port")"
		qa_set_state "$QA_KEY" QA_WP_PORT "$QA_WP_PORT"
		qa_warn "WordPress port reassigned ${PREVIOUS_WP_PORT} -> ${QA_WP_PORT}"
	fi

	qa_info "Starting QA build ${QA_GREEN}${QA_BRANCH}${QA_NC}"
	qa_dim "  project    : ${QA_PROJECT}"
	qa_dim "  ports      : wp ${QA_WP_PORT} · db ${QA_DB_PORT} · pma ${QA_PMA_PORT} · xdebug ${QA_XDEBUG_PORT}"
	[[ -n "${QA_SKIPPED:-}" ]] && qa_warn "this build skipped conflicting PR(s): ${QA_SKIPPED}"

	resolve_db_mode
	resolve_uploads_mode
	echo

	ensure_image

	# Builds created before this was wired in, or whose worktree was rebuilt
	# by hand, still need a runtime-correct autoloader.
	if [[ "${QA_VENDOR_PROVISIONED:-}" != "1" ]]; then
		qa_provision_vendor "$QA_SRC"
		qa_set_state "$QA_KEY" QA_VENDOR_PROVISIONED "1"
	fi

	# A vanilla install has to start from empty core *and* database: wp-config.php
	# survives in the core volume and would make the entrypoint skip installation.
	if [[ "$DB_MODE" == "fresh" ]] && qa_volume_exists "${QA_PROJECT}_db_data_v2"; then
		qa_warn "--db fresh: removing this build's database and WordPress core volumes"
		qa_compose down --volumes --remove-orphans >/dev/null 2>&1 || true
	fi

	qa_info "Bringing the stack up..."
	qa_compose up -d

	qa_wait_for_db
	qa_wait_for_wp

	case "$DB_MODE" in
	seed)
		echo
		bash "$SCRIPT_DIR/qa-seed.sh" --build "$QA_KEY" --uploads-mode "$UPLOADS_MODE"
		;;
	clone:*)
		echo
		bash "$SCRIPT_DIR/qa-seed.sh" --build "$QA_KEY" \
			--from-build "${DB_MODE#clone:}" --uploads-mode "$UPLOADS_MODE"
		;;
	keep | fresh)
		# A reassigned port leaves the previous one baked into siteurl/home and
		# into post content. Seed and clone rewrite URLs themselves; keep/fresh
		# have to be fixed up here.
		if [[ "$QA_WP_PORT" != "$PREVIOUS_WP_PORT" ]]; then
			qa_info "Rewriting stored site URLs for the new port..."
			qa_wp option update home "http://localhost:${QA_WP_PORT}" >/dev/null 2>&1 || true
			qa_wp option update siteurl "http://localhost:${QA_WP_PORT}" >/dev/null 2>&1 || true
			qa_wp search-replace "http://localhost:${PREVIOUS_WP_PORT}" \
				"http://localhost:${QA_WP_PORT}" \
				--all-tables --skip-columns=guid --report-changed-only >/dev/null 2>&1 || true
		fi

		# The entrypoint normally activates the plugin already; this is the
		# safety net for when it could not. Never swallow a failure here — a
		# silent one looks like a healthy stack with the plugin quietly missing.
		if qa_wp_full plugin is-active ai-post-scheduler >/dev/null 2>&1; then
			qa_ok "plugin active"
		elif qa_wp_full plugin activate ai-post-scheduler 2>&1 | sed 's/^/    /'; then
			qa_ok "plugin activated"
		else
			qa_warn "plugin activation failed — see the output above"
		fi

		# Media registered (or switched to copy mode) after this build was first
		# seeded still belongs here — but only fill an empty uploads directory,
		# never overwrite media the build already has.
		if [[ "$DB_MODE" == "keep" && "$UPLOADS_MODE" == "copy" ]] && qa_has_uploads &&
			[[ -z "$(qa_compose exec -T web sh -c 'ls -A /var/www/html/wp-content/uploads 2>/dev/null | head -1' 2>/dev/null | tr -d '\r')" ]]; then
			echo
			bash "$SCRIPT_DIR/qa-seed.sh" --build "$QA_KEY" \
				--uploads-mode "$UPLOADS_MODE" --uploads-only
		fi
		;;
	esac

	qa_set_state "$QA_KEY" QA_DB_MODE "$DB_MODE"
	qa_set_state "$QA_KEY" QA_UPLOADS_MODE "$UPLOADS_MODE"
	qa_set_state "$QA_KEY" QA_LAST_UP "$(date -u +%Y-%m-%dT%H:%M:%SZ)"

	echo
	qa_ok "QA build ${QA_BRANCH} is up"
	printf '  WordPress   %shttp://localhost:%s%s\n' "$QA_GREEN" "$QA_WP_PORT" "$QA_NC"
	printf '  Admin       %shttp://localhost:%s/wp-admin%s\n' "$QA_GREEN" "$QA_WP_PORT" "$QA_NC"
	printf '  phpMyAdmin  http://localhost:%s\n' "$QA_PMA_PORT"
	printf '  MySQL       localhost:%s (wordpress/wordpress)\n' "$QA_DB_PORT"
	echo
	qa_dim "  logs: make qa-logs PRS=$QA_PRS   ·   stop: make qa-down PRS=$QA_PRS"
}

main "$@"
