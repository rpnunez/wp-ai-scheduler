## 2024-05-23 - Accessibility Patterns for WordPress Admin
**Learning:** Standard WordPress Admin UI patterns (like modals and empty states with Dashicons) often lack default ARIA attributes. Specifically, modal close buttons (`&times;`) are frequently missing `aria-label`, and decorative Dashicons are missing `aria-hidden="true"`.
**Action:** When working on WP Admin interfaces, always audit modal close buttons and decorative icons for these specific attributes. Use `esc_attr_e('Close modal', 'text-domain')` for consistency.
## 2026-05-30 - Added loading indicator when editing authors
**Learning:** AJAX-driven edit modals within this repo can sometimes appear with empty fields while data is still loading, creating a confusing user experience. The standard pattern to solve this is to use the existing WordPress admin `.spinner` element within a loader container.
**Action:** For future modal-based edit features, ensure a loader container is added alongside the form, and use JavaScript to hide the form and show the loader during the AJAX fetch phase. Only reveal the form when the data has successfully populated.
## 2026-07-07 - Add aria-hidden to decorative Dashicons
**Area:** Admin Templates (Global)
**Status:** opened PR
**PR:** 🎨 Palette: Improve accessibility of decorative Dashicons
**Learning:** Decorative dashicons (<span class="dashicons...">) often lack aria-hidden="true", causing screen readers to misinterpret them.
**Action:** Applied aria-hidden="true" to dashicons across templates.
