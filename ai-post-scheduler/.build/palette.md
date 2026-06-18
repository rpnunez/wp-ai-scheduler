## 2024-06-05 - Standardize Admin Modals HTML Structure
**Area:** Admin Templates (`templates/admin/*.php`)
**Status:** opened PR
**PR:** 🎨 Palette: Standardize Admin Modals Structure and Button Classes
**Learning:** Inconsistent HTML structure in modals (missing headers, unstructured buttons) degrades both visual consistency and codebase maintainability.
**Action:** Always ensure modals follow the `.aips-modal-header`, `.aips-modal-body`, `.aips-modal-footer` structure and use standardized `.aips-btn` classes for primary/secondary actions. Avoid redundant ID mapping when a standard class (`.aips-modal-title`, `.aips-modal-content-body`) provides sufficient hooks for dynamic JS updates.

## 2024-06-18 - Accessibility: Decorative Icons and Close Buttons
**Area:** Admin Templates (`templates/admin/*.php`)
**Status:** opened PR
**PR:** 🎨 Palette: Improve accessibility of decorative icons and close buttons
**Learning:** Decorative icons (dashicons) frequently lack `aria-hidden="true"`, and generic close buttons (`&times;`) frequently lack `aria-label`. Both reduce screen reader accessibility.
**Action:** When adding dashicons to UI elements that also contain visible text, add `aria-hidden="true"`. Always provide an `aria-label` for symbol-only buttons like `&times;`.
