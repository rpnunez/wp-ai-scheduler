## 2024-05-23 - Contextual Actions in Data Tables
**Learning:** Generic action labels like "View", "Edit", or "Select" in data tables (history, posts, etc.) are a major accessibility barrier. Screen reader users navigating by buttons or links hear a repetitive list of "View, View, View" without context.
**Action:** Always use `aria-label` or `screen-reader-text` to append the item's title/name to the action (e.g., "View [Post Title]"). This applies to row checkboxes as well ("Select [Post Title]").
