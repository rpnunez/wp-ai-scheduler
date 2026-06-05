## 2026-06-05 - Prevent Accidental Calendar Submissions
**Area:** templates/admin/calendar.php
**Status:** opened PR
**PR:** 🎨 Palette: Add type='button' to calendar controls to prevent accidental submissions
**Learning:** Calendar UI buttons without explicit types default to `submit`, causing unexpected reloads if placed in forms.
**Action:** Always explicitly define `type="button"` on JS-driven interactive UI controls.
