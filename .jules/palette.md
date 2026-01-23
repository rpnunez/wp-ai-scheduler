## 2024-05-23 - Accessibility Patterns for WordPress Admin
**Learning:** Standard WordPress Admin UI patterns (like modals and empty states with Dashicons) often lack default ARIA attributes. Specifically, modal close buttons (`&times;`) are frequently missing `aria-label`, and decorative Dashicons are missing `aria-hidden="true"`.
**Action:** When working on WP Admin interfaces, always audit modal close buttons and decorative icons for these specific attributes. Use `esc_attr_e('Close modal', 'text-domain')` for consistency.

## 2025-01-02 - Decorative Icon Accessibility
**Learning:** Decorative icons (like Dashicons in WordPress admin) often lack `aria-hidden="true"`, causing screen readers to announce them as "graphic" or read their character codes/font ligatures. This is common in both PHP-rendered templates and JS-injected HTML.
**Action:** Always add `aria-hidden="true"` to decorative icons (e.g., `<span class="dashicons ..." aria-hidden="true"></span>`) to ensure they are ignored by assistive technology. Check both template files and JavaScript string templates.
