## 2024-06-05 - Standardize Admin Modals HTML Structure
**Area:** Admin Templates (`templates/admin/*.php`)
**Status:** opened PR
**PR:** 🎨 Palette: Standardize Admin Modals Structure and Button Classes
**Learning:** Inconsistent HTML structure in modals (missing headers, unstructured buttons) degrades both visual consistency and codebase maintainability.
**Action:** Always ensure modals follow the `.aips-modal-header`, `.aips-modal-body`, `.aips-modal-footer` structure and use standardized `.aips-btn` classes for primary/secondary actions. Avoid redundant ID mapping when a standard class (`.aips-modal-title`, `.aips-modal-content-body`) provides sufficient hooks for dynamic JS updates.

## 2024-07-02 - Add Aria Labels to Select All Checkboxes
**Area:** Admin Templates (`templates/admin/authors.php`, `templates/admin/author-topics.php`)
**Status:** opened PR
**PR:** 🎨 Palette: Add aria-labels to Select All checkboxes in tables
**Learning:** When adding new localized labels to JavaScript templates (e.g., for `AIPS.Templates.renderRaw`), always provide a fallback string (e.g., `AIPS.Templates.escape(aipsAuthorsL10n.newKey || 'Fallback Text')`) to ensure the UI remains functional and accessible even if the backend `wp_localize_script` array has not been updated yet.
**Action:** Consistently review input elements and add `aria-label` attributes to checkboxes or inputs that lack visible text, especially "Select All" controls in tables.
