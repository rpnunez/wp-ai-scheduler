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
## 2024-08-21 - Add aria-label to toggle checkboxes
**Area:** Affiliate Links (`templates/admin/affiliate-links.php`)
**Status:** opened PR
**PR:** 🎨 Palette: Add aria-label to toggle checkboxes in affiliate links
**Learning:** Toggle slider checkboxes in admin UIs often lack native labels on the `<input>` element itself.
**Action:** Always ensure toggle `<input type="checkbox">` elements have an explicit `aria-label` or are wrapped with a text-inclusive `<label>` to remain accessible to screen readers.
## 2024-08-25 - Add aria-label to cache monitor checkboxes
**Area:** Cache Monitor (`templates/admin/cache-monitor.php`, `assets/js/cache-monitor.js`)
**Status:** opened PR
**PR:** 🎨 Palette: Add aria-label to cache monitor checkboxes
**Learning:** Data tables and lists sometimes contain generated or standalone checkboxes for bulk actions without `<label>` elements or ARIA labels.
**Action:** When adding checkboxes for bulk actions, ensure both the header select-all and individual row checkboxes have an explicit localized `aria-label`.
