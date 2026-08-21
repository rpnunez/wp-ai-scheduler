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
## 2024-08-17 - Add aria-hidden to decorative dashicons
**Area:** Article Structures Template (`templates/admin/structures.php`)
**Status:** opened PR
**PR:** 🎨 Palette: Add aria-hidden to decorative icons in structures template
**Learning:** Decorative icons (like dashicons) inside interactive elements should be hidden from screen readers using `aria-hidden="true"` to prevent redundant or confusing announcements, especially when adjacent text or `.screen-reader-text` provides the accessible name.
**Action:** Always add `aria-hidden="true"` to decorative `<span class="dashicons...">` elements used for purely visual purposes.
