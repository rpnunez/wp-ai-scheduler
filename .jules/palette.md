## 2024-05-23 - Accessibility Patterns for WordPress Admin
**Learning:** Standard WordPress Admin UI patterns (like modals and empty states with Dashicons) often lack default ARIA attributes. Specifically, modal close buttons (`&times;`) are frequently missing `aria-label`, and decorative Dashicons are missing `aria-hidden="true"`.
**Action:** When working on WP Admin interfaces, always audit modal close buttons and decorative icons for these specific attributes. Use `esc_attr_e('Close modal', 'text-domain')` for consistency.

## 2024-05-24 - Unified Soft Confirm for Destructive Actions
**Learning:** The "Soft Confirm" pattern (replacing the button text with "Click again to confirm" for a few seconds) provides a much smoother UX than native browser `confirm()` alerts, which are blocking and intrusive. However, it requires careful handling of localized strings and visual feedback (red color) to clearly indicate the destructive nature of the pending action.
**Action:** Extract the Soft Confirm logic into a reusable helper (`AIPS.softConfirm`) to ensure consistency across all delete actions in the admin panel, rather than duplicating the logic or mixing it with native alerts.
