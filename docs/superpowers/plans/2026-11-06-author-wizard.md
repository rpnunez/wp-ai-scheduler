# Author Wizard Implementation Plan

> Execute the approved plan in this chat using the executing-plans workflow.

**Goal:** Reduce the overwhelming Author form through five guided sections, setting explanations, and review before creation. Existing authors support direct section navigation and saving from any section.

**Architecture:** Retain the existing author form, field names, AJAX action, and persistence. Reuse wizard styles with Author-specific navigation and validation because the shared wizard logic assumes template/schedule fields.

**Tech Stack:** WordPress PHP, jQuery, existing admin CSS tokens. No new production dependencies.

**Spec:** User-approved plan in this chat, 2026-11-06; the behavior is recorded below.

## Constraints and design

- Identity: name, niche, description.
- Writing & Audience: tone, style, expertise, audience, structure, length, language.
- Topics & Sources: keywords, details, goals, exclusions, source groups, affiliate links, approval rules.
- Scheduling: frequencies, quantities, per-topic limits, active status.
- Review: text-only current values with section navigation.
- New authors save only after review. Editing can save from every section.
- Validate enabled fields across hidden panels before saving; focus the first invalid field.
- Preserve conditional fields, source group selections, AJAX nonce and capability checks, and defaults. No schema or generation changes.
- Preserve unrelated `.phpstan-cache/` in the existing checkout; branch from the verified origin/main.

## Tasks

- [x] Verify base and create `codex/author-wizard`.
- [x] Write browser regression against the real modal and author script; observe missing-step failure.
- [x] Reorganize `templates/admin/authors.php`, add help text, and implement navigation/review in `assets/js/authors.js`.
- [x] Add scoped styles in `assets/css/authors.css`, translations in `includes/class-aips-admin-assets.php`, and root changelog entry.
- [x] Run PHP/JS syntax checks, headless browser regression, and `make test-session ARGS="tests/AIPS_Authors_Controller_Save_Test.php"`.
- [ ] Commit, push, create PR, review the full branch, fix findings, update PR and verify CI.

## Review focus

Hidden invalid fields must receive focus in their own panel; creating after editing must clear mode and values; disabled policy fields must stay omitted; review must render user text safely; shared wizard handlers must not intercept Author navigation.

## Execution notes

- Main and origin/main matched `c69ff5d9e` after the user's pull.
- No tracked user changes existed. The dedicated branch safely preserves the unrelated cache in place.
- Repository documentation references a localization class and plugin changelog absent in this checkout; use the existing admin-assets translation payload and root CHANGELOG.md.
- Docker stack was stopped before testing. Test-session owns startup/shutdown.
- Targeted PHPUnit: 5 tests, 23 assertions passed. Corrected the existing test's stale MySQL-string bounds to match the controller's existing integer timestamps using AIPS_DateTime.
- Browser regression: passed against real modal markup, author script, shared Utilities locking, and styles. Covers create/edit navigation, review escaping, source validation and preservation, hidden invalid controls, duplicate submits, failed-request unlock, reset, keyboard navigation, and mobile progress layout. AJAX is mocked; this is not an authenticated WordPress end-to-end test.
- Independent review found cancelled submissions bubbling to the global async lock. Reproduced with real Utilities, then fixed by owning submit propagation and checking in-flight form state. Localization review passed; moved markup indentation normalized.
