# Initial Dev Branch Setup

This document provides step-by-step instructions for repository maintainers to set up the `dev` branch and configure branch protection rules.

## Prerequisites

- Repository maintainer/admin access
- GitHub CLI installed (optional, for CLI steps)
- Git installed locally

## Step 1: Create the dev Branch

### Option A: Via Command Line (Recommended)

```bash
# 1. Clone the repository (if not already cloned)
git clone https://github.com/rpnunez/wp-ai-scheduler.git
cd wp-ai-scheduler

# 2. Ensure you're on the latest main
git checkout main
git pull origin main

# 3. Create dev branch from main
git checkout -b dev

# 4. Push dev branch to GitHub
git push origin dev

# 5. Verify the branch exists
git branch -a | grep dev
```

### Option B: Via GitHub Web UI

1. Go to https://github.com/rpnunez/wp-ai-scheduler
2. Click on the branch dropdown (shows "main")
3. Type `dev` in the "Find or create a branch" field
4. Click "Create branch: dev from 'main'"
5. The branch is created automatically

## Step 2: Configure Branch Protection for main

1. **Go to Repository Settings**
   - Navigate to: Settings → Branches
   - Click "Add branch protection rule"

2. **Branch name pattern**: `main`

3. **Configure Protection Rules**:

   #### Required Reviews
   - ✅ **Require a pull request before merging**
   - ✅ **Require approvals**: Set to **2**
   - ✅ **Dismiss stale pull request approvals when new commits are pushed**
   - ✅ **Require review from Code Owners** (if CODEOWNERS file exists)

   #### Status Checks
   - ✅ **Require status checks to pass before merging**
   - ✅ **Require branches to be up to date before merging**
   - **Required checks** (select from the list):
     - `PHPUnit Tests` or similar test workflow
     - `Test Summary`
     - Any other critical CI checks

   #### Additional Rules
   - ✅ **Require conversation resolution before merging**
   - ✅ **Require signed commits** (optional, but recommended)
   - ✅ **Require linear history** (optional)
   - ✅ **Include administrators** (enforce rules for admins too)
   - ✅ **Restrict who can push to matching branches**
     - Add only trusted maintainers
     - Or leave empty to restrict all direct pushes

   #### Other Settings
   - ❌ **Allow force pushes** (keep disabled for main)
   - ❌ **Allow deletions** (keep disabled for main)

4. **Save Changes**

## Step 3: Configure Branch Protection for dev

1. **Add Another Branch Protection Rule**
   - Settings → Branches → Add rule

2. **Branch name pattern**: `dev`

3. **Configure Protection Rules** (Less Strict than main):

   #### Required Reviews
   - ✅ **Require a pull request before merging**
   - ✅ **Require approvals**: Set to **1** (less than main)
   - ✅ **Dismiss stale pull request approvals when new commits are pushed**

   #### Status Checks
   - ✅ **Require status checks to pass before merging**
   - ⚠️ **Require branches to be up to date before merging** (optional)
   - **Required checks**:
     - `PHPUnit Tests`
     - Basic CI checks
     - (Fewer checks than main)

   #### Additional Rules
   - ✅ **Require conversation resolution before merging**
   - ❌ **Include administrators** (allow admins to bypass for emergency fixes)
   - ⚠️ **Restrict who can push** (optional - allow core team)

   #### Other Settings
   - ✅ **Allow force pushes**: Enable for **maintainers only**
     - This allows rebasing when needed
   - ❌ **Allow deletions** (keep disabled)

4. **Save Changes**

## Step 4: Set Default Branch (Optional)

Consider making `dev` the default branch so new PRs target it by default:

### Via Web UI:
1. Go to Settings → General
2. Find "Default branch" section
3. Click the switch/edit icon
4. Select `dev` from dropdown
5. Click "Update"
6. Confirm the change

### Impact:
- ✅ New PRs will default to `dev` base
- ✅ New contributors will see `dev` first
- ✅ Clones/forks will default to `dev`
- ⚠️ Update any documentation/badges that reference "main"

## Step 5: Update Repository Description

1. Go to repository homepage
2. Click the gear icon next to "About"
3. Update description to mention branching strategy:
   ```
   WordPress plugin for AI-powered post scheduling.
   Main: production | Dev: active development
   ```

## Step 6: Update GitHub Actions Secrets/Variables

Check if any secrets or variables reference branch names:

1. Settings → Secrets and variables → Actions
2. Review all secrets/variables
3. Update any that explicitly reference `main` to be branch-agnostic
4. Or add `dev`-specific variables if needed

## Step 7: Verify Workflows

Run a test to ensure workflows work with the new branch:

```bash
# Trigger the sync workflow
gh workflow run sync-branches.yml

# Trigger feature agent
gh workflow run feature-agent.yml

# Check workflow runs
gh run list --limit 5
```

Or via Web UI:
1. Go to Actions tab
2. Select a workflow
3. Click "Run workflow"
4. Select `dev` branch
5. Click "Run workflow"

## Step 8: Communicate the Change

Create an announcement issue or discussion:

**Title**: "New Branching Strategy: Using dev branch for development"

**Content**:
```markdown
## 🌿 New Branching Strategy

We've implemented a new branching strategy to better manage development:

- **`main`** - Production-ready code (protected, strict)
- **`dev`** - Active development (protected, less strict)

### For Contributors

- **New PRs**: Target `dev` branch (not `main`)
- **Existing PRs**: Please retarget to `dev` (see guide below)
- **Hotfixes only**: Target `main` for critical production fixes

### Documentation

- 📖 [BRANCHING_STRATEGY.md](./BRANCHING_STRATEGY.md) - Complete strategy
- 🤝 [CONTRIBUTING.md](./CONTRIBUTING.md) - Contribution guidelines  
- 🔄 [PR_MIGRATION_GUIDE.md](./PR_MIGRATION_GUIDE.md) - Update existing PRs

### Why This Change?

- ✅ Keeps main stable and production-ready
- ✅ Reduces PR drift and conflicts
- ✅ Enables parallel work on large features (like PR #665)
- ✅ Clearer release process

### Questions?

See the documentation or ask in this thread!

Thank you for adapting to the new workflow! 🎉
```

Post this:
- As a pinned issue
- In Discussions
- In a comment on major open PRs

## Step 9: Update Existing PRs

For each open PR:

1. **Review the PR** - Is it a feature/fix (→ dev) or hotfix (→ main)?
2. **Comment on the PR**:
   ```markdown
   Hi! We've implemented a new branching strategy. Could you please retarget this PR to the `dev` branch?
   
   See [PR_MIGRATION_GUIDE.md](./PR_MIGRATION_GUIDE.md) for instructions.
   
   Thank you!
   ```
3. **Offer to help** if they have trouble
4. **Change it yourself** if contributor is unresponsive (use GitHub UI)

## Step 10: Create Initial Release from dev

Once dev is established, create an initial release to sync them:

```bash
# Create a release PR from dev to main
gh workflow run release-pr.yml \
  -f version=1.7.0 \
  -f release_notes="Initial release with new branching strategy"
```

Or create manually following [BRANCH_MANAGEMENT.md](./BRANCH_MANAGEMENT.md).

## Verification Checklist

After setup, verify:

- [ ] `dev` branch exists on GitHub
- [ ] `dev` is visible in branch dropdown
- [ ] Branch protection rules configured for `main`
- [ ] Branch protection rules configured for `dev`
- [ ] Default branch set (if changed)
- [ ] Workflows tested on `dev` branch
- [ ] Documentation updated
- [ ] Contributors notified
- [ ] Existing PRs reviewed/updated
- [ ] First release planned

## Rollback Plan

If you need to roll back:

1. **Disable branch protection** on `dev` temporarily
2. **Delete the `dev` branch**:
   ```bash
   git push origin --delete dev
   ```
3. **Revert workflow changes** in `.github/workflows/`
4. **Remove documentation files** added for branching strategy
5. **Announce the rollback** to contributors

## Troubleshooting

### Problem: Can't Push to main Anymore

**Cause**: Branch protection is working correctly

**Solution**: Create a PR from `dev` to `main` following the release process

### Problem: Workflows Failing on dev

**Cause**: May need to adjust branch triggers in workflow files

**Solution**:
1. Check workflow files for hardcoded `main` references
2. Update to include `dev` in branch triggers
3. Test again

### Problem: Contributors Confused

**Cause**: Change not well communicated

**Solution**:
1. Pin an announcement issue
2. Add note to existing PRs
3. Update PR template
4. Respond promptly to questions

## Next Steps

After setup:

1. **Monitor for issues** in the first week
2. **Help contributors** adjust to new workflow
3. **Plan first release** from dev to main
4. **Review and refine** branch protection rules as needed
5. **Update documentation** based on feedback

## Resources

- [BRANCHING_STRATEGY.md](./BRANCHING_STRATEGY.md)
- [BRANCH_MANAGEMENT.md](./BRANCH_MANAGEMENT.md)
- [CONTRIBUTING.md](./CONTRIBUTING.md)
- [GitHub Branch Protection Docs](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-protected-branches/about-protected-branches)

## Support

If you encounter issues during setup:
- Check the troubleshooting section
- Review GitHub's documentation
- Open a discussion
- Contact other maintainers

---

**Last Updated**: 2026-02-10
**For**: Repository maintainers/admins
