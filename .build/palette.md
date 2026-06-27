## 2024-05-23 - Accessibility Patterns for WordPress Admin
**Learning:** Standard WordPress Admin UI patterns (like modals and empty states with Dashicons) often lack default ARIA attributes. Specifically, modal close buttons (`&times;`) are frequently missing `aria-label`, and decorative Dashicons are missing `aria-hidden="true"`.
**Action:** When working on WP Admin interfaces, always audit modal close buttons and decorative icons for these specific attributes. Use `esc_attr_e('Close modal', 'text-domain')` for consistency.
## 2026-05-30 - Added loading indicator when editing authors
**Learning:** AJAX-driven edit modals within this repo can sometimes appear with empty fields while data is still loading, creating a confusing user experience. The standard pattern to solve this is to use the existing WordPress admin `.spinner` element within a loader container.
**Action:** For future modal-based edit features, ensure a loader container is added alongside the form, and use JavaScript to hide the form and show the loader during the AJAX fetch phase. Only reveal the form when the data has successfully populated.
## 2026-06-03 - Icon Button Accessibility
**Area:** Admin Templates (`templates/admin/taxonomy.php`, `templates/admin/campaign-wizard.php`)
**Status:** opened PR
**PR:** 🎨 Palette: [UX improvement] Add aria-label to icon-only remove buttons
**Learning:** Icon-only buttons (like those containing just `&times;` or a Dashicon trash icon) must have an explicit `aria-label` to provide an accessible name for screen reader users. The WCAG 2.5.3 (Label in Name) rule does not apply since these buttons do not have visible text.
**Action:** Always add `aria-label="<?php esc_attr_e('Remove', 'ai-post-scheduler'); ?>"` to icon-only removal buttons.
