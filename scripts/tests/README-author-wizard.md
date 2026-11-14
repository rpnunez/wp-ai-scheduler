# Author Wizard browser regression

Run from the repository root with PHP 8.2+, Node.js, and Playwright. The test uses Microsoft Edge when that channel is available and falls back to Chromium:

```text
node --test scripts/tests/author-wizard.test.cjs
```

Playwright must be resolvable by Node (for example, through `NODE_PATH`). The test uses the existing vendored jQuery and renders the Author modal through a CLI-only PHP fixture. It loads the production Author script, shared Utilities action locks, and styles. AJAX is intercepted; no WordPress account, database, or AI provider is used.

Coverage includes create/edit navigation, review text escaping, source-group requirements and serialization, validation in hidden steps, request locks and failure recovery, modal reset, keyboard navigation, and mobile navigation width. The separate PHPUnit save-controller tests exercise the server-side persistence contract.

Optional screenshots: set `AIPS_WIZARD_SCREENSHOTS=1` and create `.artifacts/` before running. Remove the two generated `author-wizard-*.png` files after inspection.
