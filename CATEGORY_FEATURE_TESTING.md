# Category Feature Testing Guide

This document provides instructions for testing the new category feature for Article Structures and Structure Sections.

## Prerequisites

1. WordPress installation with AI Post Scheduler plugin installed and activated
2. Access to WordPress admin dashboard
3. Database must be updated to include the new `category_id` columns

## Update Database Schema

Before testing, you need to update the database schema to include the new `category_id` columns:

### Option 1: Via Dev Tools (Recommended)
1. Enable Developer Mode in plugin settings
2. Navigate to **AI Post Scheduler > Dev Tools**
3. Click on **Database** tab
4. Click **Repair Database** button - this will add the new columns using dbDelta

### Option 2: Via Plugin Reinstall
1. Navigate to **AI Post Scheduler > Dev Tools** (enable developer mode first)
2. Click **Reinstall Database** with backup option checked
3. This will recreate all tables with the new schema

## Testing Categories CRUD Operations

### 1. Create Categories

1. Navigate to **AI Post Scheduler > Article Structures**
2. Click on the **Categories** tab
3. Click **Add New** button
4. Fill in:
   - Name: "How-To Guides"
   - Description: "Step-by-step instructional content"
5. Click **Save Category**
6. Verify the category appears in the list
7. Repeat to create more categories:
   - "Tutorials"
   - "Reference Documentation"
   - "Opinion Pieces"

### 2. Edit Categories

1. In the Categories tab, find a category
2. Click **Edit** button
3. Modify the name or description
4. Click **Save Category**
5. Verify the changes are reflected in the list

### 3. Delete Categories

1. In the Categories tab, find a category
2. Click **Delete** button
3. Confirm the deletion
4. Verify the category is removed from the list
5. Verify that structures and sections using this category become "Uncategorized"

## Testing Category Assignment

### 1. Assign Category to Article Structure

1. Navigate to **Article Structures** tab
2. Click **Add New** or **Edit** an existing structure
3. In the modal, locate the **Category** dropdown
4. Select a category (e.g., "How-To Guides")
5. Fill in other required fields
6. Click **Save Structure**
7. Verify the structure appears under the correct category heading

### 2. Assign Category to Structure Section

1. Navigate to **Structure Sections** tab
2. Click **Add New** or **Edit** an existing section
3. In the modal, locate the **Category** dropdown
4. Select a category (e.g., "Tutorials")
5. Fill in other required fields
6. Click **Save Section**
7. Verify the section appears under the correct category heading

### 3. Test Uncategorized Items

1. Create a structure without selecting a category (or select "-- No Category --")
2. Create a section without selecting a category
3. Verify both items appear under the "Uncategorized" heading at the bottom

## Testing Category Grouping Display

### 1. Article Structures Display

1. Navigate to **Article Structures** tab
2. Verify structures are grouped by category
3. Each category should have:
   - A category heading (colored bar with category name)
   - A table with structures in that category
4. Verify "Uncategorized" section appears last
5. Verify empty categories are not displayed

### 2. Structure Sections Display

1. Navigate to **Structure Sections** tab
2. Verify sections are grouped by category
3. Each category should have:
   - A category heading (colored bar with category name)
   - A table with sections in that category
4. Verify "Uncategorized" section appears last
5. Verify empty categories are not displayed

## Edge Cases to Test

### 1. Empty States
- Test Categories tab with no categories
- Test Structures tab with no structures
- Test Sections tab with no sections

### 2. Category Deletion Impact
1. Create a category and assign it to structures and sections
2. Delete the category
3. Verify structures and sections move to "Uncategorized"
4. Verify no data is lost (structures and sections remain intact)

### 3. Multiple Categories
1. Create 5+ categories
2. Assign different structures and sections to different categories
3. Verify grouping works correctly with many categories
4. Verify categories are displayed in alphabetical order

### 4. Category Dropdown Updates
1. Create a new category
2. Open the structure or section form
3. Verify the new category appears in the dropdown
4. Delete a category (after moving items away from it)
5. Open the form again
6. Verify the deleted category is removed from the dropdown

## Expected Results

### Visual Elements

1. **Category Headings**: Should have:
   - Gray background (#f0f0f1)
   - Blue left border (#2271b1)
   - Bold text
   - Proper spacing above and below

2. **Tables**: Each category's table should be clearly separated

3. **Uncategorized Section**: Should always appear last

### Functional Requirements

1. All CRUD operations for categories should work without errors
2. Category assignment should persist after page reload
3. Grouping should be maintained after page reload
4. Deleting a category should not delete structures/sections
5. Category dropdown should reflect current categories

## Troubleshooting

### Categories Not Saving
- Check browser console for JavaScript errors
- Verify AJAX nonce is valid
- Check server logs for PHP errors

### Structures/Sections Not Grouping
- Verify database schema was updated (check for `category_id` column)
- Check that category IDs are being saved to database
- Clear browser cache and reload

### Dropdown Not Populating
- Verify categories exist in database
- Check that `$categories` variable is passed to template
- Verify WordPress taxonomy is registered

## Database Verification

To verify the database schema is correct, run these SQL queries:

```sql
-- Check structures table has category_id column
DESCRIBE wp_aips_article_structures;

-- Check sections table has category_id column  
DESCRIBE wp_aips_prompt_sections;

-- Check categories exist in terms table
SELECT * FROM wp_terms WHERE term_id IN (
    SELECT term_id FROM wp_term_taxonomy WHERE taxonomy = 'aips_structure_category'
);
```

## Completion Checklist

- [ ] Database schema updated successfully
- [ ] Can create categories
- [ ] Can edit categories  
- [ ] Can delete categories
- [ ] Can assign categories to structures
- [ ] Can assign categories to sections
- [ ] Structures display grouped by category
- [ ] Sections display grouped by category
- [ ] Uncategorized items appear at bottom
- [ ] Category dropdown populates correctly
- [ ] No JavaScript errors in console
- [ ] No PHP errors in logs
- [ ] Feature works after page reload
- [ ] Category deletion doesn't lose structures/sections
