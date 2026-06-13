## 2024-06-05 - Standardize Admin Modals HTML Structure
**Area:** Admin Templates (`templates/admin/*.php`)
**Status:** opened PR
**PR:** 🎨 Palette: Standardize Admin Modals Structure and Button Classes
**Learning:** Inconsistent HTML structure in modals (missing headers, unstructured buttons) degrades both visual consistency and codebase maintainability.
**Action:** Always ensure modals follow the `.aips-modal-header`, `.aips-modal-body`, `.aips-modal-footer` structure and use standardized `.aips-btn` classes for primary/secondary actions. Avoid redundant ID mapping when a standard class (`.aips-modal-title`, `.aips-modal-content-body`) provides sufficient hooks for dynamic JS updates.

## 2024-06-13 - Add `type="button"` to JS-triggered admin buttons
**Area:** Admin Templates (`templates/admin/*.php`)
**Status:** opened PR
**PR:** 🎨 Palette: Add type="button" to JS-triggered admin buttons
**Learning:** Native `<button>` tags without a type default to `type="submit"`. In AJAX-heavy admin interfaces, missing `type="button"` can cause unintended form submissions or page reloads when the button is used purely as a JavaScript trigger (like closing a modal, toggling a tab, or initiating an AJAX action).
**Action:** Always specify `type="button"` on non-submit action buttons to ensure semantic HTML, prevent accidental form submissions, and guarantee stable event delegation.
