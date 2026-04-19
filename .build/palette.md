## 2024-05-23 - Accessibility Patterns for WordPress Admin
**Learning:** Standard WordPress Admin UI patterns (like modals and empty states with Dashicons) often lack default ARIA attributes. Specifically, modal close buttons (`&times;`) are frequently missing `aria-label`, and decorative Dashicons are missing `aria-hidden="true"`.
**Action:** When working on WP Admin interfaces, always audit modal close buttons and decorative icons for these specific attributes. Use `esc_attr_e('Close modal', 'text-domain')` for consistency.
## 2025-04-18 - Author Suggestions Empty State & Clear Button Consistency
- Discovered that "clear search" buttons across the application were inconsistently styled, using a mix of `.aips-btn-primary`, `.aips-btn-secondary`, and `.aips-btn-ghost`. Standardized all clear buttons to `.aips-btn-ghost` to reduce visual clutter and conform to UI consistency guidelines.
- Added a standard `.aips-empty-state` structure for the Author Suggestions feature in `authors.js` for when an AI suggestion request returns successfully but yields no results, replacing an implicit error toast with a better UX empty state pattern.
