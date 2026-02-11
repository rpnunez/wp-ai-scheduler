# Migration Guide for Existing Pull Requests

## Overview

This repository has transitioned to a new branching strategy with separate `main` (production) and `dev` (development) branches. If you have an existing open PR targeting `main`, you may need to update it.

## New Branching Strategy

- **`main`** - Production-ready code only (releases, hotfixes)
- **`dev`** - Active development (features, bug fixes)

Most PRs should now target `dev` instead of `main`.

## Do I Need to Update My PR?

### ✅ Update Your PR if:
- Your PR is a **new feature**
- Your PR is a **bug fix** for existing functionality
- Your PR is a **refactoring or improvement**
- Your PR is **documentation** for features in development

### ⚠️ Keep Targeting main if:
- Your PR is a **critical hotfix** for production
- You're creating a **release PR** from `dev` to `main`
- Explicitly requested by maintainers

## How to Change Your PR Target

### Option 1: Via GitHub UI (Easiest)

1. Go to your Pull Request on GitHub
2. Look for the base branch section (shows `base: main`)
3. Click **Edit** button next to the base branch
4. Select `dev` from the dropdown
5. GitHub will automatically update the PR

**Screenshot Guide**:
```
[Your PR] → base: main ← [Edit button]
          ↓
          Select: dev
          ↓
          [Update PR]
```

### Option 2: Via Command Line (If Edit is Unavailable)

If the GitHub UI doesn't allow changing the base branch:

```bash
# 1. Update your local branch
git checkout your-feature-branch
git pull origin your-feature-branch

# 2. Rebase onto dev
git fetch origin dev
git rebase origin/dev

# 3. Force push (if needed after rebase)
git push origin your-feature-branch --force-with-lease

# 4. Close old PR and create new one targeting dev
gh pr create \
  --base dev \
  --head your-feature-branch \
  --title "Your PR Title" \
  --body "Original PR: #NUMBER

Your PR description here"
```

### Option 3: Close and Recreate (Last Resort)

If you can't change the base or rebase:

1. **Note down your PR details** (title, description, reviewers)
2. **Close the old PR** with a comment: "Closing to retarget to dev branch. See #NEW_PR_NUMBER"
3. **Create a new PR**:
   ```bash
   gh pr create \
     --base dev \
     --head your-feature-branch \
     --title "Your PR Title" \
     --body "Retargeted from #OLD_PR_NUMBER to follow new branching strategy.
   
   Original description:
   [paste your original description]"
   ```
4. **Link the PRs** in comments

## Handling Merge Conflicts

If your branch has drifted from `dev`:

```bash
# Update your branch with dev
git checkout your-feature-branch
git fetch origin dev
git merge origin/dev

# Or use rebase for cleaner history
git rebase origin/dev

# Resolve any conflicts
# Edit files, then:
git add .
git rebase --continue  # if rebasing
# or
git commit             # if merging

# Push changes
git push origin your-feature-branch --force-with-lease  # if rebased
# or
git push origin your-feature-branch  # if merged
```

## Large Refactoring PRs (Like PR #665)

If you have a large, ongoing refactoring PR:

### Benefits of Targeting dev:
- ✅ Easier to keep updated (merge dev frequently)
- ✅ Other work can proceed in parallel
- ✅ Can test changes together with other new features
- ✅ Less drift over time
- ✅ Cleaner release process

### Keeping Your Branch Updated:

```bash
# Regularly sync with dev (weekly or more)
git checkout your-large-feature-branch
git fetch origin dev
git merge origin/dev
# Resolve conflicts
git push origin your-large-feature-branch
```

## Communication

### When Updating Your PR:

1. **Add a comment** to your PR:
   ```
   Updated to target `dev` branch per new branching strategy.
   Resolved conflicts with latest dev.
   
   Changes: [list if any significant changes due to rebasing]
   ```

2. **Re-request reviews** if your PR had approvals

3. **Update PR description** if needed to reflect new context

## For PR #665 (UI Refactor) Specifically

Since you mentioned PR #665 as a large refactor:

1. **Change base to `dev`** using GitHub UI
2. **Regularly merge `dev`** into your branch (weekly):
   ```bash
   git checkout feature/ui-refactor
   git merge origin/dev
   git push origin feature/ui-refactor
   ```
3. **Continue development** - the branch will stay fresh
4. **When ready**, merge to `dev`, then later include in a release to `main`

## Timeline for Transition

- **Immediately**: New PRs should target `dev`
- **This Week**: Update existing feature/fix PRs to target `dev`
- **Next Week**: All PRs targeting `dev` unless explicitly hotfixes
- **Ongoing**: `dev` merges to `main` for releases

## Need Help?

### Can't Change Base Branch?

If GitHub won't let you edit the base branch:
- It might be because of existing approvals or merge conflicts
- Try the command-line approach instead
- Or comment on your PR asking a maintainer for help

### Don't Know Which Branch to Target?

Ask yourself:
- Is this fixing a **critical production bug**? → `main` (then backport to `dev`)
- Is this **anything else**? → `dev`

Still unsure? Ask in your PR comments or in Discussions.

### Having Merge Conflicts?

1. Try the merge/rebase steps above
2. If stuck, ask for help in your PR
3. A maintainer can help resolve complex conflicts

## Questions?

- Check [BRANCHING_STRATEGY.md](./BRANCHING_STRATEGY.md) for complete details
- Read [CONTRIBUTING.md](./CONTRIBUTING.md) for contribution guidelines
- Ask in [GitHub Discussions](https://github.com/rpnunez/wp-ai-scheduler/discussions)
- Comment on your PR if you need specific help

## Summary Checklist

For each of your open PRs:

- [ ] Determine if it should target `dev` (most PRs) or `main` (hotfixes only)
- [ ] Change base branch via GitHub UI (easiest method)
- [ ] Update your branch with latest `dev` if needed
- [ ] Resolve any merge conflicts
- [ ] Add comment to PR explaining the change
- [ ] Re-request reviews if needed
- [ ] Continue with normal PR process

Thank you for adapting to the new workflow! This will help us manage development more effectively and reduce PR drift. 🎉

---

**Last Updated**: 2026-02-10
**Related**: [BRANCHING_STRATEGY.md](./BRANCHING_STRATEGY.md) | [CONTRIBUTING.md](./CONTRIBUTING.md)
