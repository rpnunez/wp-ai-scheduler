
## 2026-06-10 - [Accessibility]
**Area:** templates/admin/planner.php
**Status:** opened PR
**PR:** 🎨 Palette: Add aria-hidden to decorative icons in Planner UI
**Learning:** Decorative icons from Dashicons must have `aria-hidden="true"` so they are properly skipped by screen readers.
**Action:** When adding dashicon spans that are decorative, ensure `aria-hidden="true"` is added.
