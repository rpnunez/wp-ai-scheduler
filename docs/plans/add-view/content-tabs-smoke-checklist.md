# Content Tabs Smoke Test Checklist

Use this quick checklist after Twig/list-controls changes to verify parity for all three Content tabs.

## Preconditions

- Log in as an admin user with `manage_options` capability.
- Have at least:
  - 1 generated/published post history row
  - 1 partial generation row
  - 1 pending-review draft row
- Have enough records in each tab to trigger pagination (recommended: >20 rows).

## 1) Generated Posts Tab

- Open Content page and verify Generated Posts tab is active by default.
- Search:
  - Enter a query in `Search posts...` and submit.
  - Confirm table rows are filtered and `Clear` appears.
  - Click `Clear` and confirm results reset.
- Filters:
  - Select an Author and Template filter, click `Filter`, verify filtered rows.
  - Click `Clear Filters` and confirm both filters reset.
- Pagination:
  - Navigate to next/previous page using footer pagination.
  - Confirm query/filter state persists across pages.
- Bulk actions area:
  - Confirm bulk-actions slot is present but intentionally empty (no regressions in layout/alignment).

## 2) Partial Generations Tab

- Switch to Partial Generations tab.
- Search:
  - Run search and verify filtered rows + clear behavior.
- Filters:
  - Apply Author/Template filters and verify row updates.
  - Clear filters and verify full list returns.
- Pagination:
  - Use footer pagination and confirm tab anchor/hash stays on partial tab.
- Bulk actions area:
  - Confirm bulk-actions slot is present but intentionally empty.

## 3) Pending Review Tab

- Switch to Pending Review tab.
- Search:
  - Search for a known draft title/source; confirm filtered results and clear behavior.
- Filters:
  - Filter by Template; confirm filtered rows.
  - Clear filters and verify rows reset.
- Pagination:
  - Navigate pages and confirm tab remains on pending-review section.
- Bulk actions:
  - Select one row checkbox and confirm:
    - selected count increments
    - bulk `Apply` button enables
  - Use `Select All` and confirm count matches visible rows.
  - Choose `Publish` then `Apply` on test content and verify success behavior.
  - Repeat with `Delete` on test content and verify success behavior.
  - Confirm count resets and `Apply` re-disables after action completes.

## 4) Cross-Tab Regression Checks

- Keyboard tab order flows logically across controls: bulk -> search -> filters -> table -> footer pagination.
- Controls have labels or `aria-label`/screen-reader text.
- No JS errors in browser console while switching tabs, filtering, paging, and running bulk actions.
- Existing row actions still work:
  - Generated: Edit, Preview, AI Edit, History, View Session.
  - Partial: Edit, Preview, AI Edit, retry/regenerate actions.
  - Pending: Publish, Edit, Preview, AI Edit, History, View Session, Regenerate.
