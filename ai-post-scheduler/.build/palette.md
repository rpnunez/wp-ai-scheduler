## 2024-06-05 - Standardize Admin Modals HTML Structure
**Area:** Admin Templates (`templates/admin/*.php`)
**Status:** opened PR
**PR:** 🎨 Palette: Standardize Admin Modals Structure and Button Classes
**Learning:** Inconsistent HTML structure in modals (missing headers, unstructured buttons) degrades both visual consistency and codebase maintainability.
**Action:** Always ensure modals follow the `.aips-modal-header`, `.aips-modal-body`, `.aips-modal-footer` structure and use standardized `.aips-btn` classes for primary/secondary actions. Avoid redundant ID mapping when a standard class (`.aips-modal-title`, `.aips-modal-content-body`) provides sufficient hooks for dynamic JS updates.
## 2026-06-05 - Add explicit type="button" to UI buttons
**Area:** Admin Templates (`templates/admin/*.php`)
**Status:** opened PR
**PR:** 🎨 Palette: Add explicit type="button" to UI buttons to prevent unintended form submissions
**Learning:** Missing `type` attributes on `<button>` elements default to `type="submit"`, causing unintended form submissions when users interact with UI buttons (e.g., toggles, clear buttons) within forms.
**Action:** Always add `type="button"` to `<button>` elements that are not intended to submit a form.
