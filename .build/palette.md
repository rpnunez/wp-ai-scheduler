## 2024-05-23 - Accessibility Patterns for WordPress Admin
**Learning:** Standard WordPress Admin UI patterns (like modals and empty states with Dashicons) often lack default ARIA attributes. Specifically, modal close buttons (`&times;`) are frequently missing `aria-label`, and decorative Dashicons are missing `aria-hidden="true"`.
**Action:** When working on WP Admin interfaces, always audit modal close buttons and decorative icons for these specific attributes. Use `esc_attr_e('Close modal', 'text-domain')` for consistency.
## 2026-05-30 - Added loading indicator when editing authors
**Learning:** AJAX-driven edit modals within this repo can sometimes appear with empty fields while data is still loading, creating a confusing user experience. The standard pattern to solve this is to use the existing WordPress admin `.spinner` element within a loader container.
**Action:** For future modal-based edit features, ensure a loader container is added alongside the form, and use JavaScript to hide the form and show the loader during the AJAX fetch phase. Only reveal the form when the data has successfully populated.
## 2024-05-30 - Add aria-hidden to Dashboard Dashicons
**Area:** Dashboard empty states (templates/admin/dashboard.php)
**Status:** opened PR
**PR:** 🎨 Palette: Add aria-hidden to decorative Dashicons in dashboard
**Learning:** Decorative icons in empty states must be explicitly hidden from screen readers.
**Action:** When adding empty state Dashicons, include aria-hidden="true".
## 2024-05-30 - Add aria-hidden to Generated Posts Dashicons
**Area:** Generated Posts tab (templates/admin/tab-generated-posts.php)
**Status:** opened PR
**PR:** 🎨 Palette: Add aria-hidden to decorative Dashicons in Generated Posts
**Learning:** Decorative icons in history interfaces must be explicitly hidden from screen readers.
**Action:** When adding or auditing generated posts Dashicons, include aria-hidden="true".
## 2026-08-19 - Add aria-labels to Author table checkboxes
**Area:** Authors templates (templates/admin/authors.php)
**Status:** opened PR
**PR:** 🎨 Palette: Add accessible labels to author table checkboxes
**Learning:** Checkboxes in data tables (like select all, or row selection) often lack accessible labels when placed in Handlebars JS templates or standard WP tables without explicitly rendering a label.
**Action:** Always add `aria-label` to table selection checkboxes in WP Admin interfaces.
