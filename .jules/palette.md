## 2024-05-23 - Accessibility Patterns for WordPress Admin
**Learning:** Standard WordPress Admin UI patterns (like modals and empty states with Dashicons) often lack default ARIA attributes. Specifically, modal close buttons (`&times;`) are frequently missing `aria-label`, and decorative Dashicons are missing `aria-hidden="true"`.
**Action:** When working on WP Admin interfaces, always audit modal close buttons and decorative icons for these specific attributes. Use `esc_attr_e('Close modal', 'text-domain')` for consistency.

## 2024-05-24 - Focus Management in Custom Modals
**Learning:** Custom jQuery-based modals (using `.show()`) do not automatically transfer focus, leaving keyboard users lost in the DOM. This is a critical accessibility gap in "homegrown" modal implementations.
**Action:** Always add a `setTimeout(() => firstInput.focus(), 100)` (or similar mechanism) immediately after showing a modal to ensure keyboard context is properly updated.
