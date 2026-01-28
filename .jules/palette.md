## 2024-05-23 - Accessibility Patterns for WordPress Admin
**Learning:** Standard WordPress Admin UI patterns (like modals and empty states with Dashicons) often lack default ARIA attributes. Specifically, modal close buttons (`&times;`) are frequently missing `aria-label`, and decorative Dashicons are missing `aria-hidden="true"`.
**Action:** When working on WP Admin interfaces, always audit modal close buttons and decorative icons for these specific attributes. Use `esc_attr_e('Close modal', 'text-domain')` for consistency.

## 2026-01-25 - Modal Focus Management
**Learning:** Custom jQuery modals often fail to manage focus, leaving keyboard users lost. Simply showing a modal (`.show()`) does not move focus. A reusable helper that finds the first focusable element (with a fallback to the close button) significantly improves accessibility with minimal code.
**Action:** Always implement a `setModalFocus` helper when dealing with custom modal implementations that don't rely on libraries with built-in accessibility.
