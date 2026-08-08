#!/usr/bin/env bash
# =============================================================================
# qa-down.sh — stop a QA build's stack and optionally delete the build.
#
#   ./scripts/qa-down.sh 1887 1888            # stop containers, keep the data
#   ./scripts/qa-down.sh --prs 1887,1888 --purge
#
# Without --purge the build's database and WordPress core volumes survive, so
# bringing it back up with `make qa-up` returns you to the same test state on
# the same ports. --purge frees the ports and removes the worktree, leaving
# only the git branch.
#
# OPTIONS
#   --prs <list>   PR ids (also accepted positionally)
#   --build <key>  Build key, e.g. 1887-1888
#   --purge        Also delete volumes, worktree and build state
#   --all          Apply to every QA build
#   -h, --help     Show this help
# =============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=scripts/qa-lib.sh
source "$SCRIPT_DIR/qa-lib.sh"

PRS_INPUT=""
BUILD_KEY=""
PURGE=0
ALL=0

usage() { sed -n '2,/^# ====/p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//;$d'; }

parse_args() {
	local -a positional=()
	while [[ $# -gt 0 ]]; do
		case "$1" in
		--prs) PRS_INPUT="$PRS_INPUT $2" && shift 2 ;;
		--prs=*) PRS_INPUT="$PRS_INPUT ${1#*=}" && shift ;;
		--build) BUILD_KEY="$2" && shift 2 ;;
		--build=*) BUILD_KEY="${1#*=}" && shift ;;
		--purge) PURGE=1 && shift ;;
		--all) ALL=1 && shift ;;
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

take_down() {
	local key="$1"

	# Subshell so each build's state does not leak into the next iteration.
	(
		qa_load_state "$key"

		qa_info "Stopping ${QA_BRANCH} (${QA_PROJECT})"
		if [[ "$PURGE" -eq 1 ]]; then
			qa_compose down --volumes --remove-orphans || true
		else
			qa_compose down --remove-orphans || true
		fi

		if [[ "$PURGE" -eq 1 ]]; then
			if [[ -d "$QA_SRC" ]]; then
				git worktree remove --force "$QA_SRC" 2>/dev/null || rm -rf "$QA_SRC"
				git worktree prune
			fi
			rm -rf "$(qa_build_dir "$key")"
			qa_ok "purged — branch ${QA_BRANCH} still exists locally (delete with: git branch -D '${QA_BRANCH}')"
		else
			qa_ok "stopped — data kept, ports ${QA_WP_PORT}/${QA_DB_PORT} still reserved"
		fi
	)
}

main() {
	parse_args "$@"
	cd "$(qa_repo_root)"
	qa_require_docker

	if [[ "$ALL" -eq 1 ]]; then
		local key found=0
		while read -r key; do
			[[ -n "$key" ]] || continue
			found=1
			take_down "$key"
		done < <(qa_list_keys)
		[[ "$found" -eq 1 ]] || qa_warn "no QA builds found"
		exit 0
	fi

	if [[ -z "$BUILD_KEY" ]]; then
		[[ -n "${PRS_INPUT// /}" ]] || {
			usage
			qa_die "no build selected (pass PR ids, --build or --all)"
		}
		BUILD_KEY="$(qa_key_from_prs "$(qa_normalize_prs "$PRS_INPUT")")"
	fi

	take_down "$BUILD_KEY"
}

main "$@"
