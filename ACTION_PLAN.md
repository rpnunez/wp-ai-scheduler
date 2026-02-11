# 🚀 ACTION PLAN: Branching Strategy Implementation

**For**: Repository Owner (@rpnunez)  
**Status**: ✅ Code Complete - Needs Repository Configuration  
**Time Required**: 30-60 minutes

---

## ⚡ Quick Start (Do This First)

### 1. Create the `dev` Branch (5 minutes)

```bash
# Clone/update your local repo
cd /path/to/wp-ai-scheduler
git checkout main
git pull origin main

# Create and push dev branch
git checkout -b dev
git push origin dev

# Verify
git branch -a | grep dev
```

**Result**: ✅ `dev` branch exists and is identical to `main`

### 2. Configure Branch Protection (10-15 minutes)

#### For `main` Branch:

Go to: **Settings → Branches → Add rule**

**Branch name pattern**: `main`

Configure:
- ✅ Require pull request before merging
- ✅ Require **2** approvals
- ✅ Dismiss stale reviews
- ✅ Require status checks: PHPUnit Tests, Test Summary
- ✅ Require conversation resolution
- ✅ Include administrators
- ✅ Restrict who can push (admins/maintainers only)
- ❌ Allow force pushes: **Disabled**

Click **Create** or **Save changes**

#### For `dev` Branch:

**Settings → Branches → Add rule**

**Branch name pattern**: `dev`

Configure:
- ✅ Require pull request before merging
- ✅ Require **1** approval (less strict than main)
- ✅ Dismiss stale reviews
- ✅ Require status checks: PHPUnit Tests
- ❌ Include administrators (allow flexibility)
- ✅ Allow force pushes: **For specified actors** → Add maintainers
- ❌ Allow deletions: **Disabled**

Click **Create** or **Save changes**

**Result**: ✅ Both branches are protected

### 3. Set Default Branch (2 minutes) ⭐ **RECOMMENDED**

Go to: **Settings → General → Default branch**

1. Click the switch icon
2. Select `dev`
3. Click **Update**
4. Confirm: **"I understand, update the default branch."**

**Why**: New PRs will automatically target `dev`

**Result**: ✅ `dev` is now the default branch

### 4. Announce the Change (5 minutes)

Create a **Discussion** or pinned **Issue**:

**Title**: "📢 New Branching Strategy: Please Target dev Branch"

**Body**:
```markdown
## 🌿 Important: New Branching Strategy

We've implemented a new development workflow:

- **`main`** = Production-ready releases only (strict)
- **`dev`** = Active development (PRs go here)

### For All Contributors

**New PRs**: Please target the `dev` branch (not `main`)

**Existing PRs**: Please retarget to `dev`. See [PR_MIGRATION_GUIDE.md](./PR_MIGRATION_GUIDE.md)

### Why This Change?

- ✅ Reduces PR drift and conflicts
- ✅ Makes it easier to work on features in parallel  
- ✅ Keeps main stable for production
- ✅ Supports large refactors (like PR #665)

### Documentation

- 📖 [BRANCHING_STRATEGY.md](./BRANCHING_STRATEGY.md)
- 🤝 [CONTRIBUTING.md](./CONTRIBUTING.md)
- 🔄 [PR_MIGRATION_GUIDE.md](./PR_MIGRATION_GUIDE.md)
- 🎨 [BRANCHING_VISUAL_GUIDE.md](./BRANCHING_VISUAL_GUIDE.md)

### Questions?

Ask here or review the docs! Thank you! 🎉
```

**Pin this** discussion/issue to the top.

**Result**: ✅ Contributors are informed

---

## 📋 Next Steps (This Week)

### 5. Update Existing Open PRs (10-30 minutes)

For each open PR targeting `main`:

1. **Comment on the PR**:
   ```markdown
   Hi @username! 👋 We've implemented a new branching strategy.
   
   Could you please retarget this PR to `dev` instead of `main`?
   
   **How**: Click "Edit" next to base branch → Select `dev`
   
   See [PR_MIGRATION_GUIDE.md](./PR_MIGRATION_GUIDE.md) for help.
   
   Thank you! 🙏
   ```

2. **Or change it yourself** (if contributor is inactive):
   - Go to PR page
   - Click "Edit" next to base branch
   - Select `dev`
   - Add comment explaining the change

**Focus on**:
- ✨ PR #665 (UI refactor) - **High Priority**
- Any other active PRs with recent activity

**Result**: ✅ PRs target the correct branch

### 6. Test the Workflows (5 minutes)

Test the new automated workflows:

```bash
# Test sync workflow
gh workflow run sync-branches.yml

# Check it ran
gh run list --limit 3
```

Or via GitHub UI:
- **Actions** → **Sync dev with main** → **Run workflow**

**Result**: ✅ Workflows tested and working

---

## 🎯 Optional Enhancements

### 7. Create a Symbolic First Release (Optional)

Once dev is established, consider creating a small release PR:

```bash
gh workflow run release-pr.yml \
  -f version=1.7.1 \
  -f release_notes="Branching strategy implementation"
```

This creates a PR from `dev` → `main` for review and establishes the pattern.

**Result**: ✅ Release workflow tested

### 8. Update Repository Description (2 minutes)

Go to repository homepage:
- Click **⚙️** next to "About"
- Update description:
  ```
  WordPress AI Post Scheduler plugin | Main: production | Dev: active development
  ```
- Add topics: `wordpress-plugin`, `ai`, `automation`, `dual-branch`

**Result**: ✅ Clear repo description

---

## ✅ Verification Checklist

After completing the above, verify:

- [ ] `dev` branch exists on GitHub
- [ ] `dev` branch visible in dropdown menu
- [ ] Branch protection configured for `main` (2+ approvals, strict)
- [ ] Branch protection configured for `dev` (1+ approval, flexible)
- [ ] Default branch set to `dev` (recommended)
- [ ] Announcement posted and pinned
- [ ] Existing PRs commented or retargeted
- [ ] At least one workflow tested
- [ ] Repository description updated

---

## 📊 What You Get

After implementation:

### Immediate Benefits
- ✅ Main stays stable and production-ready
- ✅ Dev accepts all feature development
- ✅ Clear separation of concerns
- ✅ Automated release workflow
- ✅ Branch sync automation

### For PR #665 (UI Refactor)
- ✅ Can easily keep updated with dev
- ✅ Won't drift as far
- ✅ Can merge when ready without waiting
- ✅ Other work continues in parallel

### For Contributors
- ✅ Less conflicts to resolve
- ✅ Easier to keep branches updated
- ✅ Clear target for PRs
- ✅ Better documentation

---

## 📚 Reference Documents

All documentation is ready in this PR:

| Document | Purpose | Read Time |
|----------|---------|-----------|
| **[README.md](./README.md)** | Project overview | 5 min |
| **[BRANCHING_STRATEGY.md](./BRANCHING_STRATEGY.md)** | Complete strategy guide | 15 min |
| **[BRANCHING_VISUAL_GUIDE.md](./BRANCHING_VISUAL_GUIDE.md)** | Visual diagrams | 10 min |
| **[CONTRIBUTING.md](./CONTRIBUTING.md)** | Contribution guidelines | 15 min |
| **[BRANCH_MANAGEMENT.md](./BRANCH_MANAGEMENT.md)** | For maintainers | 20 min |
| **[PR_MIGRATION_GUIDE.md](./PR_MIGRATION_GUIDE.md)** | Update existing PRs | 10 min |
| **[DEV_BRANCH_SETUP.md](./DEV_BRANCH_SETUP.md)** | Detailed setup steps | 15 min |
| **[BRANCHING_IMPLEMENTATION_SUMMARY.md](./BRANCHING_IMPLEMENTATION_SUMMARY.md)** | Complete overview | 15 min |
| This document | Quick action plan | 5 min |

---

## 🆘 Need Help?

### Common Issues

**Q: Can't create dev branch**
```bash
# Make sure you're on main
git checkout main
git pull origin main
git checkout -b dev
git push origin dev
```

**Q: Can't configure branch protection**
- Requires **admin** access to repository
- Settings → Branches → Add rule

**Q: Workflow not showing up**
- Wait a few minutes after pushing
- Check Actions tab
- May need to enable actions in Settings

**Q: Contributors confused**
- Point them to [BRANCHING_VISUAL_GUIDE.md](./BRANCHING_VISUAL_GUIDE.md)
- Or [PR_MIGRATION_GUIDE.md](./PR_MIGRATION_GUIDE.md)
- Offer to help in comments

---

## ⏱️ Time Breakdown

| Task | Time | Priority |
|------|------|----------|
| Create dev branch | 5 min | 🔴 Critical |
| Configure protection | 15 min | 🔴 Critical |
| Set default branch | 2 min | 🟡 High |
| Post announcement | 5 min | 🟡 High |
| Update existing PRs | 30 min | 🟡 High |
| Test workflows | 5 min | 🟢 Medium |
| **Total Core Tasks** | **~60 min** | |

---

## 🎉 Success Criteria

You'll know it's working when:

- ✅ New PRs automatically target `dev`
- ✅ Can't push directly to `main` or `dev`
- ✅ Contributors are targeting `dev`
- ✅ Automated workflows create PRs to `dev`
- ✅ You can create release PRs from `dev` → `main`
- ✅ Fewer PR conflicts and drift

---

## 💬 Communication Template

### For Individual PR Comments:

```markdown
Hi @username! 👋

We've implemented a new branching strategy with separate `main` (production) and `dev` (development) branches.

**Action needed**: Please retarget this PR to the `dev` branch.

**How to do it**:
1. Click "Edit" next to the base branch (where it says `base: main`)
2. Select `dev` from the dropdown
3. Done! ✨

See [PR_MIGRATION_GUIDE.md](./PR_MIGRATION_GUIDE.md) for detailed instructions.

Let me know if you need any help! 🤝
```

---

## 🚀 Ready to Start?

1. ☑️ Review this action plan (you're done!)
2. ☑️ Follow steps 1-4 above (30 minutes)
3. ☑️ Post announcement (5 minutes)
4. ☑️ Update existing PRs over the next few days
5. ☑️ Monitor and help contributors adjust

**Everything is ready to go!** All code and documentation is in this PR.

---

**Questions?** Review the detailed guides or ask in this PR thread.

**Last Updated**: 2026-02-10  
**Created By**: GitHub Copilot  
**Part of**: Branching Strategy Implementation
