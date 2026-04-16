#!/bin/bash

# Configuration
DRY_RUN=true
[[ "$1" == "--confirm" ]] && DRY_RUN=false
DAYS_OLD=15
THRESHOLD=$(date -d "$DAYS_OLD days ago" +%s)

# 1. Get the Default Branch (main/master)
DEFAULT_BRANCH=$(git remote show origin | sed -n '/HEAD branch/s/.*: //p')

echo "--- Scanning local refs for merged branches > $DAYS_OLD days old ---"

# 2. Identify and Filter Merged Branches Locally
DELETE_LIST=()

# Using %committerdate:unix to get a pure integer for Bash comparison
while read -r branch_name committer_date; do
    if [[ -n "$committer_date" && "$committer_date" -lt "$THRESHOLD" ]]; then
        DELETE_LIST+=("$branch_name")
    fi
done < <(git for-each-ref --format='%(refname:short) %(committerdate:unix)' refs/remotes/origin/ --merged "origin/$DEFAULT_BRANCH" | \
        grep -v "origin/$DEFAULT_BRANCH" | \
        sed 's/origin\///')

# 3. Action
if [ ${#DELETE_LIST[@]} -eq 0 ]; then
    echo "No stale merged branches found."
    exit 0
fi

if [ "$DRY_RUN" = true ]; then
    for branch in "${DELETE_LIST[@]}"; do
        echo "[DRY RUN] Marked for deletion: $branch"
    done
    echo "------------------------------------------------"
    echo "Total branches to delete: ${#DELETE_LIST[@]}"
    echo "Run with --confirm to execute batch delete."
else
    echo "Batch deleting ${#DELETE_LIST[@]} branches..."
    # xargs bundles multiple branches into single 'git push' commands for speed
    echo "${DELETE_LIST[@]}" | xargs -n 50 git push origin --delete
fi