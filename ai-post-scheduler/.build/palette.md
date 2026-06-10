## 2024-06-07 - [A11y ARIA Improvements]
**Area:** Admin templates (structures, taxonomy, campaign wizard)
**Status:** opened PR
**PR:** TBD
**Learning:** Decorative dashicons used as empty state icons or visual button markers often lack `aria-hidden="true"`, and icon-only buttons (like the `&times;` close buttons or dashicon buttons) sometimes lack explicit `aria-label` attributes for screen readers.
**Action:** Audit and add missing `aria-hidden="true"` to purely decorative `dashicons` classes, and missing `aria-label`s to icon-only interactive elements across the remaining admin PHP templates.
