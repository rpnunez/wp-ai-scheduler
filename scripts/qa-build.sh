#!/usr/bin/env bash
# =============================================================================
# qa-build.sh — bundle N open pull requests into one testable QA build branch.
#
#   ./scripts/qa-build.sh 1887 1888 1885
#   ./scripts/qa-build.sh --prs 1887,1888 --pr
#
# Creates a fresh branch off origin/main named qa-build-<ids>, merges each pull
# request into it, and reports which ones landed and which conflicted. A pull
# request that conflicts is aborted cleanly and skipped — the rest of the build
# still completes, so one bad PR never costs you the whole bundle.
#
# The branch is built inside a dedicated git worktree under .qa-builds/, so
# your own checkout, staged changes and current branch are never touched.
#
# OPTIONS
#   --prs <list>    Comma or space separated PR ids (also accepted positionally)
#   --base <ref>    Base branch to build from (default: main)
#   --port <n>      Pin the WordPress port (default: a random free one)
#   --pr            Push the branch and open a draft pull request for the build
#   --push          Push the branch without opening a pull request
#   --force         Rebuild a build that already exists
#   --dry-run       Show what would be merged, change nothing
#   -h, --help      Show this help
# =============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=scripts/qa-lib.sh
source "$SCRIPT_DIR/qa-lib.sh"

PRS_INPUT=""
BASE_BRANCH="$QA_BASE_BRANCH"
WP_PORT_INPUT=""
CREATE_PR=0
DO_PUSH=0
FORCE=0
DRY_RUN=0

usage() {
	sed -n '2,/^# ====/p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//;$d'
}

parse_args() {
	local -a positional=()

	while [[ $# -gt 0 ]]; do
		case "$1" in
		--prs)
			PRS_INPUT="$PRS_INPUT $2"
			shift 2
			;;
		--prs=*)
			PRS_INPUT="$PRS_INPUT ${1#*=}"
			shift
			;;
		--base)
			BASE_BRANCH="$2"
			shift 2
			;;
		--base=*)
			BASE_BRANCH="${1#*=}"
			shift
			;;
		--port)
			WP_PORT_INPUT="$2"
			shift 2
			;;
		--port=*)
			WP_PORT_INPUT="${1#*=}"
			shift
			;;
		--pr)
			CREATE_PR=1
			DO_PUSH=1
			shift
			;;
		--push)
			DO_PUSH=1
			shift
			;;
		--force)
			FORCE=1
			shift
			;;
		--dry-run)
			DRY_RUN=1
			shift
			;;
		-h | --help)
			usage
			exit 0
			;;
		-*)
			qa_die "unknown option: $1"
			;;
		*)
			positional+=("$1")
			shift
			;;
		esac
	done

	if [[ ${#positional[@]} -gt 0 ]]; then
		PRS_INPUT="$PRS_INPUT ${positional[*]}"
	fi

	[[ -n "${PRS_INPUT// /}" ]] || {
		usage
		qa_die "no pull request ids given"
	}
}

# Look up a PR title for nicer merge commits and PR bodies. Optional: without
# an authenticated gh we simply build the bundle without titles.
pr_title() {
	local number="$1"
	if [[ "$HAS_GH" -eq 1 ]]; then
		gh pr view "$number" --json title --jq .title 2>/dev/null || true
	fi
}

pr_state() {
	local number="$1"
	if [[ "$HAS_GH" -eq 1 ]]; then
		gh pr view "$number" --json state --jq .state 2>/dev/null || true
	fi
}

main() {
	parse_args "$@"

	local repo_root
	repo_root="$(qa_repo_root)"
	cd "$repo_root"

	HAS_GH=0
	qa_has_gh && HAS_GH=1

	local prs key branch project src_dir
	prs="$(qa_normalize_prs "$PRS_INPUT")"
	key="$(qa_key_from_prs "$prs")"
	branch="$(qa_branch_from_prs "$prs")"
	project="$(qa_project_from_key "$key")"
	src_dir="$(qa_src_dir "$key")"

	qa_info "QA build ${QA_GREEN}${branch}${QA_NC}"
	qa_dim "  pull requests : ${prs//,/, }"
	qa_dim "  base branch   : ${QA_REMOTE}/${BASE_BRANCH}"
	qa_dim "  worktree      : ${QA_DIR_NAME}/${key}/src"
	[[ "$HAS_GH" -eq 1 ]] || qa_dim "  gh not authenticated — PR titles omitted, --pr unavailable"
	echo

	if [[ "$DRY_RUN" -eq 1 ]]; then
		qa_info "Dry run — would merge these pull requests in order:"
		local n
		for n in ${prs//,/ }; do
			printf '  #%s %s\n' "$n" "$(pr_title "$n")"
		done
		exit 0
	fi

	if [[ -d "$src_dir" && "$FORCE" -eq 0 ]]; then
		qa_die "build '$key' already exists. Re-run with --force to rebuild it from a fresh $BASE_BRANCH."
	fi

	# ---- refresh base and pull request refs -------------------------------
	qa_info "Fetching ${QA_REMOTE}/${BASE_BRANCH}..."
	qa_git_retry fetch "$QA_REMOTE" "$BASE_BRANCH"

	local n state
	local -a refspecs=()
	for n in ${prs//,/ }; do
		state="$(pr_state "$n")"
		if [[ -n "$state" && "$state" != "OPEN" ]]; then
			qa_warn "PR #$n is $state, not open — including it anyway"
		fi
		refspecs+=("+refs/pull/${n}/head:refs/qa/pr/${n}")
	done

	qa_info "Fetching ${#refspecs[@]} pull request head(s)..."
	qa_git_retry fetch "$QA_REMOTE" "${refspecs[@]}"

	# ---- create the build worktree ----------------------------------------
	if [[ -d "$src_dir" ]]; then
		qa_warn "removing existing worktree for rebuild"
		git worktree remove --force "$src_dir" 2>/dev/null ||
			qa_die "could not remove worktree $src_dir — stop its stack first (make qa-down PRS=$prs)"
	fi
	git worktree prune

	mkdir -p "$(qa_build_dir "$key")"
	qa_info "Creating branch ${branch} from ${QA_REMOTE}/${BASE_BRANCH}..."
	git worktree add -B "$branch" "$src_dir" "${QA_REMOTE}/${BASE_BRANCH}" >/dev/null ||
		qa_die "failed to create worktree at $src_dir"

	# ---- merge each pull request ------------------------------------------
	local -a merged=() skipped=() already=()
	local title msg

	for n in ${prs//,/ }; do
		title="$(pr_title "$n")"
		if [[ -n "$title" ]]; then
			msg="qa-build: merge PR #${n} — ${title}"
		else
			msg="qa-build: merge PR #${n}"
		fi

		if git -C "$src_dir" merge-base --is-ancestor "refs/qa/pr/${n}" HEAD 2>/dev/null; then
			qa_ok "#${n} already contained in ${BASE_BRANCH}"
			already+=("$n")
			continue
		fi

		printf '%s==>%s Merging #%s %s\n' "$QA_BLUE" "$QA_NC" "$n" "${title:-}"
		if git -C "$src_dir" merge --no-ff --no-edit -m "$msg" "refs/qa/pr/${n}" >/dev/null 2>&1; then
			qa_ok "#${n} merged"
			merged+=("$n")
		else
			git -C "$src_dir" merge --abort 2>/dev/null || true
			qa_err "#${n} conflicts with the build — skipped"
			skipped+=("$n")
		fi
	done

	# ---- persist build state ----------------------------------------------
	local merged_csv skipped_csv already_csv
	merged_csv="$(
		IFS=,
		echo "${merged[*]:-}"
	)"
	skipped_csv="$(
		IFS=,
		echo "${skipped[*]:-}"
	)"
	already_csv="$(
		IFS=,
		echo "${already[*]:-}"
	)"

	# Ports are assigned once and kept across rebuilds, so a build's URL stays
	# put — unless --port explicitly asks for a different one.
	local wp_port db_port pma_port xdebug_port
	if qa_state_exists "$key" && [[ -z "$WP_PORT_INPUT" ]]; then
		local existing
		existing="$(qa_state_file "$key")"
		wp_port="$(sed -n 's/^QA_WP_PORT=//p' "$existing" | head -n1)"
		db_port="$(sed -n 's/^QA_DB_PORT=//p' "$existing" | head -n1)"
		pma_port="$(sed -n 's/^QA_PMA_PORT=//p' "$existing" | head -n1)"
		xdebug_port="$(sed -n 's/^QA_XDEBUG_PORT=//p' "$existing" | head -n1)"
	fi

	if [[ -z "${wp_port:-}" ]]; then
		read -r wp_port db_port pma_port xdebug_port < <(qa_allocate_ports "$key" "$WP_PORT_INPUT")
	fi

	qa_save_state "$key" \
		"QA_KEY=$key" \
		"QA_PRS=$prs" \
		"QA_BRANCH=$branch" \
		"QA_BASE=$BASE_BRANCH" \
		"QA_PROJECT=$project" \
		"QA_SRC=$src_dir" \
		"QA_WP_PORT=$wp_port" \
		"QA_DB_PORT=$db_port" \
		"QA_PMA_PORT=$pma_port" \
		"QA_XDEBUG_PORT=$xdebug_port" \
		"QA_MERGED=$merged_csv" \
		"QA_SKIPPED=$skipped_csv" \
		"QA_ALREADY=$already_csv" \
		"QA_CREATED=$(date -u +%Y-%m-%dT%H:%M:%SZ)"

	# ---- report ------------------------------------------------------------
	echo
	qa_info "Build summary"
	printf '  branch   %s\n' "$branch"
	printf '  merged   %s\n' "${merged_csv:-none}"
	[[ -n "$already_csv" ]] && printf '  in base  %s\n' "$already_csv"
	if [[ -n "$skipped_csv" ]]; then
		printf '  %sskipped  %s%s (merge conflicts)\n' "$QA_RED" "$skipped_csv" "$QA_NC"
	fi
	printf '  commits  %s ahead of %s\n' \
		"$(git -C "$src_dir" rev-list --count "${QA_REMOTE}/${BASE_BRANCH}..HEAD")" "$BASE_BRANCH"
	echo

	if [[ -z "$merged_csv" && -z "$already_csv" ]]; then
		qa_warn "nothing merged — every pull request conflicted"
	fi

	# ---- push / draft pull request ----------------------------------------
	if [[ "$DO_PUSH" -eq 1 ]]; then
		qa_info "Pushing ${branch}..."
		(cd "$src_dir" && qa_git_retry push -u "$QA_REMOTE" "$branch" --force-with-lease)
		qa_ok "pushed"
	fi

	if [[ "$CREATE_PR" -eq 1 ]]; then
		[[ "$HAS_GH" -eq 1 ]] || qa_die "--pr needs an authenticated GitHub CLI (run: gh auth login)"
		create_draft_pr "$key" "$prs" "$branch" "$merged_csv" "$skipped_csv" "$already_csv"
	fi

	# After any push, so the pushed branch matches the merge result exactly
	# rather than carrying regenerated autoloader files.
	qa_provision_vendor "$src_dir"
	qa_set_state "$key" QA_VENDOR_PROVISIONED "1"

	echo
	qa_ok "Build ready. Start it with: make qa-up PRS=$prs"
}

create_draft_pr() {
	local key="$1" prs="$2" branch="$3" merged_csv="$4" skipped_csv="$5" already_csv="$6"

	local existing
	existing="$(gh pr list --head "$branch" --state open --json number --jq '.[0].number' 2>/dev/null || true)"
	if [[ -n "$existing" ]]; then
		qa_ok "draft pull request already open: #$existing"
		qa_set_state "$key" QA_PR_NUMBER "$existing"
		return 0
	fi

	local body_file
	body_file="$(mktemp)"
	{
		printf 'QA build bundling %s open pull request(s) onto a fresh branch off `%s` for combined testing.\n\n' \
			"$(awk -F, '{print NF}' <<<"$prs")" "$BASE_BRANCH"
		printf '**Not for merge.** This branch exists so the bundled changes can be exercised together against a copy of production data.\n\n'
		printf '## Bundled pull requests\n\n'
		printf '| PR | Status | Title |\n|---|---|---|\n'

		local n status title
		for n in ${prs//,/ }; do
			status='merged'
			[[ ",$skipped_csv," == *",$n,"* ]] && status='conflict — skipped'
			[[ ",$already_csv," == *",$n,"* ]] && status="already in $BASE_BRANCH"
			title="$(pr_title "$n")"
			printf '| #%s | %s | %s |\n' "$n" "$status" "${title:-}"
		done

		printf '\n## How to run this build\n\n```bash\nmake qa-up PRS=%s\n```\n' "$prs"
	} >"$body_file"

	qa_info "Opening draft pull request..."
	local url
	if url="$(gh pr create --draft \
		--base "$BASE_BRANCH" \
		--head "$branch" \
		--title "QA build: PRs ${prs//,/, }" \
		--body-file "$body_file" \
		--label qa-build 2>/dev/null)"; then
		:
	else
		# The qa-build label may not exist yet in the repo; retry without it.
		url="$(gh pr create --draft \
			--base "$BASE_BRANCH" \
			--head "$branch" \
			--title "QA build: PRs ${prs//,/, }" \
			--body-file "$body_file")" ||
			qa_die "failed to create the pull request"
	fi

	rm -f "$body_file"
	qa_ok "draft pull request: $url"
	qa_set_state "$key" QA_PR_URL "$url"
}

main "$@"
