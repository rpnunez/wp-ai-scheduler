# Template Wizard Implementation

## Overview
This document describes the multi-step wizard implementation for the Template Add/Edit modal in the AI Post Scheduler plugin.

## Changes Made

### 1. Database Schema
- **Added**: `description` field to `aips_templates` table (TEXT, nullable)
- **Migration**: Automatic via `dbDelta()` on plugin upgrade

### 2. Backend (PHP)

#### Files Modified:
- `includes/class-aips-db-manager.php` - Added description column to schema
- `includes/class-aips-templates.php` - Updated save() method to handle description
- `includes/class-aips-templates-controller.php` - Updated AJAX handlers to accept/return description

### 3. Frontend Structure (templates.php)

#### New Wizard Structure:
The single-page form has been replaced with a 5-step wizard:

**Step 1: Basic Information**
- Template Name (required)
- Template Description (optional, new field)

**Step 2: Title & Excerpt Settings**
- Title Prompt (optional)
- AI Variables panel
- Usage instructions

**Step 3: Content Settings**
- Content Prompt (required)
- Voice selection
- Post quantity

**Step 4: Featured Image Options**
- Enable/disable featured image
- Image source selection (AI, Unsplash, Media Library)
- Source-specific settings

**Step 5: Summary & Post Settings**
- Read-only summary of all settings
- Post status, category, tags, author
- Active/inactive toggle

#### Progress Indicator
- Visual stepper showing all 5 steps
- Active step highlighted in blue
- Completed steps shown with green checkmark
- Progress line connecting steps

#### Navigation
- **Back Button**: Navigate to previous step (hidden on step 1)
- **Next Button**: Navigate to next step (validates current step)
- **Save Template Button**: Shown only on final step
- **Cancel Button**: Close modal

### 4. JavaScript (admin.js)

#### New Functions:
- `wizardGoToStep(step)` - Navigate to specific step, update UI
- `wizardNext()` - Advance to next step with validation
- `wizardBack()` - Return to previous step
- `validateWizardStep(step)` - Validate required fields for current step
- `updateWizardSummary()` - Populate summary on final step

#### Updated Functions:
- `openTemplateModal()` - Initialize wizard to step 1
- `editTemplate()` - Load existing template data and initialize wizard
- `saveTemplate()` - Include description field in AJAX save

#### Event Bindings:
- Click on `.aips-wizard-next` → `wizardNext()`
- Click on `.aips-wizard-back` → `wizardBack()`

### 5. CSS Styling (admin.css)

#### New Styles:
- `.aips-wizard-modal` - Larger modal for wizard
- `.aips-wizard-progress` - Progress indicator container
- `.aips-wizard-step` - Individual step indicator
- `.aips-step-number` - Circular step number
- `.aips-step-label` - Step title
- `.aips-wizard-step-content` - Step content container
- `.aips-template-summary` - Summary display box
- `.aips-summary-grid` - Summary items layout
- Animation for step transitions

#### Color Scheme:
- Active step: Blue (#2271b1)
- Completed step: Green (#00a32a)
- Inactive step: Gray (#646970)

## User Experience Flow

### Creating a New Template:
1. Click "Add New" button
2. **Step 1**: Enter template name and optional description
3. Click "Next" (validates name is not empty)
4. **Step 2**: Optionally configure title prompt
5. Click "Next"
6. **Step 3**: Enter content prompt (required), select voice, set quantity
7. Click "Next" (validates content prompt)
8. **Step 4**: Configure featured image settings
9. Click "Next"
10. **Step 5**: Review summary and set post settings
11. Click "Save Template"
12. Template is saved and page reloads

### Editing an Existing Template:
1. Click "Edit" button on template
2. Modal loads with all existing data
3. Wizard starts at Step 1
4. User can navigate through all steps using Back/Next
5. Make changes to any step
6. Review on Step 5
7. Save changes

## Validation

### Step 1 Validation:
- Template Name: Required, cannot be empty

### Step 3 Validation:
- Content Prompt: Required, cannot be empty

### Other Steps:
- No validation (all fields optional)

## Technical Notes

### Database Migration:
- The `description` column is added automatically via WordPress's `dbDelta()` function
- No manual migration required
- Existing templates will have NULL description (safe)

### Backward Compatibility:
- Existing templates work without description field
- Old data loads correctly into wizard
- Save operation includes description even if empty

### Browser Support:
- Modern browsers (Chrome, Firefox, Safari, Edge)
- Uses CSS Grid for layout
- CSS animations for transitions
- JavaScript ES5+ features

## Testing Checklist

- [x] PHP syntax validation
- [x] JavaScript syntax validation
- [ ] Create new template through wizard
- [ ] Edit existing template
- [ ] Navigate forward through all steps
- [ ] Navigate backward through steps
- [ ] Test step 1 validation (empty name)
- [ ] Test step 3 validation (empty content prompt)
- [ ] Verify summary displays correctly
- [ ] Verify all data saves correctly
- [ ] Test with featured image enabled/disabled
- [ ] Test with different image sources
- [ ] Verify description field saves and loads
- [ ] Test closing modal mid-wizard
- [ ] Test on mobile/tablet screens

## Future Enhancements

Potential improvements for future versions:
- Add progress percentage indicator
- Save draft progress between steps
- Add "Skip" button for optional steps
- Add tooltips on each step
- Add keyboard navigation (arrow keys)
- Add step completion indicators beyond required fields
- Add ability to jump to specific step (if all previous validated)
- Add form field grouping animation
- Add more detailed validation messages
