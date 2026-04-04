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

## 2024-05-19 - Add "Clear" buttons to search bars in Sections and Planner
**Learning:** Consistent UX in search bars requires not just an input, but `screen-reader-text` labels and a hidden `Clear` button that can be toggled by JS. Wait to merge `class` attributes when copying elements to prevent duplicate attributes like `class="aips-form-input" class="aips-planner-topic-search"`.
**Action:** Always check if the `admin.js` script handles specific clear button IDs even if they are missing from the PHP template, and add them proactively for completeness.

## 2026-01-23 - Standardize UI Empty States and Clear Buttons
**Learning:** Empty states for searches across different admin pages had inconsistent classes (`dashicons` vs `aips-empty-state-icon`, missing `aips-empty-state-actions`), and PHP-driven clear buttons lacked the standardized `.aips-btn-secondary` class.
**Action:** Ensured `generated-posts.php` and `post-review.php` have the correct classes on their search clear links, and applied the exact `.aips-empty-state*` class hierarchy to all `*-no-results` empty states.

## 2026-03-25 - Add Call-to-Actions in Empty States
**Learning:** Empty states that tell users to go somewhere without providing a direct link create unnecessary friction and break the flow.
**Action:** Always include clear call-to-action buttons (like 'Create X') in empty states that direct users to the exact next step needed.

## 2026-03-26 - Empty State Consistency
**Learning:** Empty states across different admin pages had inconsistent classes and missing wrapper elements.
**Action:** Standardized all empty states to use `.aips-empty-state-icon`, `.aips-empty-state-title`, `.aips-empty-state-description`, and `.aips-empty-state-actions` to ensure consistent UI.

## 2026-03-31 - Standardize Search Clear Buttons Classes
**Learning:** Discovered inconsistencies in the CSS classes for "Search" and "Clear" buttons across various admin panel filter interfaces where `.aips-btn-sm` was omitted, violating standard UI component consistency guidelines.
**Action:** Replaced `class="aips-btn aips-btn-secondary"` with `class="aips-btn aips-btn-sm aips-btn-secondary"` strictly on search and clear buttons within `.aips-filter-right` sections in all admin PHP templates to ensure uniform appearance across the plugin interface.

## 2026-04-03 - Added "Clear Filters" to PHP-driven Filter Bars
**Learning:** While JS-driven search bars had clear buttons, the PHP-driven filter bars (for Author and Template dropdowns) lacked an easy way to reset the filters without manually selecting "All" and resubmitting.
**Action:** Added a contextual "Clear Filters" ghost button next to the "Filter" submit button in `generated-posts.php` and `post-review.php` that only appears when a filter is actively applied.

## 2024-04-04 - Search Empty States
Learning: PHP-driven tabs had a generic "No Posts" empty state even when the user performed a search that returned no results, hiding the search context.
Action: Implemented conditional empty states that check `!empty($search_query)` to display a "No Posts Found" message with a "Clear Search" button instead of the generic empty state.
