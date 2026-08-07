## 2024-05-23 - Accessibility Patterns for WordPress Admin
**Learning:** Standard WordPress Admin UI patterns (like modals and empty states with Dashicons) often lack default ARIA attributes. Specifically, modal close buttons (`&times;`) are frequently missing `aria-label`, and decorative Dashicons are missing `aria-hidden="true"`.
**Action:** When working on WP Admin interfaces, always audit modal close buttons and decorative icons for these specific attributes. Use `esc_attr_e('Close modal', 'text-domain')` for consistency.
## 2026-05-30 - Added loading indicator when editing authors
**Learning:** AJAX-driven edit modals within this repo can sometimes appear with empty fields while data is still loading, creating a confusing user experience. The standard pattern to solve this is to use the existing WordPress admin `.spinner` element within a loader container.
**Action:** For future modal-based edit features, ensure a loader container is added alongside the form, and use JavaScript to hide the form and show the loader during the AJAX fetch phase. Only reveal the form when the data has successfully populated.

## 2024-06-05 - Standardize Admin Modals HTML Structure
**Area:** Admin Templates (`templates/admin/*.php`)
**Status:** opened PR
**PR:** 🎨 Palette: Standardize Admin Modals Structure and Button Classes
**Learning:** Inconsistent HTML structure in modals (missing headers, unstructured buttons) degrades both visual consistency and codebase maintainability.
**Action:** Always ensure modals follow the `.aips-modal-header`, `.aips-modal-body`, `.aips-modal-footer` structure and use standardized `.aips-btn` classes for primary/secondary actions. Avoid redundant ID mapping when a standard class (`.aips-modal-title`, `.aips-modal-content-body`) provides sufficient hooks for dynamic JS updates.
