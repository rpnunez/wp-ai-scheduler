## 2024-05-23 - Accessibility Patterns for WordPress Admin
**Learning:** Standard WordPress Admin UI patterns (like modals and empty states with Dashicons) often lack default ARIA attributes. Specifically, modal close buttons (`&times;`) are frequently missing `aria-label`, and decorative Dashicons are missing `aria-hidden="true"`.
**Action:** When working on WP Admin interfaces, always audit modal close buttons and decorative icons for these specific attributes. Use `esc_attr_e('Close modal', 'text-domain')` for consistency.

## 2026-06-02 - Add aria-hidden to decorative Dashicons in admin templates
**Learning:** Found that numerous Dashicons (`<span class="dashicons ...">`) were missing the `aria-hidden="true"` attribute in admin templates. Since these icons are purely decorative and exist next to readable text (e.g., `<span class="dashicons dashicons-edit"></span> Edit`), they are read out by screen readers as redundant or confusing characters unless explicitly hidden.
**Action:** When adding Dashicons to admin interfaces, always include `aria-hidden="true"` unless the icon itself is the only content of an interactive element, in which case it requires an `aria-label` on the parent button instead. Added it to multiple missing icons in `structures.php` and `sections.php`.
