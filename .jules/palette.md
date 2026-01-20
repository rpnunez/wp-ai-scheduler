## 2024-05-23 - Accessibility Patterns for WordPress Admin
**Learning:** Standard WordPress Admin UI patterns (like modals and empty states with Dashicons) often lack default ARIA attributes. Specifically, modal close buttons (`&times;`) are frequently missing `aria-label`, and decorative Dashicons are missing `aria-hidden="true"`.
**Action:** When working on WP Admin interfaces, always audit modal close buttons and decorative icons for these specific attributes. Use `esc_attr_e('Close modal', 'text-domain')` for consistency.

## 2024-05-24 - Table Accessibility in WordPress Admin
**Learning:** Tables in WP Admin often lack programmatic association with their headings. While `<caption>` is the standard element, WP design patterns often use a preceding `<h2>`.
**Action:** Use `id` on the heading and `aria-labelledby="[id]"` on the `<table>` to create a strong semantic relationship without altering the visual layout.
