# Article Structure Admin UI Implementation Plan

## Goals & Scope
- Deliver a minimal-yet-complete Article Structures admin page that loads existing data and supports create/update/delete, default selection, and active toggling via AJAX.
- Keep changes small by reusing existing repositories, AJAX endpoints (`aips_*structure*` actions), and modal markup already present in `templates/admin/structures.php`.
- Ensure admin assets (enqueue + localization) provide structure-specific strings/config without breaking existing admin pages.

## Current State Summary
- **Template**: `templates/admin/structures.php` renders list + modal; columns show Active/Default as plain text; actions only Edit/Delete buttons.
- **Controller**: `AIPS_Structures_Controller` already exposes AJAX actions: get list/detail, save, delete, set default, toggle active.
- **JS**: `assets/js/admin.js` contains temporary structure handlers (with TODO comments) that rely on reloads and duplicate modal close logic; no dedicated structure JS or styling.
- **Assets**: Admin enqueue localizes `aipsAdminL10n`/`aipsAjax` generically; no structure-specific script or strings.

## Assumptions & Constraints
- Preserve existing architecture (repository + controller + nonce validation) and WordPress coding standards.
- Avoid large refactors; keep admin.js changes minimal (ideally move handlers into a dedicated file and prevent duplicate bindings).
- No new endpoints or schema changes; UI should consume existing AJAX responses.

## Implementation Steps
1. **Template wiring & states**
   - Confirm admin page loader passes `$structures` and `$sections` to the view; keep markup stable.
   - Add minimal UI affordances for Active/Default (badges or toggles) and include data attributes (`data-structure-id`, current flags) to support JS updates without reloads.
   - Ensure empty state CTA still opens modal.

2. **Dedicated JS module (`assets/js/admin-structures.js`)**
   - Encapsulate structure behaviors: open/close modal, fetch detail (`aips_get_structure`), save (`aips_save_structure`), delete (`aips_delete_structure`), set default (`aips_set_structure_default`), toggle active (`aips_toggle_structure_active`).
   - Handle UI states (disable buttons/spinners, update row badges/toggles in place, enforce single default toggle).
   - Gracefully surface errors using localized strings and fallback alerts.
   - Initialize on document ready; ensure existing admin.js handlers are removed/guarded to avoid double-binding.

3. **Admin enqueue & localization**
   - Enqueue the new script only on the Structures admin screen; set dependencies (`jquery`, existing admin base if needed).
   - Localize structure-specific strings/config (labels, confirmations, error messages, nonce/ajax URLs) into `aipsStructuresL10n` or extend existing localization safely.
   - Keep `aipsAjax` nonce/URL intact for other modules.

4. **Styling touch-ups**
   - Add minimal CSS for badges/toggles, modal width, and multiselect sizing, reusing existing admin styles where possible.
   - Ensure accessibility (focusable close, keyboard escape) leverages existing modal patterns.

5. **Testing & Acceptance**
   - Manual flows on Article Structures page:
     - Page loads with existing structures and sections populated.
     - Add structure (required fields enforced) → row appears with correct active/default flags.
     - Edit structure loads data (sections + prompt template) and saves updates.
     - Delete structure removes row and shows confirmation.
     - Toggle Active updates server and UI without full reload; errors revert UI.
     - Set Default ensures only one default at a time; UI reflects change immediately.
     - Empty state CTA opens modal; modal close works via X, Cancel, overlay, Escape.
   - Regression: other admin pages still function (templates, schedules, voices) with admin.js unaffected.

6. **Risks & Mitigations**
   - **Double event binding**: Guard removal of old handlers or scope new module to avoid duplicate AJAX calls.
   - **Default enforcement**: Ensure UI enforces single default by resetting previous default in DOM after success.
   - **Translation drift**: Add localized strings rather than hard-coded English to keep WP i18n compliance.
   - **Legacy browsers**: Keep jQuery-based DOM operations consistent with rest of admin for compatibility.
