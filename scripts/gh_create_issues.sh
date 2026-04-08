#!/usr/bin/env bash
# =============================================================================
# gh_create_issues.sh
#
# Create GitHub Issues in bulk from a CSV file using the GitHub CLI (gh).
#
# USAGE:
#   ./scripts/gh_create_issues.sh --issuesfile <file.csv> [OPTIONS]
#
# OPTIONS:
#   --issuesfile  <path>         Path to the CSV issues file (required)
#   --repo        <owner/repo>   Target repository (default: current repo)
#   --project     <number>       GitHub Project number to add issues to
#   --milestone   <title>        Milestone to assign to all issues (default)
#   --dry-run                    Print commands without executing them
#   --help                       Show this help message
#
# REQUIREMENTS:
#   - GitHub CLI (gh) installed and authenticated: https://cli.github.com
#   - For --project support:
#       gh extension install cli/gh-projects
#
# =============================================================================
# CSV FORMAT
# =============================================================================
# Delimiter   : comma (,)
# Quoting     : fields containing commas or newlines must be wrapped in "double
#               quotes". Escape a literal double-quote by doubling it: "".
# Header row  : the first line is always treated as a header and is skipped.
# Comment rows: lines whose first non-whitespace character is # are skipped.
# Blank rows  : silently skipped.
#
# COLUMNS (in order):
#   1. title      (required) Issue title
#   2. body       (required) Issue description. Markdown supported.
#   3. labels     (optional) Comma-separated label names wrapped in quotes.
#                            Labels that do not exist will be created by gh.
#                            Example:  "bug,enhancement,Phase 1"
#   4. assignees  (optional) Comma-separated GitHub usernames.
#                            Example:  "rpnunez,octocat"
#   5. milestone  (optional) Milestone title for this row. Overrides the
#                            --milestone flag when non-empty.
#   6. project    (optional) Project number for this row. Overrides the
#                            --project flag when non-empty.
#
# EXAMPLE CSV
# ---------------------------------------------------------------------------
# title,body,labels,assignees,milestone,project
# "Fix dedup in save_research_batch","topic_exists() is not called; repeated cron runs produce duplicate rows.","bug,Phase 1","rpnunez",,
# "Add status column to aips_trending_topics","Add ENUM(pending|approved|dismissed|scheduled) DEFAULT pending.","enhancement,Phase 1,database",,"Sprint 1",3
# "Research Inbox tab","New Inbox tab on Research page with Approve/Dismiss/Generate actions.","enhancement,Phase 2",,"Sprint 2",
# "Introduce AIPS_Research_Source interface","Define interface with get_label() and fetch() for pluggable research sources.","enhancement,Phase 3,architecture",,"Sprint 3",3
# ---------------------------------------------------------------------------
#
# NOTE ON LABELS:
#   If a label referenced in the CSV does not exist in the repository, gh will
#   error. Pre-create labels with:
#     gh label create "Phase 1" --color 0075ca --repo owner/repo
#
# NOTE ON PROJECTS:
#   The project number appears in the URL of the project page:
#     https://github.com/orgs/ORG/projects/NUMBER
#   or https://github.com/users/USER/projects/NUMBER
#
# =============================================================================

set -euo pipefail

# ---------------------------------------------------------------------------
# Colour helpers
# ---------------------------------------------------------------------------
RED="\033[0;31m"
GREEN="\033[0;32m"
YELLOW="\033[1;33m"
CYAN="\033[0;36m"
BOLD="\033[1m"
NC="\033[0m"

info()    { echo -e "${CYAN}[INFO]${NC}  $*"; }
success() { echo -e "${GREEN}[OK]${NC}    $*"; }
warn()    { echo -e "${YELLOW}[WARN]${NC}  $*"; }
error()   { echo -e "${RED}[ERROR]${NC} $*" >&2; }
die()     { error "$*"; exit 1; }

# ---------------------------------------------------------------------------
# Defaults
# ---------------------------------------------------------------------------
ISSUES_FILE=""
REPO=""
GLOBAL_PROJECT=""
GLOBAL_MILESTONE=""
DRY_RUN=false
CREATED=0
SKIPPED=0
FAILED=0

# ---------------------------------------------------------------------------
# Usage
# ---------------------------------------------------------------------------
usage() {
    grep '^#' "$0" | grep -v '^#!/' | sed 's/^# \{0,1\}//' | head -80
    exit 0
}

# ---------------------------------------------------------------------------
# Argument parsing
# ---------------------------------------------------------------------------
while [[ $# -gt 0 ]]; do
    case "$1" in
        --issuesfile)
            ISSUES_FILE="$2"
            shift 2
            ;;
        --repo)
            REPO="$2"
            shift 2
            ;;
        --project)
            GLOBAL_PROJECT="$2"
            shift 2
            ;;
        --milestone)
            GLOBAL_MILESTONE="$2"
            shift 2
            ;;
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --help|-h)
            usage
            ;;
        *)
            die "Unknown argument: $1. Run with --help for usage."
            ;;
    esac
done

# ---------------------------------------------------------------------------
# Validation
# ---------------------------------------------------------------------------
[[ -z "$ISSUES_FILE" ]] && die "--issuesfile is required."
[[ ! -f "$ISSUES_FILE" ]] && die "Issues file not found: $ISSUES_FILE"

command -v gh >/dev/null 2>&1 || die "GitHub CLI (gh) is not installed. See https://cli.github.com"

gh auth status >/dev/null 2>&1 || die "GitHub CLI is not authenticated. Run: gh auth login"

# ---------------------------------------------------------------------------
# Build --repo flags array
# ---------------------------------------------------------------------------
REPO_FLAGS=()
if [[ -n "$REPO" ]]; then
    REPO_FLAGS=("--repo" "$REPO")
fi

# ---------------------------------------------------------------------------
# Resolve repo owner (used for project assignment)
# ---------------------------------------------------------------------------
get_owner() {
    if [[ -n "$REPO" ]]; then
        echo "${REPO%%/*}"
    else
        gh repo view --json owner --jq '.owner.login' 2>/dev/null || echo ""
    fi
}

# ---------------------------------------------------------------------------
# Ensure a milestone exists in the repository; create it if missing.
# gh issue create accepts milestone by title so we just ensure it exists.
# ---------------------------------------------------------------------------
ensure_milestone() {
    local title="$1"
    [[ -z "$title" ]] && return 0

    local api_path
    if [[ -n "$REPO" ]]; then
        api_path="repos/${REPO}/milestones"
    else
        api_path="$(gh repo view --json nameWithOwner --jq '"repos/" + .nameWithOwner + "/milestones"' 2>/dev/null)"
    fi

    local existing
    existing=$(gh api "$api_path" --paginate --jq ".[].title" 2>/dev/null \
        | grep -Fx "$title" || true)

    if [[ -z "$existing" ]]; then
        if [[ "$DRY_RUN" == true ]]; then
            warn "[dry-run] Would create milestone: $title"
        else
            info "Creating milestone: $title"
            gh api --method POST "$api_path" -f title="$title" >/dev/null 2>&1 \
                || warn "Could not create milestone '$title' — it may already exist."
        fi
    fi
}

# ---------------------------------------------------------------------------
# Add an issue to a GitHub Project by issue URL.
# Requires: gh extension install cli/gh-projects
# ---------------------------------------------------------------------------
add_to_project() {
    local issue_url="$1"
    local project_num="$2"
    [[ -z "$project_num" ]] && return 0

    local owner
    owner=$(get_owner)
    if [[ -z "$owner" ]]; then
        warn "Cannot determine repository owner — skipping project assignment."
        return 0
    fi

    if [[ "$DRY_RUN" == true ]]; then
        warn "[dry-run] Would add $issue_url to project #$project_num (owner: $owner)"
        return 0
    fi

    gh project item-add "$project_num" \
        --owner "$owner" \
        --url "$issue_url" >/dev/null 2>&1 \
        || warn "Could not add issue to project #$project_num. Ensure gh-projects extension is installed: gh extension install cli/gh-projects"

    success "  Added to project #$project_num"
}

# ---------------------------------------------------------------------------
# Parse one CSV line into the global CSV_FIELDS array.
# Handles double-quoted fields and escaped double-quotes ("").
# ---------------------------------------------------------------------------
CSV_FIELDS=()
parse_csv_line() {
    local line="$1"
    CSV_FIELDS=()
    local field=""
    local in_quotes=false
    local i char next

    for (( i=0; i<${#line}; i++ )); do
        char="${line:$i:1}"
        if [[ "$in_quotes" == true ]]; then
            if [[ "$char" == '"' ]]; then
                next="${line:$((i+1)):1}"
                if [[ "$next" == '"' ]]; then
                    # Escaped double-quote inside a quoted field
                    field+='"'
                    (( i++ ))
                else
                    in_quotes=false
                fi
            else
                field+="$char"
            fi
        else
            if [[ "$char" == '"' ]]; then
                in_quotes=true
            elif [[ "$char" == ',' ]]; then
                CSV_FIELDS+=("$field")
                field=""
            else
                field+="$char"
            fi
        fi
    done
    # Push the final field (no trailing comma required)
    CSV_FIELDS+=("$field")
}

# ---------------------------------------------------------------------------
# Main loop — read CSV and create issues
# ---------------------------------------------------------------------------
info "Reading issues from: $ISSUES_FILE"
[[ "$DRY_RUN" == true ]] && warn "Dry-run mode — no issues will be created or modified."
echo

line_number=0

while IFS= read -r raw_line || [[ -n "$raw_line" ]]; do
    (( ++line_number ))

    # Skip header row
    [[ "$line_number" -eq 1 ]] && continue

    # Skip blank lines
    [[ -z "${raw_line// /}" ]] && continue

    # Skip comment rows (first non-whitespace char is #)
    [[ "$raw_line" =~ ^[[:space:]]*# ]] && continue

    parse_csv_line "$raw_line"

    row_title="${CSV_FIELDS[0]:-}"
    row_body="${CSV_FIELDS[1]:-}"
    row_labels="${CSV_FIELDS[2]:-}"
    row_assignees="${CSV_FIELDS[3]:-}"
    row_milestone="${CSV_FIELDS[4]:-$GLOBAL_MILESTONE}"
    row_project="${CSV_FIELDS[5]:-$GLOBAL_PROJECT}"

    # Allow \n escapes in CSV body text so multiline Markdown can be stored
    # while keeping one physical CSV line per issue row.
    row_body="${row_body//\\n/$'\n'}"

    if [[ -z "$row_title" ]]; then
        warn "Line $line_number: empty title — skipping."
        (( ++SKIPPED ))
        continue
    fi

    info "Line $line_number: ${BOLD}$row_title${NC}"

    # Build the gh issue create argument list
    GH_ARGS=()
    GH_ARGS+=("${REPO_FLAGS[@]}")
    GH_ARGS+=("--title" "$row_title")
    GH_ARGS+=("--body"  "$row_body")

    # Labels — each becomes a separate --label flag
    if [[ -n "$row_labels" ]]; then
        IFS=',' read -ra label_arr <<< "$row_labels"
        for lbl in "${label_arr[@]}"; do
            lbl="$(echo "$lbl" | xargs)"
            [[ -n "$lbl" ]] && GH_ARGS+=("--label" "$lbl")
        done
    fi

    # Assignees — each becomes a separate --assignee flag
    if [[ -n "$row_assignees" ]]; then
        IFS=',' read -ra assignee_arr <<< "$row_assignees"
        for usr in "${assignee_arr[@]}"; do
            usr="$(echo "$usr" | xargs)"
            [[ -n "$usr" ]] && GH_ARGS+=("--assignee" "$usr")
        done
    fi

    # Milestone
    if [[ -n "$row_milestone" ]]; then
        ensure_milestone "$row_milestone"
        GH_ARGS+=("--milestone" "$row_milestone")
    fi

    if [[ "$DRY_RUN" == true ]]; then
        echo -e "  ${YELLOW}[dry-run]${NC} gh issue create ${GH_ARGS[*]}"
        [[ -n "$row_project" ]] && \
            echo -e "  ${YELLOW}[dry-run]${NC} Would add to project #$row_project"
        (( ++CREATED ))
        continue
    fi

    issue_url=$(gh issue create "${GH_ARGS[@]}" 2>&1) || {
        error "Failed to create issue on line $line_number — $issue_url"
        (( ++FAILED ))
        continue
    }

    success "Created: $issue_url"

    # Add to project if specified
    if [[ -n "$row_project" ]]; then
        add_to_project "$issue_url" "$row_project"
    fi

    (( ++CREATED ))

done < "$ISSUES_FILE"

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------
echo
echo -e "${BOLD}------- Summary -------${NC}"
echo -e "  ${GREEN}Created : $CREATED${NC}"
[[ "$SKIPPED" -gt 0 ]] && echo -e "  ${YELLOW}Skipped : $SKIPPED${NC}"
[[ "$FAILED"  -gt 0 ]] && echo -e "  ${RED}Failed  : $FAILED${NC}"
echo

[[ "$FAILED" -gt 0 ]] && exit 1
exit 0
