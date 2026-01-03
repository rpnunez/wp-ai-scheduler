## 2024-05-23 - Accessibility Patterns for WordPress Admin
**Learning:** Standard WordPress Admin UI patterns (like modals and empty states with Dashicons) often lack default ARIA attributes. Specifically, modal close buttons (`&times;`) are frequently missing `aria-label`, and decorative Dashicons are missing `aria-hidden="true"`.
**Action:** When working on WP Admin interfaces, always audit modal close buttons and decorative icons for these specific attributes. Use `esc_attr_e('Close modal', 'text-domain')` for consistency.

## 2024-05-23 - Async Loading States
**Learning:** In the Research admin page, the "Load Topics" button lacked immediate visual feedback (disabling + spinner) during the AJAX request, which could lead to user confusion or double-submission.
**Action:** When implementing AJAX actions on buttons, always pair the click handler with `.prop('disabled', true)` and `.spinner.addClass('is-active')`, and ensure they are reset in the `complete` callback.
