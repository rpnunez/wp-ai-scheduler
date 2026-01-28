## 2024-05-23 - Accessibility Patterns for WordPress Admin
**Learning:** Standard WordPress Admin UI patterns (like modals and empty states with Dashicons) often lack default ARIA attributes. Specifically, modal close buttons (`&times;`) are frequently missing `aria-label`, and decorative Dashicons are missing `aria-hidden="true"`.
**Action:** When working on WP Admin interfaces, always audit modal close buttons and decorative icons for these specific attributes. Use `esc_attr_e('Close modal', 'text-domain')` for consistency.

## 2024-05-24 - Accessibility for Admin List Tables
**Learning:** In WordPress Admin list tables, action buttons like "Edit" or "Delete" are often ambiguous for screen reader users when navigating by controls. They lack context about *which* item is being acted upon.
**Action:** Always add dynamic `aria-label` attributes to these buttons, incorporating the item's name (e.g., `aria-label="Edit author John Doe"`). Use `sprintf` for localization support.
