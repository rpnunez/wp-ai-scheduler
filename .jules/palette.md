## 2024-05-23 - Accessibility Patterns for WordPress Admin
**Learning:** Standard WordPress Admin UI patterns (like modals and empty states with Dashicons) often lack default ARIA attributes. Specifically, modal close buttons (`&times;`) are frequently missing `aria-label`, and decorative Dashicons are missing `aria-hidden="true"`.
**Action:** When working on WP Admin interfaces, always audit modal close buttons and decorative icons for these specific attributes. Use `esc_attr_e('Close modal', 'text-domain')` for consistency.

## 2024-05-23 - Accessibility in Dynamic JavaScript Content
**Learning:** Dynamically generated HTML in JavaScript (e.g., in AJAX callbacks or `render` functions) is a common source of accessibility regressions because it bypasses server-side escaping and attribute helpers. In this project, `dashicons` injected via JS were consistently missing `aria-hidden="true"`.
**Action:** When writing or reviewing JS that constructs HTML strings, explicitly check for ARIA attributes on interactive elements and `aria-hidden="true"` on decorative icons. Consider creating a helper function for icon generation to enforce this.
