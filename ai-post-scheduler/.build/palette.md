## 2024-06-05 - Standardize Admin Modals HTML Structure
**Area:** Admin Templates (`templates/admin/*.php`)
**Status:** opened PR
**PR:** 🎨 Palette: Standardize Admin Modals Structure and Button Classes
**Learning:** Inconsistent HTML structure in modals (missing headers, unstructured buttons) degrades both visual consistency and codebase maintainability.
**Action:** Always ensure modals follow the `.aips-modal-header`, `.aips-modal-body`, `.aips-modal-footer` structure and use standardized `.aips-btn` classes for primary/secondary actions. Avoid redundant ID mapping when a standard class (`.aips-modal-title`, `.aips-modal-content-body`) provides sufficient hooks for dynamic JS updates.

## 2024-06-24 - Icons and Modals Accessibility
**Area:** Admin Templates
**Status:** opened PR
**PR:** 🎨 Palette: Improve Accessibility of Icons and Modal Closes
**Learning:** Missing aria labels and aria-hidden for icons decrease accessibility.
**Action:** Always add aria-hidden="true" to decorative dashicons and aria-label to modal close buttons (&times;).
