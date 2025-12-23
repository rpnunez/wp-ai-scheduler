#!/bin/bash

# Script to check which PRs have merge conflicts
# This script tests if each PR can be cleanly merged into its target branch

# Array of PR numbers and branch names
declare -A prs
prs[6]="wizard-template-search-11506985276342195298"
prs[12]="copilot/improve-slow-code-efficiency"
prs[14]="copilot/ciadd-pr-ci-workflow"
prs[24]="bolt-logger-optimization-13857484038144494642"
prs[25]="sentinel-ssrf-fix-10986171741638357540"
prs[26]="palette-a11y-fix-9149112686241413741"
prs[28]="wizard-history-search-8655622669745702532"
prs[30]="hunter-fix-scheduler-id-collision-15743482046909698209"
prs[38]="palette-editable-topics-17633147156937485405"
prs[47]="atlas/extract-image-service-10996060423954448602"
prs[52]="copilot/implement-database-repository-layer"
prs[54]="all-operational-modes-4464187572361531219"
prs[55]="hunter-wizard-atlas-bolt-705793256190618529"
prs[57]="copilot/reduce-redundancy-in-repository"
prs[58]="copilot/modify-aips-db-manager"
prs[61]="bolt-bulk-insert-optimization-14417970550656218874"
prs[62]="sentinel-input-validation-14523201659580960297"
prs[63]="palette-clear-topics-button-12343389530500501724"
prs[65]="wizard-system-status-copy-2346282826133477814"

echo "=== Checking for Merge Conflicts ==="
echo ""

# Ensure we have latest main
git fetch origin main:refs/remotes/origin/main 2>/dev/null

mergeable=()
conflicts=()
errors=()

for pr_num in $(echo "${!prs[@]}" | tr ' ' '\n' | sort -n); do
    branch="${prs[$pr_num]}"
    
    echo "Checking PR #$pr_num ($branch)..."
    
    # Fetch the branch
    git fetch origin "$branch:refs/remotes/origin/$branch" 2>/dev/null
    
    # Create a temporary branch for merge test
    test_branch="merge-test-$pr_num"
    git branch -D "$test_branch" 2>/dev/null
    
    # Create test branch from main
    git checkout -b "$test_branch" refs/remotes/origin/main 2>/dev/null
    
    # Try to merge the PR branch
    if git merge --no-commit --no-ff "origin/$branch" 2>&1 | tee /tmp/merge_output_$pr_num.txt; then
        # Check if there are conflicts
        if git diff --name-only --diff-filter=U | grep -q .; then
            conflicted_files=$(git diff --name-only --diff-filter=U)
            conflicts+=("$pr_num:$branch:$conflicted_files")
            echo "  ❌ MERGE CONFLICT detected"
            echo "  Conflicted files:"
            echo "$conflicted_files" | sed 's/^/    - /'
        else
            mergeable+=("$pr_num:$branch")
            echo "  ✅ Clean merge (no conflicts)"
        fi
        # Abort the merge
        git merge --abort 2>/dev/null
    else
        # Merge command failed
        if grep -q "CONFLICT" /tmp/merge_output_$pr_num.txt; then
            conflicted_files=$(git diff --name-only --diff-filter=U)
            conflicts+=("$pr_num:$branch:$conflicted_files")
            echo "  ❌ MERGE CONFLICT detected"
            echo "  Conflicted files:"
            echo "$conflicted_files" | sed 's/^/    - /'
            git merge --abort 2>/dev/null
        else
            errors+=("$pr_num:$branch")
            echo "  ⚠️  Error during merge test"
        fi
    fi
    
    # Cleanup
    git checkout refs/remotes/origin/main 2>/dev/null
    git branch -D "$test_branch" 2>/dev/null
    echo ""
done

echo ""
echo "=== SUMMARY ==="
echo "PRs with clean merges: ${#mergeable[@]}"
echo "PRs with merge conflicts: ${#conflicts[@]}"
echo "PRs with errors: ${#errors[@]}"
echo ""

if [ ${#conflicts[@]} -gt 0 ]; then
    echo "=== PRs WITH MERGE CONFLICTS (UNMERGEABLE) ==="
    for item in "${conflicts[@]}"; do
        IFS=':' read -r pr_num branch files <<< "$item"
        echo "PR #$pr_num ($branch)"
        echo "  Conflicted files:"
        echo "$files" | tr ' ' '\n' | sed 's/^/    - /'
        echo ""
    done
fi

if [ ${#mergeable[@]} -gt 0 ]; then
    echo "=== PRs WITHOUT MERGE CONFLICTS (MERGEABLE) ==="
    for item in "${mergeable[@]}"; do
        IFS=':' read -r pr_num branch <<< "$item"
        echo "PR #$pr_num ($branch)"
    done
fi
