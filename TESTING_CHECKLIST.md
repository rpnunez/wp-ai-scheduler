# Testing Checklist: Generated Posts Tabbed Interface

## Pre-Testing Setup
- [ ] Install the plugin in a WordPress test environment
- [ ] Ensure Meow Apps AI Engine is installed and activated
- [ ] Generate some test posts (both published and draft status)

---

## Test Case 1: Menu Item Removal
### Steps:
1. Navigate to WordPress admin
2. Look at the "AI Post Scheduler" menu

### Expected Results:
- [ ] "Post Review" menu item is NOT visible
- [ ] "Generated Posts" menu item IS visible
- [ ] All other menu items remain unchanged

---

## Test Case 2: Default Tab Display
### Steps:
1. Click on "Generated Posts" menu item
2. Observe the page that loads

### Expected Results:
- [ ] Page title shows "Generated Posts"
- [ ] Tab bar is visible with two tabs
- [ ] "Generated Posts" tab is active (highlighted)
- [ ] "Pending Review" tab is inactive
- [ ] Content area shows published posts
- [ ] URL is: `admin.php?page=aips-generated-posts`

---

## Test Case 3: Tab Switching
### Steps:
1. Be on Generated Posts page (default tab)
2. Click the "Pending Review" tab
3. Observe the changes

### Expected Results:
- [ ] "Pending Review" tab becomes active (highlighted)
- [ ] "Generated Posts" tab becomes inactive
- [ ] Content area switches to show draft posts
- [ ] URL changes to: `admin.php?page=aips-generated-posts#aips-pending-review`
- [ ] No page reload occurs
- [ ] Switching is smooth and instant

---

## Test Case 4: Tab Content - Generated Posts
### Steps:
1. Ensure "Generated Posts" tab is active
2. Review the displayed content

### Expected Results:
- [ ] Description text is visible
- [ ] Search form is present
- [ ] Table shows published posts with columns:
  - [ ] Title
  - [ ] Date Scheduled
  - [ ] Date Published
  - [ ] Date Generated
  - [ ] Actions
- [ ] "Edit" button is present for each post
- [ ] "View Session" button is present for each post
- [ ] Pagination works (if applicable)
- [ ] Empty state message shows if no posts exist

---

## Test Case 5: Tab Content - Pending Review
### Steps:
1. Click "Pending Review" tab
2. Review the displayed content

### Expected Results:
- [ ] Draft posts count is displayed
- [ ] Search form is present
- [ ] Template filter dropdown is present (if templates exist)
- [ ] Bulk actions dropdown is available
- [ ] Table shows draft posts with columns:
  - [ ] Checkbox (for bulk selection)
  - [ ] Post Title
  - [ ] Template
  - [ ] Created
  - [ ] Modified
  - [ ] Actions
- [ ] Action buttons present for each post:
  - [ ] Edit
  - [ ] View Logs
  - [ ] Publish
  - [ ] Re-generate
  - [ ] Delete
- [ ] Pagination works and preserves tab state
- [ ] Empty state message shows if no drafts exist

---

## Test Case 6: Deep Linking
### Steps:
1. Navigate away from the Generated Posts page
2. Manually enter URL: `admin.php?page=aips-generated-posts#aips-pending-review`
3. Press Enter

### Expected Results:
- [ ] Page loads
- [ ] "Pending Review" tab is automatically activated
- [ ] Draft posts content is displayed
- [ ] "Generated Posts" tab is inactive

---

## Test Case 7: Search Functionality - Generated Posts
### Steps:
1. Navigate to "Generated Posts" tab
2. Enter a search term in the search box
3. Click "Search Posts" button

### Expected Results:
- [ ] Search executes
- [ ] Results are filtered based on search term
- [ ] Tab remains on "Generated Posts"
- [ ] Search query is preserved in URL

---

## Test Case 8: Search Functionality - Pending Review
### Steps:
1. Navigate to "Pending Review" tab
2. Enter a search term in the search box
3. Click "Search" button

### Expected Results:
- [ ] Search executes
- [ ] Results are filtered based on search term
- [ ] Tab remains on "Pending Review"
- [ ] URL includes both search query and hash fragment

---

## Test Case 9: Pagination - Pending Review Tab
### Steps:
1. Navigate to "Pending Review" tab
2. If pagination exists, click "Next" or a page number

### Expected Results:
- [ ] Page navigates to next set of results
- [ ] Tab remains on "Pending Review" (doesn't switch back)
- [ ] URL includes page number AND hash fragment
- [ ] Draft posts from next page are displayed

---

## Test Case 10: Template Filter - Pending Review
### Steps:
1. Navigate to "Pending Review" tab
2. Select a template from the filter dropdown
3. Click "Filter" button

### Expected Results:
- [ ] Posts are filtered by selected template
- [ ] Tab remains on "Pending Review"
- [ ] Filter state is preserved in URL

---

## Test Case 11: View Session Modal
### Steps:
1. Navigate to "Generated Posts" tab
2. Click "View Session" button on any post

### Expected Results:
- [ ] Modal opens
- [ ] Session information is displayed
- [ ] Tabs within modal work (Logs / AI)
- [ ] Modal can be closed

---

## Test Case 12: View Logs Modal
### Steps:
1. Navigate to "Pending Review" tab
2. Click "View Logs" button on any post

### Expected Results:
- [ ] Modal opens
- [ ] Generation logs are displayed
- [ ] Modal can be closed

---

## Test Case 13: Bulk Actions - Pending Review
### Steps:
1. Navigate to "Pending Review" tab
2. Check checkboxes for 2-3 posts
3. Select "Publish" from bulk actions dropdown
4. Click "Apply"

### Expected Results:
- [ ] Confirmation dialog appears
- [ ] After confirmation, posts are published
- [ ] Success message is displayed
- [ ] Posts are removed from draft list

---

## Test Case 14: Email Notification Link
### Steps:
1. Ensure email notifications are enabled in settings
2. Create a draft post via the plugin
3. Trigger the notification email (may need to run cron manually)
4. Open the email
5. Click the "Review Posts" button/link

### Expected Results:
- [ ] WordPress admin opens
- [ ] Generated Posts page loads
- [ ] "Pending Review" tab is automatically active
- [ ] Draft posts are displayed

---

## Test Case 15: Accessibility - Keyboard Navigation
### Steps:
1. Navigate to Generated Posts page
2. Use Tab key to navigate through the page
3. Try to switch tabs using keyboard only

### Expected Results:
- [ ] Tab key moves focus to tab links
- [ ] Enter/Space key activates tabs
- [ ] Focus is visually indicated
- [ ] Tab content is accessible via keyboard

---

## Test Case 16: Accessibility - Screen Reader
### Steps:
1. Enable a screen reader (NVDA, JAWS, VoiceOver)
2. Navigate to Generated Posts page
3. Navigate through the tabs

### Expected Results:
- [ ] Tab role is announced
- [ ] Active/inactive state is announced
- [ ] Tab panel content is properly associated
- [ ] All interactive elements are accessible

---

## Test Case 17: Browser Compatibility
### Browsers to Test:
- [ ] Chrome/Edge (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Mobile browsers (iOS Safari, Chrome Mobile)

### Expected Results:
- [ ] All functionality works in all browsers
- [ ] Tabs are clickable and responsive
- [ ] No JavaScript errors in console

---

## Test Case 18: Post Actions - Pending Review
### Steps:
1. Navigate to "Pending Review" tab
2. Test each action button:
   - Edit
   - Publish
   - Delete
   - Re-generate

### Expected Results:
- [ ] Edit: Opens post in WordPress editor
- [ ] Publish: Publishes post with confirmation
- [ ] Delete: Deletes post with confirmation
- [ ] Re-generate: Triggers regeneration with confirmation

---

## Test Case 19: Empty States
### Steps:
1. Remove all published posts
2. Check "Generated Posts" tab
3. Remove all draft posts
4. Check "Pending Review" tab

### Expected Results:
- [ ] Generated Posts tab shows "No generated posts found" message
- [ ] Pending Review tab shows "No Draft Posts" message with descriptive text
- [ ] Empty state icons are displayed
- [ ] Messages are clear and helpful

---

## Test Case 20: Performance
### Steps:
1. Create 100+ posts (if possible)
2. Navigate between tabs multiple times
3. Use search and filters

### Expected Results:
- [ ] Tab switching is instant (no delay)
- [ ] Search executes reasonably fast
- [ ] Pagination doesn't lag
- [ ] No memory leaks or slowdowns after extended use

---

## Regression Testing
### Areas to Verify Haven't Broken:
- [ ] Dashboard page still works
- [ ] Templates page still works
- [ ] Schedule page still works
- [ ] Other menu items function normally
- [ ] Post creation workflow unchanged
- [ ] Existing published posts display correctly
- [ ] Existing draft posts display correctly

---

## Test Results Summary

### Pass/Fail Count:
- Total Tests: 20
- Passed: ___
- Failed: ___
- Blocked: ___

### Critical Issues Found:
(List any critical bugs that prevent core functionality)

### Minor Issues Found:
(List any cosmetic or minor functionality issues)

### Browser-Specific Issues:
(List any issues specific to certain browsers)

### Recommendations:
(Suggestions for improvements or fixes)

---

## Sign-Off

**Tester Name:** _______________
**Date:** _______________
**Environment:** WordPress ___ / PHP ___ / Browser ___
**Status:** ☐ Approved  ☐ Needs Fixes  ☐ Blocked

**Notes:**
