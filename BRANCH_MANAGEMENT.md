# Branch Management Guide

This guide provides instructions for repository maintainers on managing the dual-branch workflow.

## Table of Contents

- [Initial Setup](#initial-setup)
- [Day-to-Day Operations](#day-to-day-operations)
- [Release Process](#release-process)
- [Hotfix Process](#hotfix-process)
- [Branch Maintenance](#branch-maintenance)
- [Troubleshooting](#troubleshooting)

## Initial Setup

### Create the dev Branch

If the `dev` branch doesn't exist yet:

```bash
# Ensure you have the latest main
git checkout main
git pull origin main

# Create dev from main
git checkout -b dev
git push origin dev
```

### Set Up Branch Protection

Go to **Settings → Branches** on GitHub and configure:

#### For `main` Branch:
1. Enable branch protection
2. Require pull request reviews (2+)
3. Require status checks to pass
4. Require branches to be up to date
5. Require conversation resolution
6. Do not allow bypassing
7. Restrict who can push (maintainers only)

#### For `dev` Branch:
1. Enable branch protection
2. Require pull request reviews (1+)
3. Require status checks to pass
4. Allow force pushes (maintainers only)
5. Less strict than main

### Update Default Branch (Optional)

Consider setting `dev` as the default branch:
1. Go to **Settings → General**
2. Under "Default branch", click switch icon
3. Select `dev`
4. Click "Update"

This makes new PRs target `dev` by default.

## Day-to-Day Operations

### Accepting Feature PRs

1. **Verify PR targets `dev`**:
   - Check the base branch in the PR
   - If targeting `main`, ask contributor to change to `dev`

2. **Review normally**:
   - Code quality
   - Tests passing
   - Documentation updated

3. **Merge to dev**:
   ```bash
   # Via GitHub UI (recommended)
   # Or via command line:
   git checkout dev
   git pull origin dev
   git merge --no-ff feature-branch
   git push origin dev
   ```

4. **Delete feature branch** after merge

### Monitoring dev Branch

Regular checks:
- [ ] CI/CD passing on dev
- [ ] No stale PRs targeting dev
- [ ] Dev is not too far ahead of main (plan releases)

### Keeping Track of Changes

Maintain CHANGELOG.md on dev:
```markdown
## [Unreleased]

### Added
- New feature X
- New feature Y

### Fixed
- Bug fix Z

### Changed
- Refactored component A
```

## Release Process

### 1. Prepare for Release

```bash
# Ensure dev is stable
git checkout dev
git pull origin dev

# Run full test suite
cd ai-post-scheduler
composer test

# Run any additional QA
```

### 2. Update Version Numbers

Update version in these files:
- `ai-post-scheduler/ai-post-scheduler.php` (plugin header)
- `CHANGELOG.md` (move Unreleased to version number)
- Any other version references

```bash
# Commit version bump
git add .
git commit -m "chore: Bump version to 1.8.0"
git push origin dev
```

### 3. Create Release PR

Use the automated workflow:

```bash
# Via GitHub UI:
# Go to Actions → Create Release PR → Run workflow
# Enter version: 1.8.0
# Enter release notes

# Or create manually:
gh pr create \
  --base main \
  --head dev \
  --title "release: Version 1.8.0" \
  --body "See CHANGELOG.md for details"
```

### 4. Review Release PR

Complete the checklist:
- [ ] All tests pass
- [ ] Manual testing completed
- [ ] Documentation updated
- [ ] CHANGELOG.md updated
- [ ] Version numbers bumped
- [ ] Breaking changes documented
- [ ] Migration guide (if needed)

### 5. Merge to main

```bash
# Via GitHub UI (recommended)
# Use "Create a merge commit" (not squash)
```

### 6. Tag the Release

```bash
git checkout main
git pull origin main
git tag -a v1.8.0 -m "Release version 1.8.0"
git push origin v1.8.0
```

### 7. Create GitHub Release

1. Go to **Releases → Draft a new release**
2. Choose the tag: `v1.8.0`
3. Title: `Version 1.8.0`
4. Description: Copy from CHANGELOG.md
5. Attach plugin zip (if applicable)
6. Publish release

### 8. Sync dev with main

Run the sync workflow or manually:

```bash
git checkout dev
git pull origin dev
git merge origin/main
git push origin dev
```

## Hotfix Process

For critical production bugs that can't wait for next release.

### 1. Create Hotfix Branch from main

```bash
git checkout main
git pull origin main
git checkout -b hotfix/critical-security-fix
```

### 2. Make the Fix

```bash
# Fix the issue
# Add tests
# Update CHANGELOG.md

git add .
git commit -m "fix: Critical security vulnerability in template processor"
```

### 3. Create PR to main

```bash
gh pr create \
  --base main \
  --head hotfix/critical-security-fix \
  --title "fix: Critical security vulnerability" \
  --label "hotfix" \
  --label "security"
```

### 4. Fast-Track Review

- Get immediate review from maintainers
- Expedite approval process
- Merge ASAP

### 5. Tag Hotfix Release

```bash
git checkout main
git pull origin main
git tag -a v1.7.1 -m "Hotfix: Security vulnerability"
git push origin v1.7.1
```

### 6. Backport to dev

```bash
# Create PR from hotfix branch to dev
gh pr create \
  --base dev \
  --head hotfix/critical-security-fix \
  --title "chore: Backport security fix to dev" \
  --body "Backporting hotfix from main"

# Or merge manually
git checkout dev
git merge hotfix/critical-security-fix
git push origin dev
```

### 7. Clean Up

```bash
git branch -d hotfix/critical-security-fix
git push origin --delete hotfix/critical-security-fix
```

## Branch Maintenance

### Weekly Tasks

- [ ] Review open PRs
- [ ] Check if dev is ready for release
- [ ] Update CHANGELOG.md on dev
- [ ] Check for stale branches

### Monthly Tasks

- [ ] Review branch protection settings
- [ ] Check CI/CD health on both branches
- [ ] Review and close stale issues/PRs
- [ ] Update documentation

### Keeping Branches in Sync

Run the sync workflow periodically:
```bash
# Via GitHub Actions
Actions → Sync dev with main → Run workflow
```

## Troubleshooting

### Problem: PR Created Against Wrong Branch

**Symptom**: Someone created a PR to `main` instead of `dev`

**Solution**:
```bash
# Ask contributor to change base branch:
# On PR page → Edit button next to base branch → Change to dev

# Or close and recreate:
gh pr close ISSUE_NUMBER
# Create new PR targeting dev
```

### Problem: dev Has Diverged from main

**Symptom**: After a hotfix, dev and main have different histories

**Solution**:
```bash
git checkout dev
git pull origin dev
git merge origin/main

# If conflicts, resolve them
git add .
git commit -m "chore: Sync dev with main after hotfix"
git push origin dev
```

### Problem: Accidental Commit to main

**Symptom**: Someone pushed directly to main (if protection is misconfigured)

**Solution**:
```bash
# Revert the commit
git checkout main
git revert HEAD
git push origin main

# Ensure branch protection is enabled
# Go to Settings → Branches → Edit main branch protection
```

### Problem: Feature Branch Too Far Behind dev

**Symptom**: Feature PR has many conflicts with dev

**Solution**:
```bash
# Ask contributor to update their branch:
git checkout feature-branch
git merge dev
# Or: git rebase dev

# Resolve conflicts
git push origin feature-branch --force-with-lease  # if rebased
```

### Problem: Need to Undo a Merge to dev

**Symptom**: A merged PR to dev broke something

**Solution**:
```bash
git checkout dev
git pull origin dev

# Find the merge commit
git log --oneline --graph -10

# Revert the merge (replace MERGE_SHA)
git revert -m 1 MERGE_SHA
git push origin dev
```

### Problem: Lost Work on dev

**Symptom**: Force push or other issue lost commits

**Solution**:
```bash
# Use git reflog to find lost commits
git reflog show dev

# Find the commit before the problem (e.g., dev@{1})
git checkout dev
git reset --hard dev@{1}
git push origin dev --force-with-lease

# Requires force push permissions - use carefully!
```

## Best Practices for Maintainers

### 1. Communication

- Announce releases in advance
- Inform team before merging large PRs
- Document breaking changes clearly
- Use GitHub Discussions for questions

### 2. Release Cadence

- Regular releases (weekly/bi-weekly)
- Don't let dev get too far ahead
- Plan release windows
- Communicate release schedule

### 3. Code Review

- Require reviews even for maintainers
- Check tests and documentation
- Verify branch target
- Look for breaking changes

### 4. Branch Health

- Keep branches up-to-date
- Run tests regularly
- Monitor CI/CD failures
- Address issues promptly

### 5. Documentation

- Keep BRANCHING_STRATEGY.md updated
- Document special cases
- Update workflows as needed
- Maintain this guide

## Automated Workflows

### Available Workflows

1. **Create Release PR**: Creates PR from dev to main
2. **Sync Branches**: Syncs dev with main after releases
3. **CI/CD**: Runs tests on both branches
4. **Feature Agent**: Targets dev for automated PRs
5. **Feature Analysis**: Targets dev for automated PRs

### Running Workflows

```bash
# Via GitHub UI:
Actions → Select workflow → Run workflow

# Via GitHub CLI:
gh workflow run release-pr.yml -f version=1.8.0
gh workflow run sync-branches.yml
```

## Emergency Procedures

### Break Glass: Bypass Protection

If absolutely necessary to directly push to main:

1. Go to Settings → Branches
2. Temporarily disable protection
3. Make the critical change
4. Re-enable protection immediately
5. Document the incident
6. Backport to dev

**Use this only in extreme emergencies!**

## Questions?

- Review [BRANCHING_STRATEGY.md](./BRANCHING_STRATEGY.md)
- Check [CONTRIBUTING.md](./CONTRIBUTING.md)
- Ask in GitHub Discussions
- Contact repository owner

---

**Last Updated**: 2026-02-10
**Maintainers**: Repository owners and collaborators
