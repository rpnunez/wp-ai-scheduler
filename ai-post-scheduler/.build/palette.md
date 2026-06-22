## 2024-06-05 - Standardize Admin Modals HTML Structure
**Area:** Admin Templates (`templates/admin/*.php`)
**Status:** opened PR
**PR:** 🎨 Palette: Standardize Admin Modals Structure and Button Classes
**Learning:** Inconsistent HTML structure in modals (missing headers, unstructured buttons) degrades both visual consistency and codebase maintainability.
**Action:** Always ensure modals follow the `.aips-modal-header`, `.aips-modal-body`, `.aips-modal-footer` structure and use standardized `.aips-btn` classes for primary/secondary actions. Avoid redundant ID mapping when a standard class (`.aips-modal-title`, `.aips-modal-content-body`) provides sufficient hooks for dynamic JS updates.

## 2024-06-22 - Add aria-hidden to decorative Dashicons
**Area:** Admin Templates (`templates/admin/*.php`)
**Status:** opened PR
**PR:** 🎨 Palette: Add aria-hidden to decorative Dashicons in admin templates
**Learning:** Decorative font icons like Dashicons can confuse screen readers if not properly hidden. Adding `aria-hidden="true"` prevents this issue.
**Action:** Always add `aria-hidden="true"` to `<span class="dashicons...">` elements when they are purely visual or accompanied by accessible label text.
