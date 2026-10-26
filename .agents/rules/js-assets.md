# JavaScript rules: `ai-post-scheduler/assets/js/**`

Read `wordpress-core.md` too. Rationale and examples: `docs/DEVELOPMENT_GUIDELINES.md`. Skip `assets/js/vendor/` (third-party, do not edit).

## Module shape (copy `admin.js` / `utilities.js`)
```js
(function($) {
  'use strict';
  window.AIPS = window.AIPS || {};
  var AIPS = window.AIPS;

  AIPS.ModuleName = {
    init: function() { this.bindEvents(); },
    bindEvents: function() { $(document).on('click', '.aips-x', this.onClick.bind(this)); },
    /** @param {Event} e */
    onClick: function(e) { ... }
  };

  $(document).ready(function() { AIPS.ModuleName.init(); });
})(jQuery);
```
- Bind to named methods, never inline callbacks. Prefer delegated `$(document).on(...)` for dynamic UI.
- JSDoc every method; add `@param {Event} e` on handlers.

## Required helpers
- HTML: `AIPS.Templates.render(id, data)` (auto-escaped) or `renderRaw` (trusted HTML only). Markup lives in `<script type="text/html" id="tmpl-...">` in the admin template. No string-concatenated HTML.
- Feedback: `AIPS.Utilities.showToast(message, type, opts)`. Never `alert()`.
- Confirmation: `AIPS.Utilities.confirm(message, heading, buttons)`. Never `confirm()`.
- Refresh: re-fetch via AJAX and re-render the changed region. Never `location.reload()`.
- Dates: `assets/js/datetime.js`.
- Existing `alert()` / `location.reload()` calls (for example in `admin-db.js`, `admin-content-auditor.js`) are legacy; fix them when you touch that code, do not copy them.

## AJAX and data
- Use `ajaxurl` / the localized `aipsAjax` nonce and the action names registered in `AIPS_Ajax_Registry`. Handle `AIPS_Ajax_Response` shapes (`success`, `data`, error `message`).
- User-facing strings come from localized `aips*L10n` objects (set in `AIPS_Admin_Assets`), not hard-coded literals.
- Sanitize/validate user-controlled values before sending. jQuery sends booleans as strings; the server parses them.

## Scope
- New script: register/enqueue it in `AIPS_Admin_Assets` for its screen only. No new build tooling.
- Keep changes small; preserve DOM hooks (`aips-*` classes, IDs, `data-*`) used by templates.
- Add or update tests/docs where behavior changes.
