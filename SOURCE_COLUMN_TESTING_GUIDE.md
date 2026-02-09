# Source Column Feature - Testing Guide

## Overview
This document provides comprehensive testing instructions for the new "Source" column feature added to the Generated Posts and Pending Review tabs.

## Feature Description
The Source column displays:
1. **Type**: Whether the post was generated from a Template or Author Topic
2. **Creation Method**: Whether it was a Manual or Scheduled run
3. **Context**: Template name or Author/Topic information

## Display Format Examples
- `Template: My Template (Manual)`
- `Template: Marketing Posts (Scheduled)`
- `Author Topic: John Doe - SEO Best Practices (Manual)`
- `Author Topic: Jane Smith - Content Strategy (Scheduled)`

## Testing Prerequisites

### Database Migration
1. Activate or update the plugin to trigger schema migration
2. Verify new columns exist in `wp_aips_history` table:
   ```sql
   SHOW COLUMNS FROM wp_aips_history LIKE 'author_id';
   SHOW COLUMNS FROM wp_aips_history LIKE 'topic_id';
   SHOW COLUMNS FROM wp_aips_history LIKE 'creation_method';
   ```
3. All three columns should exist with proper types

### Existing Data
- Posts generated before this update will show "Unknown" or template name without creation method
- This is expected behavior as historical data lacks creation_method

## Test Cases

### Test 1: Manual Template Generation
**Steps:**
1. Navigate to Templates page
2. Create or select an existing template
3. Click "Generate Now" button to manually generate a post
4. Navigate to Generated Posts page
5. Find the newly generated post

**Expected Result:**
- Source column shows: `Template: [Template Name] (Manual)`

**Verification:**
- [ ] Source type shows "Template"
- [ ] Template name is displayed correctly
- [ ] Creation method shows "(Manual)"

---

### Test 2: Scheduled Template Generation
**Steps:**
1. Navigate to Schedule page
2. Create or activate a schedule for a template
3. Wait for the schedule to run (or trigger cron manually)
4. Navigate to Generated Posts page
5. Find the scheduled post

**Expected Result:**
- Source column shows: `Template: [Template Name] (Scheduled)`

**Verification:**
- [ ] Source type shows "Template"
- [ ] Template name is displayed correctly
- [ ] Creation method shows "(Scheduled)"

---

### Test 3: Manual Author Topic Generation
**Steps:**
1. Navigate to Authors page
2. Select an author with approved topics
3. Click "Generate Now" on an approved topic
4. Navigate to Generated Posts page
5. Find the newly generated post

**Expected Result:**
- Source column shows: `Author Topic: [Author Name] - [Topic Title] (Manual)`

**Verification:**
- [ ] Source type shows "Author Topic"
- [ ] Author name is displayed correctly
- [ ] Topic title is displayed correctly
- [ ] Creation method shows "(Manual)"

---

### Test 4: Scheduled Author Topic Generation
**Steps:**
1. Navigate to Authors page
2. Configure an author with post generation schedule
3. Ensure the author has approved topics
4. Wait for scheduled generation (or trigger cron)
5. Navigate to Generated Posts page
6. Find the scheduled post

**Expected Result:**
- Source column shows: `Author Topic: [Author Name] - [Topic Title] (Scheduled)`

**Verification:**
- [ ] Source type shows "Author Topic"
- [ ] Author name is displayed correctly
- [ ] Topic title is displayed correctly
- [ ] Creation method shows "(Scheduled)"

---

### Test 5: Pending Review Tab - Templates
**Steps:**
1. Configure a template with post_status = 'draft'
2. Generate a post (manual or scheduled)
3. Navigate to Generated Posts → Pending Review tab
4. Verify the draft post appears

**Expected Result:**
- Source column shows template information with creation method

**Verification:**
- [ ] Source column is present
- [ ] Template information is correct
- [ ] Creation method is displayed (if available)

---

### Test 6: Pending Review Tab - Author Topics
**Steps:**
1. Configure an author with post_status = 'draft'
2. Generate a post from a topic (manual or scheduled)
3. Navigate to Generated Posts → Pending Review tab
4. Verify the draft post appears

**Expected Result:**
- Source column shows author topic information with creation method

**Verification:**
- [ ] Source column is present
- [ ] Author and topic information is correct
- [ ] Creation method is displayed (if available)

---

### Test 7: Mixed Content Display
**Steps:**
1. Generate multiple posts using different methods:
   - Manual template generation
   - Scheduled template generation
   - Manual author topic generation
   - Scheduled author topic generation
2. Navigate to Generated Posts page
3. Scroll through the list

**Expected Result:**
- All posts show appropriate source information
- Different source types are clearly distinguishable
- No display errors or formatting issues

**Verification:**
- [ ] All source types display correctly
- [ ] Text is properly escaped (no HTML/JS injection)
- [ ] Layout remains clean and readable
- [ ] Column width is appropriate

---

### Test 8: Search and Filter Compatibility
**Steps:**
1. Use search functionality on Generated Posts page
2. Use template filter on Pending Review tab
3. Verify source column persists and displays correctly

**Expected Result:**
- Source column remains visible after filtering
- Source information is accurate for filtered results

**Verification:**
- [ ] Column appears in search results
- [ ] Column appears in filtered results
- [ ] Data accuracy maintained

---

## Database Verification

### Check Source Data Storage
Run these SQL queries to verify data is being stored correctly:

```sql
-- Check recent posts with source information
SELECT id, post_id, template_id, author_id, topic_id, creation_method, created_at
FROM wp_aips_history
WHERE post_id IS NOT NULL
ORDER BY created_at DESC
LIMIT 10;

-- Count by source type
SELECT 
    CASE 
        WHEN template_id IS NOT NULL THEN 'Template'
        WHEN author_id IS NOT NULL AND topic_id IS NOT NULL THEN 'Author Topic'
        ELSE 'Unknown'
    END as source_type,
    creation_method,
    COUNT(*) as count
FROM wp_aips_history
WHERE post_id IS NOT NULL
GROUP BY source_type, creation_method;
```

**Expected Results:**
- New posts should have populated source fields
- creation_method should be 'manual' or 'scheduled' (or NULL for old data)

---

## Edge Cases to Test

### Edge Case 1: Missing Template Name
**Scenario:** Template is deleted but post history remains

**Expected:** Source shows `Template (Manual/Scheduled)` without name

---

### Edge Case 2: Missing Author/Topic Names
**Scenario:** Author or topic deleted but post history remains

**Expected:** Source shows `Author Topic (Manual/Scheduled)` with partial or no details

---

### Edge Case 3: Very Long Names
**Scenario:** Template or topic has a very long name

**Expected:** 
- Text doesn't break layout
- Content is readable (may truncate with ellipsis)

---

### Edge Case 4: Special Characters
**Scenario:** Names contain special characters (quotes, HTML entities, etc.)

**Expected:**
- All text properly escaped
- No HTML rendering issues
- No JavaScript execution

---

## Regression Testing

Verify that existing functionality still works:

### Generated Posts Tab
- [ ] Search functionality works
- [ ] Pagination works
- [ ] Date columns display correctly
- [ ] Edit button works
- [ ] View Session button works

### Pending Review Tab
- [ ] Search functionality works
- [ ] Template filter works
- [ ] Pagination works
- [ ] Bulk actions work
- [ ] Publish button works
- [ ] Delete button works
- [ ] Regenerate button works
- [ ] Preview functionality works

---

## Performance Testing

For sites with many posts:
1. Generate 100+ posts with various sources
2. Navigate to Generated Posts page
3. Check page load time
4. Test pagination performance
5. Test search performance

**Expected:**
- No significant performance degradation
- Query times remain reasonable
- UI remains responsive

---

## Browser Compatibility

Test the UI in:
- [ ] Chrome/Edge (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Mobile browsers (iOS Safari, Chrome Mobile)

**Verification:**
- Column displays correctly in all browsers
- Text is readable
- Layout is not broken

---

## Success Criteria

✅ All test cases pass
✅ No console errors in browser
✅ No PHP errors in logs
✅ Database queries perform well
✅ UI is responsive and clean
✅ Security: All output is properly escaped
✅ No regression in existing features

---

## Troubleshooting

### Issue: Source shows "Unknown"
**Cause:** Missing source data in history record
**Fix:** Verify context is properly created with source fields in generator

### Issue: Creation method is empty
**Cause:** creation_method not passed when creating context
**Fix:** Check that schedule processor and author post generator pass creation_method

### Issue: Template/Author names not showing
**Cause:** Repository lookup failing or entity deleted
**Fix:** Check that format_source method properly queries repositories

### Issue: Layout broken
**Cause:** Column width issues or CSS conflicts
**Fix:** Review CSS and table column definitions

---

## Manual SQL Testing (Optional)

To manually insert test data:

```sql
-- Insert a test history record with source info
INSERT INTO wp_aips_history 
(post_id, template_id, author_id, topic_id, creation_method, status, generated_title, created_at) 
VALUES 
(123, 1, NULL, NULL, 'manual', 'completed', 'Test Post', NOW()),
(124, NULL, 2, 3, 'scheduled', 'completed', 'Test Author Post', NOW());
```

Then verify the Source column displays correctly for these records.

---

## Reporting Issues

When reporting issues, please include:
1. WordPress version
2. PHP version
3. Plugin version
4. Browser and OS
5. Steps to reproduce
6. Expected vs actual behavior
7. Screenshots if UI-related
8. Relevant error logs
9. Database query results (if applicable)

---

## Additional Notes

- The feature uses WordPress's dbDelta for schema updates, which runs automatically on plugin activation
- Historical data (posts generated before this update) may not have complete source information
- The format_source method is designed to handle missing data gracefully
- All output is properly escaped for security
- The implementation follows WordPress coding standards
