#!/bin/bash

# Script to check mergeable status via GitHub API
# This script queries the GitHub API for each PR's mergeable status

# List of PR numbers to check
prs=(6 12 14 24 25 26 28 30 38 47 52 54 55 57 58 61 62 63 65)

echo "=== GitHub API Mergeable Status Check ==="
echo ""

unmergeable=()
mergeable=()
unknown=()

for pr_num in "${prs[@]}"; do
    echo "Checking PR #$pr_num..."
    
    # Use curl to check via GitHub API (no auth needed for public repos)
    response=$(curl -s "https://api.github.com/repos/rpnunez/wp-ai-scheduler/pulls/$pr_num")
    
    mergeable_state=$(echo "$response" | jq -r '.mergeable_state // "unknown"')
    mergeable=$(echo "$response" | jq -r '.mergeable // "unknown"')
    
    echo "  Mergeable: $mergeable"
    echo "  Mergeable State: $mergeable_state"
    
    if [ "$mergeable" = "false" ]; then
        unmergeable+=("$pr_num")
        echo "  ❌ UNMERGEABLE (has conflicts)"
    elif [ "$mergeable" = "true" ]; then
        mergeable+=("$pr_num")
        echo "  ✅ MERGEABLE"
    else
        unknown+=("$pr_num")
        echo "  ⚠️  Status unknown"
    fi
    
    echo ""
done

echo ""
echo "=== SUMMARY ==="
echo "Mergeable PRs: ${#mergeable[@]}"
echo "Unmergeable PRs (conflicts): ${#unmergeable[@]}"
echo "Unknown status: ${#unknown[@]}"
echo ""

if [ ${#unmergeable[@]} -gt 0 ]; then
    echo "=== UNMERGEABLE PRs (HAVE MERGE CONFLICTS) ==="
    for pr_num in "${unmergeable[@]}"; do
        echo "  PR #$pr_num"
    done
    echo ""
fi

if [ ${#mergeable[@]} -gt 0 ]; then
    echo "=== MERGEABLE PRs (NO CONFLICTS) ==="
    for pr_num in "${mergeable[@]}"; do
        echo "  PR #$pr_num"
    done
fi
