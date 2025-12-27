# Maintenance Tools

This directory contains bash scripts used by the Maintenance Agent to analyze and manage Pull Requests.

## Scripts

### check_pr_divergence.sh

Checks which PRs are behind their target branch.

**What it does:**
- Fetches all open PR branches
- Compares each PR's head commit with its base branch using `git rev-list`
- Reports how many commits each PR is behind/ahead

**Usage:**
```bash
./.tools/check_pr_divergence.sh
```

**Output:**
- Lists PRs that are out of date with their target branch
- Shows commit counts (behind/ahead) for each PR

### check_merge_conflicts.sh

Tests if each PR can be cleanly merged into its target branch.

**What it does:**
- Creates temporary test branches from main
- Attempts to merge each PR branch
- Identifies any conflicting files

**Usage:**
```bash
./.tools/check_merge_conflicts.sh
```

**Output:**
- Lists PRs with clean merges (no conflicts)
- Lists PRs with merge conflicts and conflicted files

**Note:** This script performs actual merge tests locally but aborts them before committing.

### check_mergeable_status.sh

Queries the GitHub API for each PR's mergeable status.

**What it does:**
- Uses GitHub API to check mergeable status
- Requires `curl` and `jq` to be installed

**Usage:**
```bash
./.tools/check_mergeable_status.sh
```

**Output:**
- Lists PRs by mergeable status (mergeable, unmergeable, unknown)

**Note:** Requires internet access to query the GitHub API.

## Requirements

- Git
- Bash
- curl (for check_mergeable_status.sh)
- jq (for check_mergeable_status.sh)

## Notes

- These scripts are designed for the `rpnunez/wp-ai-scheduler` repository
- PR numbers and branch names are hardcoded and should be updated when running against current PRs
- Scripts should be run from the repository root directory
