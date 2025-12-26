# Copilot Setup Steps - Firewall Fix

## Problem
Copilot coding agent was blocked by firewall rules when trying to run `composer install` because it attempted to download packages from `api.github.com` after the firewall was already enabled in the Copilot environment.

## Solution
Created `.github/workflows/copilot-setup-steps.yml` which runs **before** the firewall is enabled, allowing Copilot to have pre-installed Composer dependencies available when it starts work.

## How It Works

### Workflow Execution Order
1. **GitHub Actions starts Copilot agent session**
2. **Copilot Setup Steps workflow runs first** (before firewall is enabled)
   - Checks out repository
   - Sets up PHP 8.1 with required extensions
   - Runs `composer install` with access to external URLs
   - Caches dependencies for faster subsequent runs
3. **Firewall is enabled** (blocks external connections)
4. **Copilot agent starts working** (dependencies already installed)

### Key Requirements
- The job **must** be named `copilot-setup-steps` (GitHub looks for this specific name)
- Workflow file must be at `.github/workflows/copilot-setup-steps.yml`
- Workflow runs automatically when Copilot agent is invoked

## Testing
The workflow can be triggered manually via `workflow_dispatch` to verify it works correctly:
```bash
# Via GitHub UI: Actions → Copilot Setup Steps → Run workflow
```

## Benefits
- ✅ No more firewall blocks when installing dependencies
- ✅ Faster Copilot execution (dependencies pre-cached)
- ✅ More reliable automated workflows
- ✅ Better developer experience

## References
- [GitHub Docs: Customizing Copilot Environment](https://docs.github.com/en/copilot/customizing-copilot/customizing-the-development-environment-for-copilot-coding-agent)
- [GitHub Blog: Copilot Setup Steps](https://github.blog/changelog/2025-07-30-copilot-coding-agent-custom-setup-steps-are-more-reliable-and-easier-to-debug/)
