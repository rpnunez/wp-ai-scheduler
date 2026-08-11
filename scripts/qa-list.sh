#!/usr/bin/env bash
# =============================================================================
# qa-list.sh — show every QA build, its ports, and whether it is running.
# =============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=scripts/qa-lib.sh
source "$SCRIPT_DIR/qa-lib.sh"

cd "$(qa_repo_root)"

seed="$(qa_seed_file)"
if [[ -f "$seed" ]]; then
	qa_dim "cached production seed: ${QA_DIR_NAME}/_seed/prod.sql ($(du -h "$seed" | cut -f1))"
else
	qa_dim "cached production seed: none — register one with 'make qa-seed PRS=... FILE=dump.sql'"
fi
echo

printf '%-22s %-9s %-24s %-7s %s\n' "BUILD" "STATE" "URL" "DB" "PULL REQUESTS"

found=0
while read -r key; do
	[[ -n "$key" ]] || continue
	found=1
	(
		qa_load_state "$key"

		state="stopped"
		if docker inspect -f '{{.State.Status}}' "${QA_PROJECT}-web" 2>/dev/null | grep -q running; then
			state="running"
		fi

		flag=""
		[[ -n "${QA_SKIPPED:-}" ]] && flag=" (skipped ${QA_SKIPPED})"

		printf '%-22s %-9s %-24s %-7s %s%s\n' \
			"$key" "$state" "http://localhost:${QA_WP_PORT}" \
			"${QA_DB_MODE:-—}" "${QA_PRS//,/, }" "$flag"
	)
done < <(qa_list_keys)

[[ "$found" -eq 1 ]] || qa_dim "no QA builds yet — create one with 'make qa-build PRS=1887,1888'"
