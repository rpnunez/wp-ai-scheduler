## 2024-05-23 - Accessibility Patterns for WordPress Admin
**Learning:** Standard WordPress Admin UI patterns (like modals and empty states with Dashicons) often lack default ARIA attributes. Specifically, modal close buttons (`&times;`) are frequently missing `aria-label`, and decorative Dashicons are missing `aria-hidden="true"`.
**Action:** When working on WP Admin interfaces, always audit modal close buttons and decorative icons for these specific attributes. Use `esc_attr_e('Close modal', 'text-domain')` for consistency.

## 2024-05-23 - Accessibility in Toolbars
**Learning:** Toolbar-style filters often omit visual labels for density, but this creates an accessibility barrier for screen readers. Using `aria-label` allows us to maintain the compact visual design while ensuring the controls are identifiable.
**Action:** When creating filter toolbars, always add `aria-label` or `aria-labelledby` to inputs that lack visible labels.
