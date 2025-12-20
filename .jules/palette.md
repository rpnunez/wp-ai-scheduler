## 2024-05-23 - Accessibility Patterns for WordPress Admin
**Learning:** Standard WordPress Admin UI patterns (like modals and empty states with Dashicons) often lack default ARIA attributes. Specifically, modal close buttons (`&times;`) are frequently missing `aria-label`, and decorative Dashicons are missing `aria-hidden="true"`.
**Action:** When working on WP Admin interfaces, always audit modal close buttons and decorative icons for these specific attributes. Use `esc_attr_e('Close modal', 'text-domain')` for consistency.

## 2024-05-23 - Helper Input Labels
**Learning:** Helper inputs (like search filters inside a form field wrapper) often get overlooked for labels because they share a visual context with the main input.
**Action:** Always verify that 'filter' or 'search' inputs inside other components have their own unique label, even if visually hidden.
