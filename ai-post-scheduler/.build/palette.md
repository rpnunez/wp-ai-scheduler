## 2024-06-05 - Standardize Admin Modals HTML Structure
**Area:** Admin Templates (`templates/admin/*.php`)
**Status:** opened PR
**PR:** 🎨 Palette: Standardize Admin Modals Structure and Button Classes
**Learning:** Inconsistent HTML structure in modals (missing headers, unstructured buttons) degrades both visual consistency and codebase maintainability.
**Action:** Always ensure modals follow the `.aips-modal-header`, `.aips-modal-body`, `.aips-modal-footer` structure and use standardized `.aips-btn` classes for primary/secondary actions. Avoid redundant ID mapping when a standard class (`.aips-modal-title`, `.aips-modal-content-body`) provides sufficient hooks for dynamic JS updates.

## 2024-06-14 - Add aria-hidden to decorative Dashicons
**Area:** Admin Templates (`templates/admin/*.php`)
**Status:** opened PR
**PR:** 🎨 Palette: Add aria-hidden to decorative Dashicons
**Learning:** Decorative dashicons used alongside text labels should have `aria-hidden="true"` to prevent screen readers from reading them out redundantly or confusingly. We should also ensure icon-only buttons have an `aria-label`.
**Action:** Add `aria-hidden="true"` to all decorative `<span class="dashicons...">` elements in admin templates. Ensure `&times;` close buttons have `aria-label`.
