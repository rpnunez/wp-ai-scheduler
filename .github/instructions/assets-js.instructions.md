---
applyTo: "assets/js/**/*.js,ai-post-scheduler/assets/js/**/*.js"
---

Use this file for JavaScript changes in `assets/js/*.js`.

Shared/foundational modules used across pages (`core.js`, `core-modal.js`, `core-table.js`, `core-bulk.js`, `core-ui.js`, `utilities.js`, `templates.js`, `datetime.js`) live in `assets/js/core/`. Everything else — a specific admin page's script — stays directly in `assets/js/`.

Follow the `admin.js` module pattern for all plugin JavaScript files at a high level.

- Wrap files in an IIFE that receives jQuery: `(function($) { ... })(jQuery);`.
- Enable strict mode near the top: `'use strict';`.
- Initialize and reuse a shared global namespace object: `window.AIPS = window.AIPS || {};` then `var AIPS = window.AIPS;`.
- Define behavior as a named sub-module on `AIPS` using the module's name (for example, `AIPS.Utilities = { ... }` for `utilities.js`, `AIPS.SystemStatus = { ... }` for `system-status.js`). Assign all methods directly on that sub-module object.
- Name new files after the page/feature they cover, without an `admin-` prefix (for example `system-status.js`, not `admin-system-status.js`) — every file in this directory is already admin-only, so the prefix is redundant. `admin.js` (the shared bootstrap) and `admin-bar.js` (wraps WordPress's own "admin bar" feature) are the only exceptions.
- Include an `init()` method on the sub-module as the main bootstrap entry point (for example `AIPS.Utilities.init()`).
- Include a `bindEvents()` method on the sub-module that registers UI event listeners (for example `AIPS.Utilities.bindEvents()`).
- In `bindEvents()`, register listeners to named methods on the sub-module (for example `this.saveTemplate`) and do not use inline callbacks.
- Prefer delegated listeners on `document` (for example `$(document).on(...)`) for dynamic admin UI elements.
- Keep handler logic in dedicated methods rather than embedding behavior inside event registration lines.
- Add DocBlocks/JSDoc-style comments for all methods with a clear summary; include `@param {Event} e` for event handlers.
- Add a single jQuery `$(document).ready(function() { ... });` block at the bottom that calls the sub-module's `init()` (for example `AIPS.Utilities.init()`) and any minimal startup-only logic.

- Keep code compatible with existing project JavaScript patterns and WordPress admin usage.
- Prefer small, focused changes; avoid broad refactors unless requested.
- Preserve existing public behavior and DOM hooks used by admin templates.
- Sanitize and validate user-controlled values before sending them to AJAX endpoints.
- Use WordPress-friendly conventions for AJAX (`ajaxurl`, nonces, action names) when applicable.
- Avoid introducing new build tooling for simple script updates.
- Keep browser compatibility in mind for WordPress admin environments.
- If behavior changes, update or add tests/documentation where applicable.

> **Note:** Only read `docs/DEVELOPMENT_GUIDELINES.md` if you have not already read it in this session.
> **Also read:** [`docs/DEVELOPMENT_GUIDELINES.md`](../../docs/DEVELOPMENT_GUIDELINES.md) for project-specific coding and architectural guidelines (HTML templating with `AIPS.Templates`, versioning rules, SQL ownership, and settings registration).
