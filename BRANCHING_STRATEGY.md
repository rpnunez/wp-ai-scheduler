# Branching Strategy

## Overview

This repository follows a **dual-branch workflow** designed to maintain a stable production-ready codebase while supporting active development and large refactoring efforts.

## Branch Structure

### 🌟 `main` - Production-Ready Branch
- **Purpose**: Stable, tested, production-ready code
- **Status**: Protected, requires PR reviews
- **Deployments**: All releases are tagged from this branch
- **Updates**: Only via PRs from `dev` branch after thorough testing

### 🔨 `dev` - Development Branch
- **Purpose**: Active development and integration
- **Status**: Protected, accepts feature PRs
- **Testing**: All CI/CD tests must pass before merging to main
- **Updates**: Feature branches merge here first

### 🌿 Feature Branches
- **Naming**: `feature/description` or `copilot/description`
- **Source**: Created from `dev` branch
- **Target**: Merge back to `dev` branch
- **Lifecycle**: Deleted after merge

### 🐛 Hotfix Branches
- **Naming**: `hotfix/description`
- **Source**: Created from `main` branch (for critical fixes)
- **Target**: Merge to both `main` and `dev`
- **Purpose**: Emergency production fixes only

## Workflow Diagrams

### Standard Feature Development
```
main ──────────────────────────────→ main (stable)
         ↖                     ↗
          dev ─────────────→ dev
               ↖         ↗
                feature-branch
```

### Hotfix Workflow
```
main ──→ hotfix ──→ main
         ↓
         └────────→ dev
```

### Release Workflow
```
dev → (testing) → PR to main → merge → tag release
```

## Pull Request Guidelines

### For Feature Development
1. **Create branch from `dev`**:
   ```bash
   git checkout dev
   git pull origin dev
   git checkout -b feature/your-feature-name
   ```

2. **Open PR targeting `dev`**:
   - Title: `feat: Brief description`
   - Add appropriate labels
   - Link related issues
   - Request reviews

3. **After merge to `dev`**:
   - Feature is available in development
   - Will be included in next release to `main`

### For Production Release
1. **Create PR from `dev` to `main`**:
   - Title: `release: Version X.Y.Z`
   - Include changelog
   - Comprehensive testing required
   - Multiple reviewer approval

2. **After merge to `main`**:
   - Tag the release
   - Deploy to production
   - Update documentation

### For Hotfixes
1. **Create branch from `main`**:
   ```bash
   git checkout main
   git pull origin main
   git checkout -b hotfix/critical-fix
   ```

2. **Create two PRs**:
   - PR #1: `hotfix/critical-fix` → `main` (immediate fix)
   - PR #2: `hotfix/critical-fix` → `dev` (keep dev in sync)

## Branch Protection Rules

### Recommended Settings for `main`
- ✅ Require pull request reviews (minimum 2)
- ✅ Require status checks to pass
- ✅ Require branches to be up to date
- ✅ Require conversation resolution
- ✅ Do not allow bypassing the above settings
- ✅ Restrict who can push (maintainers only)

### Recommended Settings for `dev`
- ✅ Require pull request reviews (minimum 1)
- ✅ Require status checks to pass
- ✅ Allow force pushes (for maintainers only)
- ❌ Less strict than main, to allow for active development

## Automated Workflows

### CI/CD on `dev`
- Runs on every PR to `dev`
- Runs on every push to `dev`
- Full test suite execution
- Code quality checks
- Build validation

### CI/CD on `main`
- Runs on every PR to `main`
- Comprehensive test suite
- Security scanning
- Performance checks
- Release artifact creation

### Auto-generated PRs
- **Feature Reports**: Target `dev` branch
- **Feature Analysis**: Target `dev` branch
- **Automated updates**: Target `dev` branch

## Best Practices

### 1. Keep Branches Updated
```bash
# Update your feature branch with latest dev
git checkout dev
git pull origin dev
git checkout feature/your-feature
git merge dev
# Or rebase: git rebase dev
```

### 2. Small, Focused PRs
- One feature per PR
- Clear, descriptive commits
- Add tests for new features
- Update documentation

### 3. Regular Integration
- Merge feature branches to `dev` frequently
- Don't let feature branches drift too far
- Resolve conflicts early

### 4. Testing Before Release
- All tests pass on `dev`
- Manual testing of new features
- Regression testing
- Performance validation

### 5. Clear Communication
- Use PR descriptions effectively
- Link to related issues
- Explain breaking changes
- Document migration steps

## Common Scenarios

### Scenario 1: Working on a New Feature
```bash
# Start
git checkout dev
git pull origin dev
git checkout -b feature/amazing-feature

# Work and commit
git add .
git commit -m "feat: Add amazing feature"

# Push and create PR targeting dev
git push origin feature/amazing-feature
# Create PR: feature/amazing-feature → dev
```

### Scenario 2: Large Refactoring (like PR #665)
```bash
# Create long-lived feature branch from dev
git checkout dev
git pull origin dev
git checkout -b feature/ui-refactor

# Periodically sync with dev to avoid drift
git checkout dev
git pull origin dev
git checkout feature/ui-refactor
git merge dev

# When ready, create PR to dev
# Create PR: feature/ui-refactor → dev
```

### Scenario 3: Preparing a Release
```bash
# Ensure dev is tested and stable
# Create release PR
git checkout main
git pull origin main
git checkout dev
git pull origin dev

# Create PR: dev → main
# Title: "release: Version 1.8.0"
# Include changelog and test results
```

### Scenario 4: Emergency Hotfix
```bash
# Create from main
git checkout main
git pull origin main
git checkout -b hotfix/critical-security-fix

# Fix and test
git add .
git commit -m "fix: Critical security issue"

# Create two PRs
git push origin hotfix/critical-security-fix
# PR 1: hotfix/critical-security-fix → main (urgent)
# PR 2: hotfix/critical-security-fix → dev (sync)
```

## Migration Guide

### For Existing Open PRs

If you have existing PRs targeting `main`, you have two options:

#### Option 1: Change PR Target to `dev` (Recommended)
1. Go to your PR on GitHub
2. Click "Edit" next to the base branch
3. Change from `main` to `dev`
4. Update PR description if needed

#### Option 2: Keep Targeting `main` for Critical Changes
- Only for production hotfixes
- Requires additional approval
- Must be backported to `dev`

### For Repository Maintainers

1. **Create `dev` branch from current `main`**:
   ```bash
   git checkout main
   git pull origin main
   git checkout -b dev
   git push origin dev
   ```

2. **Update branch protection rules** in GitHub Settings

3. **Update automated workflows** to target `dev`

4. **Communicate change** to all contributors

## Why This Strategy?

### Problems Solved
1. **Prevents PR Drift**: Feature branches target `dev`, easier to keep updated
2. **Protects Production**: `main` only receives tested, stable code
3. **Enables Large Refactors**: Long-lived branches can sync with `dev` regularly
4. **Reduces Conflicts**: Integration happens in `dev`, isolated from production
5. **Clear Release Process**: Explicit `dev` → `main` PRs for releases

### Benefits
- ✅ Stable production branch
- ✅ Active development without breaking production
- ✅ Clear release cycle
- ✅ Easier to track what's in production vs development
- ✅ Reduced PR management overhead
- ✅ Better for teams and large refactorings

## Questions?

### Q: Can I still create PRs to `main`?
**A**: Only for critical hotfixes. All regular development should target `dev`.

### Q: How often should `dev` merge to `main`?
**A**: When ready for a release (weekly, bi-weekly, or per-feature depending on your cycle).

### Q: What if my feature branch is behind `dev`?
**A**: Regularly merge `dev` into your feature branch to stay up-to-date:
```bash
git checkout feature/my-feature
git merge dev
```

### Q: What about automated PRs from workflows?
**A**: They now target `dev` by default. Will be included in next release.

### Q: How do I know which branch to target?
**A**: 
- 🌿 New features → `dev`
- 🔧 Regular fixes → `dev`
- 🐛 Critical hotfixes → `main` (then backport to `dev`)
- 📦 Releases → `dev` → `main`

## Resources

- [GitHub Flow Documentation](https://docs.github.com/en/get-started/quickstart/github-flow)
- [Git Flow Explanation](https://www.atlassian.com/git/tutorials/comparing-workflows/gitflow-workflow)
- [Contributing Guide](./CONTRIBUTING.md)

## History

- **2026-02-10**: Branching strategy implemented
  - Created `dev` branch for active development
  - Updated workflows to target `dev`
  - Added comprehensive documentation
