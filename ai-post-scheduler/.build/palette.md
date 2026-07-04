## 2024-06-05 - Standardize Admin Modals HTML Structure
**Area:** Admin Templates (`templates/admin/*.php`)
**Status:** opened PR
**PR:** 🎨 Palette: Standardize Admin Modals Structure and Button Classes
**Learning:** Inconsistent HTML structure in modals (missing headers, unstructured buttons) degrades both visual consistency and codebase maintainability.
**Action:** Always ensure modals follow the `.aips-modal-header`, `.aips-modal-body`, `.aips-modal-footer` structure and use standardized `.aips-btn` classes for primary/secondary actions. Avoid redundant ID mapping when a standard class (`.aips-modal-title`, `.aips-modal-content-body`) provides sufficient hooks for dynamic JS updates.
## 2024-07-04 - Accessibility Fix for Taxonomy Remove Post Button
**Area:** Taxonomy Template (`templates/admin/taxonomy.php`)
**Status:** opened PR
**PR:** 🎨 Palette: Add aria-label to remove post button in taxonomy
**Learning:** Missing aria-labels on icon-only buttons (like `&times;`) degrades screen reader accessibility.
**Action:** Always ensure icon-only buttons have an `aria-label` attribute describing their function for assistive technologies.
