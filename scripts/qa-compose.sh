#!/usr/bin/env bash
# =============================================================================
# qa-compose.sh — run an arbitrary docker compose command against one QA build.
#
#   ./scripts/qa-compose.sh --prs 1887,1888 -- logs -f web
#   ./scripts/qa-compose.sh --prs 1887,1888 -- exec web bash
#
# Backs the qa-logs / qa-shell / qa-db-shell make targets, and is the escape
# hatch for anything the dedicated scripts do not wrap.
# =============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=scripts/qa-lib.sh
source "$SCRIPT_DIR/qa-lib.sh"

PRS_INPUT=""
BUILD_KEY=""

while [[ $# -gt 0 ]]; do
	case "$1" in
	--prs) PRS_INPUT="$PRS_INPUT $2" && shift 2 ;;
	--prs=*) PRS_INPUT="$PRS_INPUT ${1#*=}" && shift ;;
	--build) BUILD_KEY="$2" && shift 2 ;;
	--build=*) BUILD_KEY="${1#*=}" && shift ;;
	--)
		shift
		break
		;;
	*) qa_die "unknown option: $1" ;;
	esac
done

[[ $# -gt 0 ]] || qa_die "no docker compose command given (use: -- logs -f)"

cd "$(qa_repo_root)"

if [[ -z "$BUILD_KEY" ]]; then
	[[ -n "${PRS_INPUT// /}" ]] || qa_die "no build selected (--prs or --build)"
	BUILD_KEY="$(qa_key_from_prs "$(qa_normalize_prs "$PRS_INPUT")")"
fi

qa_load_state "$BUILD_KEY"
qa_compose "$@"
