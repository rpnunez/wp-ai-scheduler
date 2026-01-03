#!/bin/bash

# Script to check which PRs are behind their target branch
# This script checks the divergence status of all open PRs

# Array of PR numbers, branch names, base branches, and head SHAs
declare -A prs
prs[65]="wizard-system-status-copy-2346282826133477814:main:090b46588c15d790ad57ff3d1255b250532ced57"
prs[63]="palette-clear-topics-button-12343389530500501724:main:70318e46da55c598ac767ee13778a8550bc394a1"
prs[62]="sentinel-input-validation-14523201659580960297:main:018e15c97b9377e518ed7e065b2b5c37166689b3"
prs[61]="bolt-bulk-insert-optimization-14417970550656218874:main:88e653a2c223e911acb76d32455d48610c601f39"
prs[58]="copilot/modify-aips-db-manager:main:582060efc10dd07408525d6ad62159f3dcecc7b8"
prs[57]="copilot/reduce-redundancy-in-repository:main:aba9a019bd2ee7651398f7ce739800c8de2fa98b"
prs[55]="hunter-wizard-atlas-bolt-705793256190618529:main:a1f14ba5e4351e236eb4ea645319a7622ec6048d"
prs[54]="all-operational-modes-4464187572361531219:main:8c1e8b9ab1a47e115b466b4eed6983700fadcb9b"
prs[52]="copilot/implement-database-repository-layer:main:20884c7ebb3ba6cf32270a357c405aa0bd904e4a"
prs[47]="atlas/extract-image-service-10996060423954448602:main:41aed7ceb47deb0f26291d1379ee3cfd8b916c18"
prs[38]="palette-editable-topics-17633147156937485405:main:4410840ee81a51c8be1f512aaac70e398a04dac9"
prs[30]="hunter-fix-scheduler-id-collision-15743482046909698209:main:8adb380dc979e64c1ad1cb706dc6444e0a5be8ea"
prs[28]="wizard-history-search-8655622669745702532:main:528779f297ad1c4fae8e32bc1f30acf996e983c7"
prs[26]="palette-a11y-fix-9149112686241413741:main:0a55e8a7fa019013be565852a38ff361dfe6536c"
prs[25]="sentinel-ssrf-fix-10986171741638357540:main:8086056bc9eb6ad08b6ff9933da8cc628d11edcd"
prs[24]="bolt-logger-optimization-13857484038144494642:main:209faa4fba7ef1558b855daf0ecdfc0347cf8940"
prs[14]="copilot/ciadd-pr-ci-workflow:main:15f2330ec39e8a09133e3844efb2521d1626375e"
prs[12]="copilot/improve-slow-code-efficiency:main:6fd5c6029a2b3211431f8bfc9846bba1a119518c"
prs[6]="wizard-template-search-11506985276342195298:main:ef76c6fec39163c745f73bbfc4cd211727a5f4a1"

echo "=== Checking PR Divergence Status ==="
echo ""

# Get current main SHA
git fetch origin main:refs/remotes/origin/main 2>/dev/null || true
MAIN_SHA=$(git rev-parse refs/remotes/origin/main 2>/dev/null || echo "unknown")
echo "Current main branch SHA: $MAIN_SHA"
echo ""

out_of_date=()
up_to_date=()

for pr_num in "${!prs[@]}"; do
    IFS=':' read -r branch base_branch head_sha <<< "${prs[$pr_num]}"
    
    # Fetch the branch
    git fetch origin "$branch:refs/remotes/origin/$branch" 2>/dev/null || continue
    
    # Check if the base branch (main) has commits not in the PR branch
    behind=$(git rev-list --count "origin/$branch..refs/remotes/origin/$base_branch" 2>/dev/null || echo "0")
    ahead=$(git rev-list --count "refs/remotes/origin/$base_branch..origin/$branch" 2>/dev/null || echo "0")
    
    if [ "$behind" -gt 0 ]; then
        out_of_date+=("$pr_num:$branch:$base_branch:$behind:$ahead")
        echo "❌ PR #$pr_num ($branch) is BEHIND $base_branch by $behind commits (ahead by $ahead)"
    else
        up_to_date+=("$pr_num:$branch:$base_branch")
        echo "✅ PR #$pr_num ($branch) is up to date with $base_branch"
    fi
done

echo ""
echo "=== SUMMARY ==="
echo "Out of date PRs: ${#out_of_date[@]}"
echo "Up to date PRs: ${#up_to_date[@]}"
echo ""

if [ ${#out_of_date[@]} -gt 0 ]; then
    echo "=== PRs THAT NEED UPDATES ==="
    for item in "${out_of_date[@]}"; do
        IFS=':' read -r pr_num branch base behind ahead <<< "$item"
        echo "[#$pr_num] $branch (Target: $base) - Behind by $behind commits, ahead by $ahead"
    done
fi
