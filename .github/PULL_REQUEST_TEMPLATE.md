## Description

<!-- Provide a brief description of the changes in this PR -->

## Type of Change

<!-- Mark the relevant option with an [x] -->

- [ ] 🐛 Bug fix (non-breaking change that fixes an issue)
- [ ] ✨ New feature (non-breaking change that adds functionality)
- [ ] 💥 Breaking change (fix or feature that would cause existing functionality to not work as expected)
- [ ] 📝 Documentation update
- [ ] 🎨 Code style/refactoring (no functional changes)
- [ ] ✅ Test addition or update
- [ ] 🔧 Chore (dependency updates, build config, etc.)

## Target Branch

<!-- Verify you're targeting the correct branch -->

**This PR targets**: <!-- main or dev -->

- [ ] ✅ **dev** - For regular features, bug fixes, and improvements
- [ ] ⚠️ **main** - For critical hotfixes only (requires justification below)

**Justification for targeting main** (if applicable):
<!-- Explain why this needs to go directly to main instead of dev -->

## Related Issues

<!-- Link to related issues using GitHub keywords -->

Closes #<!-- issue number -->
Relates to #<!-- issue number -->

## Changes Made

<!-- Provide a clear list of changes -->

- 
- 
- 

## Testing

### Test Coverage

- [ ] Unit tests added/updated
- [ ] All tests passing locally (`composer test`)
- [ ] Manual testing completed

### Test Instructions

<!-- Describe how reviewers can test these changes -->

1. 
2. 
3. 

### Test Results

<!-- Paste relevant test output if applicable -->

```
# composer test output
```

## Screenshots

<!-- If applicable, add screenshots to help explain your changes -->

### Before
<!-- Screenshot or description of the old behavior -->

### After
<!-- Screenshot or description of the new behavior -->

## Breaking Changes

<!-- List any breaking changes and migration steps -->

- [ ] No breaking changes
- [ ] Breaking changes (described below)

**Breaking Changes Description**:
<!-- Detail what breaks and how users should migrate -->

## Documentation

- [ ] Code is self-documenting with clear variable/function names
- [ ] Added/updated inline comments for complex logic
- [ ] Added/updated PHPDoc blocks for new functions/classes
- [ ] Updated README.md (if needed)
- [ ] Updated CHANGELOG.md
- [ ] Updated relevant documentation in `docs/` (if needed)

## Code Quality

- [ ] Follows WordPress coding standards
- [ ] Follows plugin naming conventions (AIPS_ prefix)
- [ ] Uses tabs for indentation
- [ ] All output is properly escaped (`esc_html()`, `esc_attr()`, etc.)
- [ ] All input is properly sanitized
- [ ] Uses repository classes for database access (no direct `$wpdb`)
- [ ] Uses nonces for form submissions
- [ ] No PHP warnings or notices
- [ ] No JavaScript console errors

## Checklist

### Before Submitting

- [ ] I have read the [CONTRIBUTING.md](../CONTRIBUTING.md) guide
- [ ] I have read the [BRANCHING_STRATEGY.md](../BRANCHING_STRATEGY.md) guide
- [ ] My code follows the project's coding standards
- [ ] I have performed a self-review of my code
- [ ] I have commented my code, particularly in hard-to-understand areas
- [ ] My changes generate no new warnings or errors
- [ ] I have added tests that prove my fix is effective or that my feature works
- [ ] New and existing unit tests pass locally with my changes
- [ ] I have updated the documentation accordingly
- [ ] My branch is up-to-date with the target branch

### For Reviewers

**Reviewer Checklist**:
- [ ] Code quality and standards verified
- [ ] Tests are adequate and passing
- [ ] Documentation is clear and complete
- [ ] No security concerns
- [ ] No performance concerns
- [ ] Breaking changes are acceptable (if any)
- [ ] Ready to merge

## Additional Context

<!-- Add any other context, considerations, or notes for reviewers -->

## Deployment Notes

<!-- Any special deployment steps or considerations? -->

- [ ] No special deployment steps required
- [ ] Special steps required (described below)

**Special Deployment Steps**:
<!-- List any special steps needed when deploying this change -->

---

## For Maintainers

**Merge Strategy**:
- [ ] Squash and merge (recommended for single-purpose PRs)
- [ ] Create a merge commit (for multi-commit features)
- [ ] Rebase and merge (for clean linear history)

**Post-Merge Actions**:
- [ ] Update version number (if releasing)
- [ ] Tag release (if applicable)
- [ ] Update changelog
- [ ] Deploy to production (if merging to main)
- [ ] Announce changes (if significant)
