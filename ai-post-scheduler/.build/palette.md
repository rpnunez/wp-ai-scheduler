
## 2026-06-03 - Accessible Icon-Only Buttons
**Learning:** Icon-only navigation buttons (such as calendar arrows) frequently rely solely on `title` attributes, which are insufficient for screen readers. The inner `dashicons` characters may also be incorrectly announced.
**Action:** Always provide an explicit `aria-label` on the `<button>` element for icon-only actions, and ensure the inner decorative `<span class="dashicons">` element includes `aria-hidden="true"` to prevent redundant or confusing screen reader announcements.
