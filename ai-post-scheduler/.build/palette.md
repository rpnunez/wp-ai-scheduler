## 2024-06-05 - Standardize Admin Modals HTML Structure
**Area:** Admin Templates (`templates/admin/*.php`)
**Status:** opened PR
**PR:** 🎨 Palette: Standardize Admin Modals Structure and Button Classes
**Learning:** Inconsistent HTML structure in modals (missing headers, unstructured buttons) degrades both visual consistency and codebase maintainability.
**Action:** Always ensure modals follow the `.aips-modal-header`, `.aips-modal-body`, `.aips-modal-footer` structure and use standardized `.aips-btn` classes for primary/secondary actions. Avoid redundant ID mapping when a standard class (`.aips-modal-title`, `.aips-modal-content-body`) provides sufficient hooks for dynamic JS updates.
## 2024-06-26 - Taxonomy Screen Accessibility
**Area:** Admin Templates (`templates/admin/taxonomy.php`)
**Status:** opened PR
**PR:** 🎨 Palette: Taxonomy Admin Template Accessibility Improvements
**Learning:** Decorative icons and 'x' remove buttons in JS templates often lack appropriate ARIA attributes.
**Action:** Add `aria-hidden="true"` to purely decorative `dashicons` spans, and ensure JS template buttons (like `&times;` remove actions) have an `aria-label` attribute (with proper translation via `esc_attr_e`) for screen reader support.
