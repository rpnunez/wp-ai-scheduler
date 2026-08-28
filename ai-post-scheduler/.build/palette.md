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
## 2024-11-22 - Data Table Checkboxes Accessibility
**Area:** Authors and Author Topics Admin Templates (`templates/admin/authors.php`, `templates/admin/author-topics.php`)
**Status:** opened PR
**PR:** 🎨 Palette: Add aria-label to data table checkboxes in Authors templates
**Learning:** Checkboxes in JS-rendered templates (like Handlebars `{{}}`) require explicit `aria-label` attributes to be accessible to screen readers, especially when they lack a `<label>` element.
**Action:** Always ensure that dynamically generated checkboxes in data tables have an `aria-label` attached for assistive technologies.
## 2024-11-23 - Decorative Dashicon Accessibility
**Area:** History Template (`templates/admin/history.php`)
**Status:** opened PR
**PR:** 🎨 Palette: Add aria-hidden to decorative Dashicons in History template
**Learning:** Screen readers announce decorative Dashicons unnecessarily when they are grouped with visible descriptive text (e.g. inside buttons), confusing users.
**Action:** Always add `aria-hidden="true"` to decorative Dashicons included in buttons or elements that already have descriptive text or are otherwise purely aesthetic.
## 2026-08-26 - Add aria-label to taxonomy table row checkbox
**Area:** Taxonomy page (`templates/admin/taxonomy.php`)
**Status:** opened PR
**PR:** 🎨 Palette: Add aria-label to taxonomy row checkbox
**Learning:** Row checkboxes inside data tables must have explicit `aria-label` attributes to define what they are selecting. The `aips-tmpl-taxonomy-row` JS template lacked this.
**Action:** When creating JS template rows containing check columns, always add `aria-label` mapped to the relevant select action.
## 2026-08-28 - Add aria-label to voice search input
**Area:** Templates wizard (`templates/admin/templates.php`)
**Status:** opened PR
**PR:** 🎨 Palette: Add accessible label to voice search input
**Learning:** Text inputs inside custom components (like the voice selector dropdown) require explicit `<label>` tags for accessibility.
**Action:** When building custom search inputs, always add an explicit `<label>` tag mapped to the input ID, using the `screen-reader-text` class if it should be visually hidden.
