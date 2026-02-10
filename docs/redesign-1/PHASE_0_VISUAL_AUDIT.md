# Phase 0 — Visual Audit & UI Inventory

**Date:** 2026-02-10  
**Status:** Completed  
**Author:** Copilot Agent

## Executive Summary

This document provides a comprehensive audit of the AI Post Scheduler plugin's current UI/UX, establishing a baseline before implementing the "WP Media Cleaner style" redesign. The plugin currently has 19 distinct admin pages with a mix of tables, forms, modals, and dashboard elements.

---

## Admin Pages Inventory

### Core Pages (19 Total)

| # | Page Name | Slug | Template File | Primary Purpose |
|---|-----------|------|---------------|-----------------|
| 1 | Dashboard | `ai-post-scheduler` | `dashboard.php` | Main hub with stats & activity |
| 2 | Templates | `aips-templates` | `templates.php` | Create/manage post templates |
| 3 | Schedule | `aips-schedule` | `schedule.php` | Configure automated schedules |
| 4 | Generated Posts | `aips-generated-posts` | `generated-posts.php` | View/manage AI-generated drafts |
| 5 | Authors | `aips-authors` | `authors.php` | Manage author profiles & topics |
| 6 | Voices | `aips-voices` | `voices.php` | Configure AI writing styles |
| 7 | Activity | `aips-activity` | `activity.php` | Recent activities log |
| 8 | History | (in main.php) | `history.php` | Generation history |
| 9 | Research | `aips-research` | `research.php` | Research topics |
| 10 | Planner | `aips-planner` | `planner.php` | Content planning |
| 11 | Post Review | (embedded) | `post-review.php` | Review workflow |
| 12 | Structures | `aips-structures` | `structures.php` | Article structure templates |
| 13 | Sections | `aips-sections` | `sections.php` | Content sections |
| 14 | Seeder | `aips-seeder` | `seeder.php` | Bulk generation |
| 15 | System Status | `aips-status` | `system-status.php` | Plugin health monitoring |
| 16 | Dev Tools | `aips-dev-tools` | `dev-tools.php` | Development utilities |
| 17 | Settings | (tab in main) | `settings.php` | Plugin configuration |
| 18 | Main | `ai-post-scheduler` | `main.php` | Tab navigation wrapper |

---

## UI Elements Inventory

### 1. Page Layouts

#### Current Structure
```
.wrap.aips-wrap
  └─ h1 (Page Title + Action Button)
  └─ Content Area (varies by page)
```

**Pages by Layout Type:**
- **Dashboard Layout:** Dashboard (stats grid + 2-column cards)
- **Table Layout:** Templates, Schedule, Generated Posts, Authors, History
- **Form Layout:** Settings, Voices, Structures
- **Tab Layout:** Main (Templates/Schedule/History tabs)
- **Mixed Layout:** Research, Planner, Seeder

### 2. Tables & Lists

**Current Implementation:**
- Uses WordPress `.wp-list-table.widefat.fixed.striped`
- Standard columns: Name, Status, Category, Stats, Actions
- Inline row actions (Edit, Delete, Clone, Run Now)
- No sticky headers
- Basic zebra striping via WordPress defaults

**Example (Templates Page):**
```html
<table class="wp-list-table widefat fixed striped">
  <thead>
    <tr>
      <th>Name</th>
      <th>Post Status</th>
      <th>Category</th>
      <th>Statistics</th>
      <th>Active</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <!-- Row with multiple action buttons -->
  </tbody>
</table>
```

### 3. Status Badges

**Current Classes:**
- `.aips-status-active` (green)
- `.aips-status-inactive` (gray)
- `.aips-status-pending` (yellow)
- `.aips-status-failed` (red)
- `.aips-status-completed` (green)

**Current Styling:**
```css
.aips-status {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}
```

### 4. Cards & Panels

**Dashboard Stat Cards:**
```css
.aips-stat-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}
```

**Content Cards:**
```css
.aips-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
}
```

### 5. Buttons & Actions

**Button Types:**
- `.button` (standard)
- `.button-primary` (primary CTA)
- `.button-link-delete` (destructive action)
- `.page-title-action` (header button)

**Action Button Groups:**
```html
<button class="button aips-edit-template">Edit</button>
<button class="button aips-run-now">Run Now</button>
<button class="button aips-clone-template">Clone</button>
<button class="button button-link-delete aips-delete-template">Delete</button>
```

### 6. Modals

**Current Structure:**
```html
<div class="aips-modal-overlay">
  <div class="aips-modal-content">
    <div class="aips-modal-header">
      <h2>Title</h2>
      <button class="aips-modal-close">×</button>
    </div>
    <div class="aips-modal-body">
      <!-- Form or content -->
    </div>
    <div class="aips-modal-footer">
      <!-- Action buttons -->
    </div>
  </div>
</div>
```

**Modal Types:**
- Template creation wizard (multi-step)
- Schedule creation
- Confirmation dialogs
- Preview panels

### 7. Forms

**Form Elements:**
- Text inputs (`.regular-text`)
- Textareas
- Select dropdowns
- Checkboxes
- Radio buttons
- File upload (media library integration)

**Form Sections:**
- Basic structure with labels and inputs
- Some have section headings
- Inline help text in `<p class="description">`

### 8. Tabs & Navigation

**Current Tab Implementation (main.php):**
```html
<h2 class="nav-tab-wrapper">
  <a href="?page=ai-post-scheduler&tab=templates" class="nav-tab">Templates</a>
  <a href="?page=ai-post-scheduler&tab=schedules" class="nav-tab">Schedules</a>
  <a href="?page=ai-post-scheduler&tab=history" class="nav-tab">History</a>
</h2>
```

### 9. Search & Filters

**Current Implementation:**
- Basic search box: `<input type="search" class="regular-text">`
- No advanced filtering UI
- No filter chips or active filter display
- Clear button appears on search

### 10. Empty States

**Current Empty States:**
```html
<p class="aips-no-data">No scheduled posts yet.</p>
<a href="..." class="button button-primary">Create Schedule</a>
```

**Empty State Classes:**
- `.aips-empty-state`
- `.aips-no-data`

### 11. Icons

**Icon System:**
- Uses WordPress Dashicons exclusively
- Common icons: `dashicons-edit`, `dashicons-clock`, `dashicons-media-document`, `dashicons-warning`
- Icon usage: `<span class="dashicons dashicons-edit"></span>`

---

## Current Design Tokens

### Colors

**Primary Colors:**
- Primary Blue: `#2271b1` (links, primary buttons)
- Success Green: `#00a32a`
- Warning Yellow: `#dba617`
- Error Red: `#d63638`

**Neutral Colors:**
- Dark Text: `#1d2327`
- Medium Text: `#646970`
- Light Text: `#787c82`
- Border: `#c3c4c7`
- Background: `#f0f0f1`
- White: `#fff`

### Typography

**Font Family:** System font stack (inherits from WordPress)

**Sizes:**
- Page Title (h1): 23px
- Section Title (h2): 18px
- Body Text: 13px
- Meta Text: 12px
- Large Numbers: 28px (stat cards)

**Weights:**
- Normal: 400
- Medium: 500
- Semibold: 600

### Spacing

**Scale:**
- XS: 5px
- S: 10px
- M: 15px
- L: 20px
- XL: 30px

**Common Usage:**
- Card padding: 20px
- Grid gap: 20px
- Button padding: Variable (WordPress defaults)

### Borders & Radius

**Border Radius:**
- Cards: 4px
- Buttons: 3px (WordPress default)
- Status badges: 3px

**Borders:**
- Width: 1px
- Color: `#c3c4c7`
- Style: solid

### Shadows

**Currently Used:**
- Minimal shadows
- Relies on borders for separation

---

## Common UI Patterns

### 1. Dashboard Pattern
- **Stats Grid** → 4-column auto-fit grid with stat cards
- **Two-Column Layout** → Side-by-side content cards
- **Empty State** → Message + CTA button

### 2. List Page Pattern
- **Page Header** → Title + "Add New" button
- **Search Box** → Right-aligned above table
- **Data Table** → Full-width striped table
- **Row Actions** → Multiple buttons per row
- **Status Display** → Colored badge in table cell

### 3. Form Pattern
- **Section Headers** → h2 or h3 headings
- **Field Groups** → Table-based layout (`.form-table`)
- **Help Text** → `.description` paragraphs
- **Submit Buttons** → Footer with primary button

### 4. Modal Pattern
- **Overlay** → Full-screen dark overlay
- **Centered Content** → White modal box
- **Header** → Title + close button
- **Body** → Scrollable content
- **Footer** → Right-aligned action buttons

---

## Accessibility Features

**Current Implementation:**
- Screen reader text: `.screen-reader-text`
- ARIA labels on icons: `aria-hidden="true"`
- Semantic HTML: proper heading hierarchy
- Keyboard navigation: WordPress defaults
- Focus states: WordPress defaults

---

## Responsive Behavior

**Current Breakpoints:**
- Desktop: 1200px+ (stats grid shows 4 columns)
- Tablet: 782px - 1199px (dashboard adjusts)
- Mobile: < 782px (WordPress mobile menu)

**Grid Behavior:**
- `grid-template-columns: repeat(auto-fit, minmax(200px, 1fr))`
- Cards stack on smaller screens

---

## JavaScript Architecture

**Module Pattern:**
```javascript
window.AIPS = Object.assign(window.AIPS || {}, {
    Templates: { /* methods */ },
    Schedules: { /* methods */ },
    // etc.
});
```

**Event Handling:**
- jQuery-based event delegation
- AJAX requests with `wp.ajax` or `$.ajax`
- Confirmation dialogs before destructive actions

---

## CSS Files Structure

### `admin.css` (900+ lines)
- Dashboard styles
- Card components
- Table styles
- Modal system
- Form elements
- Status badges
- Wizard/stepper components

### `admin-fixing.css`
- Additional refinements
- Bug fixes
- Override styles

### `authors.css`
- Authors page specific styles
- Kanban board layout
- Topic management UI

---

## Current Pain Points

### Visual Consistency
- ❌ Inconsistent spacing between pages
- ❌ Mixed design patterns (some cards, some plain tables)
- ❌ Button styles vary by context
- ❌ Modal designs not fully standardized

### Usability
- ❌ No sticky headers on long tables
- ❌ Row actions take up significant space
- ❌ No bulk actions UI for some tables
- ❌ Empty states are plain text only
- ❌ No visual hierarchy in forms

### Modern UI Expectations
- ❌ Tables feel dated (full-width, basic styling)
- ❌ No compact view options
- ❌ Limited use of icons and visual cues
- ❌ No status summary panels
- ❌ Filter UI is basic search only

---

## Opportunities for Improvement

### Layout
1. **Container System:** Implement framed containers instead of full-width pages
2. **Header Block:** Standardized header with description + primary action
3. **Sidebar Panels:** Add contextual info panels where helpful

### Tables
1. **Compact Design:** Reduce row height and padding
2. **Hover Effects:** Better row interaction feedback
3. **Sticky Filters:** Keep filter bar visible on scroll
4. **Inline Metadata:** Show more info without expanding rows
5. **Action Menus:** Compact actions into dropdown menus

### Status & Feedback
1. **Modern Badges:** Redesign status badges with icons
2. **Summary Panels:** Add overview stats at top of lists
3. **Empty States:** Design proper empty state cards with illustrations/icons

### Forms & Modals
1. **Section Design:** Add icons and descriptions to form sections
2. **Toggle Switches:** Replace checkboxes with modern toggles
3. **Modal Refinement:** Improve modal spacing and button placement

### Interaction
1. **Quick Actions:** Floating action button or toolbar
2. **Tooltips:** Add contextual help on hover
3. **Loading States:** Better feedback during AJAX operations

---

## Baseline Screenshots

**TODO:** Capture screenshots of all major pages:
- [ ] Dashboard
- [ ] Templates list
- [ ] Schedule list
- [ ] Generated Posts
- [ ] Authors page
- [ ] Template creation modal
- [ ] Settings page
- [ ] Forms (template wizard)

---

## Next Steps

1. ✅ Complete visual audit
2. → Proceed to Phase 1: Design System & UI Tokens
3. → Create design token definitions
4. → Begin implementation with pilot page (Dashboard recommended)

---

## Notes

- Plugin currently follows WordPress admin conventions closely
- Strong foundation to build upon
- Main challenge: Maintaining WordPress compatibility while adding custom design layer
- Must ensure all AJAX functionality continues to work with new UI
- Accessibility must be maintained or improved

