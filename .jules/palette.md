## 2024-05-23 - Accessibility Patterns for WordPress Admin
**Learning:** Standard WordPress Admin UI patterns (like modals and empty states with Dashicons) often lack default ARIA attributes. Specifically, modal close buttons (`&times;`) are frequently missing `aria-label`, and decorative Dashicons are missing `aria-hidden="true"`.
**Action:** When working on WP Admin interfaces, always audit modal close buttons and decorative icons for these specific attributes. Use `esc_attr_e('Close modal', 'text-domain')` for consistency.

## 2024-05-24 - Contextual Action Buttons in Data Tables
**Learning:** In WordPress admin tables (`wp-list-table`), action buttons like "Edit" or "Delete" are often repeated in every row, creating ambiguity for screen reader users ("Delete" what?).
**Action:** Always add `aria-label` attributes to these buttons that include the row's identifying data (e.g., `aria-label="<?php printf(esc_attr__('Delete author %s', 'domain'), esc_attr($name)); ?>"`).
