---
name: aips-admin-ui-js
description: Use when changing admin templates or JavaScript in the AI Post Scheduler plugin (ai-post-scheduler/) — anything under templates/admin/*.php or assets/js/*.js, admin markup, toasts, modals, or confirm dialogs.
---

# Admin UI / JS workflow

## Required workflow

1. **Follow the JS module pattern.** Every file in `assets/js/` follows the same
   shape:
   ```js
   (function($) {
     'use strict';
     window.AIPS = window.AIPS || {};
     var AIPS = window.AIPS;

     AIPS.ModuleName = {
       init() { this.bindEvents(); },
       bindEvents() { $(document).on('event', '.selector', this.handler.bind(this)); },
       handler(e) { ... }
     };

     $(document).ready(function() { AIPS.ModuleName.init(); });
   })(jQuery);
   ```
2. **Generate HTML only through the template helper.** Use `AIPS.Templates.render(id, data)`
   (auto-escaped) or `AIPS.Templates.renderRaw(id, data)` (trusted HTML only). Never
   build HTML via string concatenation.
3. **Use the shared UI primitives, not browser dialogs.** `AIPS.Utilities.showToast(message, type)`
   instead of `alert()`; `AIPS.Utilities.confirm(message, heading, buttons)` instead of
   `confirm()`.
4. **Refresh via re-fetch + re-render, not `location.reload()`.** After a mutating
   AJAX call, re-fetch the affected data and re-render with `AIPS.Templates` rather
   than reloading the page.
5. **Follow the template layout structure.** Admin pages nest
   `div.wrap.aips-wrap` → `div.aips-page-container` → `div.aips-page-header` /
   `div.aips-content-panel`. Buttons use `aips-btn` with variants
   (`aips-btn-primary`, `aips-btn-danger`, ...). Tables use `table.aips-table`.
   Modals nest `.aips-modal` → `.aips-modal-content` → `.aips-modal-header` /
   `.aips-modal-body` / `.aips-modal-footer`.
6. **Templates stay presentation-only.** `templates/admin/*.php` must not contain
   SQL or heavy business logic — pull data from a controller/service before
   rendering.

## Guardrails

- An admin UI change almost always warrants the `admin-ui` + `needs-browser-test`
  PR labels — see `aips-pr-prep`. Actually open the page in a browser (or via the
  Docker dev environment at `http://localhost:8080/wp-admin`) to confirm the change
  before calling it done — type checks don't catch broken markup/JS wiring.

## Reference files

- `ai-post-scheduler/assets/js/` (any existing module as a pattern reference)
- `ai-post-scheduler/templates/admin/`
