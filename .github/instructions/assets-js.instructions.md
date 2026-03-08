---
applyTo: "assets/js/*.js,ai-post-scheduler/assets/js/*.js"
---

Use this file for JavaScript changes in `assets/js/*.js`.

Follow the `admin.js` module pattern for all plugin JavaScript files at a high level.

- Wrap files in an IIFE that receives jQuery: `(function($) { ... })(jQuery);`.
- Enable strict mode near the top: `'use strict';`.
- Initialize and reuse a shared global namespace object: `window.AIPS = window.AIPS || {};` then `var AIPS = window.AIPS;`.
- Define behavior as named methods on `AIPS` via `Object.assign(AIPS, { ... })`.
- Include an `init()` method as the main bootstrap entry point.
- Include a `bindEvents()` method that registers UI event listeners.
- In `bindEvents()`, register listeners to named methods (for example `this.saveTemplate`) and do not use inline callbacks.
- Prefer delegated listeners on `document` (for example `$(document).on(...)`) for dynamic admin UI elements.
- Keep handler logic in dedicated methods rather than embedding behavior inside event registration lines.
- Add DocBlocks/JSDoc-style comments for all methods with a clear summary; include `@param {Event} e` for event handlers.
- Add a single jQuery `$(document).ready(function() { ... });` block at the bottom that calls `AIPS.init()` and any minimal startup-only logic.

- Keep code compatible with existing project JavaScript patterns and WordPress admin usage.
- Prefer small, focused changes; avoid broad refactors unless requested.
- Preserve existing public behavior and DOM hooks used by admin templates.
- Sanitize and validate user-controlled values before sending them to AJAX endpoints.
- Use WordPress-friendly conventions for AJAX (`ajaxurl`, nonces, action names) when applicable.
- Avoid introducing new build tooling for simple script updates.
- Keep browser compatibility in mind for WordPress admin environments.
- If behavior changes, update or add tests/documentation where applicable.
