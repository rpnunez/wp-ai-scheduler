## 2024-06-05 - Standardize Admin Modals HTML Structure
**Area:** Admin Templates (`templates/admin/*.php`)
**Status:** opened PR
**PR:** 🎨 Palette: Standardize Admin Modals Structure and Button Classes
**Learning:** Inconsistent HTML structure in modals (missing headers, unstructured buttons) degrades both visual consistency and codebase maintainability.
**Action:** Always ensure modals follow the `.aips-modal-header`, `.aips-modal-body`, `.aips-modal-footer` structure and use standardized `.aips-btn` classes for primary/secondary actions. Avoid redundant ID mapping when a standard class (`.aips-modal-title`, `.aips-modal-content-body`) provides sufficient hooks for dynamic JS updates.
## 2024-06-16 - Add aria-hidden to decorative Dashicons
**Area:** Admin Templates
**Status:** opened PR
**PR:** 🎨 Palette: Add aria-hidden="true" to decorative Dashicons in admin templates
**Learning:** Decorative Dashicons (like `dashicons-no-alt` in close buttons, or standard icons in tables/buttons) should have `aria-hidden="true"` to prevent screen readers from announcing them unnecessarily or confusingly.
**Action:** When adding Dashicons, always evaluate if they convey unique information or are just decorative. If decorative or redundant to nearby text, include `aria-hidden="true"`.
