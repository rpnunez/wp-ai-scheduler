## 2024-05-23 - Accessibility Patterns for WordPress Admin
**Learning:** Standard WordPress Admin UI patterns (like modals and empty states with Dashicons) often lack default ARIA attributes. Specifically, modal close buttons (`&times;`) are frequently missing `aria-label`, and decorative Dashicons are missing `aria-hidden="true"`.
**Action:** When working on WP Admin interfaces, always audit modal close buttons and decorative icons for these specific attributes. Use `esc_attr_e('Close modal', 'text-domain')` for consistency.

## 2024-05-24 - Decorative Icons in Dashboard Cards
**Learning:** Dashboard statistic cards often use large icons for visual appeal. These are purely decorative and can be distracting or confusing for screen reader users if they are announced as "graphic" or empty content.
**Action:** Always add `aria-hidden="true"` to icon containers that are purely decorative, especially in high-level dashboard summaries where the text content provides the full context.
