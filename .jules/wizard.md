# Feature Wizard's Journal 🧙‍♂️

## 2024-05-23 - Client-side Search for Templates
**Learning:** `templates.php` loads all records at once, but lacked search functionality, forcing users to scan manually.
**Action:** Implemented client-side filtering (Name/Category) with a "Clear" button and empty state handling, reusing standard WP inputs.

## 2024-05-25 - Planner UI Improvements
**Learning:** Users needed a way to clear the brainstormed topic list and copy selected topics to clipboard for external use.
**Action:** Implemented "Clear List" (with confirmation) and "Copy Selected" buttons in the Planner toolbar, updating admin-planner.js to handle clipboard interactions.

## 2024-05-27 - History Bulk Actions
**Learning:** The history table lacked bulk deletion capabilities, forcing users to delete failed/unwanted entries one by one or clear the entire history.
**Action:** Implemented "Select All" / Individual checkboxes and a "Delete Selected" button in `history.php`, backed by a new `delete_bulk` repository method and AJAX handler.

## 2025-12-25 - Icon Button Feedback
**Learning:** The journal described a specific visual feedback pattern for small buttons (swapping icon to checkmark), but the implementation in `admin.js` was missing/broken for icon-only buttons, causing the icon to disappear.
**Action:** Implemented the icon-swap logic in `copyToClipboard` handler in `admin.js` to correctly support icon-only buttons like those in the Prompt Sections table.

## 2025-12-26 - Structure Search Consistency
**Learning:** The "Article Structures" table lacked search functionality while "Structure Sections" (and other lists) had it, creating an inconsistent user experience where users expected to be able to filter structures.
**Action:** Implemented client-side search for Article Structures using the established pattern (CSS classes for columns + JS filtering), ensuring consistent discoverability across all admin tables.

## 2025-12-27 - Author Search Consistency
**Learning:** The "Authors" table was the last major list without search, breaking the expectation set by Templates, Schedules, and Structures. Consistency in basic data tools (search/filter) significantly reduces cognitive load.
**Action:** Implemented client-side search for Authors using the standard pattern (search input + JS filter), ensuring the entire admin suite now behaves predictably.

## 2026-01-20 - Planner Filter & Bulk Select
**Learning:** When implementing client-side filtering on a list with bulk actions (like "Select All"), users expect the bulk action to apply only to the *visible* (filtered) items, not the hidden ones.
**Action:** Updated `toggleAllTopics` in `admin-planner.js` to target `.topic-checkbox:visible`, ensuring that "Select All" respects the current search filter.

## 2026-01-21 - Generated Posts Empty State
**Learning:** `generated-posts.php` was missing the standard Empty State pattern (`.aips-empty-state`) and a direct "View" action, causing inconsistency with `history.php`.
**Action:** Implemented the standard Empty State and added a "View" button with accessibility attributes (`aria-label`, `rel="noopener"`) to match the "Feature Wizard" polish standards.

## 2026-01-22 - Research Search Consistency
**Learning:** The "Trending Topics Library" table lacked search functionality, which is a standard expectation established in other admin tables (Authors, Structures, etc.).
**Action:** Implemented client-side search for Trending Topics with a "Clear" button and Empty State, ensuring "Select All" functionality respects the active filter.
