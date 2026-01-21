# Category Feature UI Mockup

This document provides a visual reference for the category feature UI.

## Categories Tab

```
┌─────────────────────────────────────────────────────────────┐
│ Article Structures                                          │
└─────────────────────────────────────────────────────────────┘

[Article Structures] [Structure Sections] [Categories*]

┌─────────────────────────────────────────────────────────────┐
│ Categories                                    [+ Add New]    │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ Name              │ Description                 │ Actions    │
├───────────────────┼─────────────────────────────┼────────────┤
│ How-To Guides     │ Step-by-step instructions   │ [Edit] [Del]│
│ Tutorials         │ In-depth learning content   │ [Edit] [Del]│
│ Reference Docs    │ API and technical docs      │ [Edit] [Del]│
│ Opinion Pieces    │ Editorial content           │ [Edit] [Del]│
└─────────────────────────────────────────────────────────────┘
```

## Article Structures Tab (With Grouping)

```
┌─────────────────────────────────────────────────────────────┐
│ Article Structures                                          │
└─────────────────────────────────────────────────────────────┘

[Article Structures*] [Structure Sections] [Categories]

┌─────────────────────────────────────────────────────────────┐
│ Article Structures                            [+ Add New]    │
└─────────────────────────────────────────────────────────────┘

╔═════════════════════════════════════════════════════════════╗
║ How-To Guides                                               ║
╚═════════════════════════════════════════════════════════════╝
┌─────────────────────────────────────────────────────────────┐
│ Name         │ Description        │ Active │ Default│ Actions│
├──────────────┼────────────────────┼────────┼────────┼────────┤
│ How-To Guide │ Step-by-step guide │ Yes    │ Yes    │[Ed][Del]│
│ Quick Start  │ Fast tutorial      │ Yes    │ No     │[Ed][Del]│
└─────────────────────────────────────────────────────────────┘

╔═════════════════════════════════════════════════════════════╗
║ Tutorials                                                   ║
╚═════════════════════════════════════════════════════════════╝
┌─────────────────────────────────────────────────────────────┐
│ Name         │ Description        │ Active │ Default│ Actions│
├──────────────┼────────────────────┼────────┼────────┼────────┤
│ Tutorial     │ In-depth tutorial  │ Yes    │ No     │[Ed][Del]│
└─────────────────────────────────────────────────────────────┘

╔═════════════════════════════════════════════════════════════╗
║ Uncategorized                                               ║
╚═════════════════════════════════════════════════════════════╝
┌─────────────────────────────────────────────────────────────┐
│ Name         │ Description        │ Active │ Default│ Actions│
├──────────────┼────────────────────┼────────┼────────┼────────┤
│ Listicle     │ List-based article │ Yes    │ No     │[Ed][Del]│
│ Case Study   │ Real-world example │ Yes    │ No     │[Ed][Del]│
└─────────────────────────────────────────────────────────────┘
```

## Structure Sections Tab (With Grouping)

```
┌─────────────────────────────────────────────────────────────┐
│ Article Structures                                          │
└─────────────────────────────────────────────────────────────┘

[Article Structures] [Structure Sections*] [Categories]

┌─────────────────────────────────────────────────────────────┐
│ Structure Sections                            [+ Add New]    │
└─────────────────────────────────────────────────────────────┘

╔═════════════════════════════════════════════════════════════╗
║ How-To Guides                                               ║
╚═════════════════════════════════════════════════════════════╝
┌─────────────────────────────────────────────────────────────┐
│ Name         │ Key         │ Description    │ Active│ Actions│
├──────────────┼─────────────┼────────────────┼───────┼────────┤
│ Prerequisites│ prerequi... │ Required tools │ Yes   │[Ed][Del]│
│ Steps        │ steps       │ Main procedure │ Yes   │[Ed][Del]│
└─────────────────────────────────────────────────────────────┘

╔═════════════════════════════════════════════════════════════╗
║ Tutorials                                                   ║
╚═════════════════════════════════════════════════════════════╝
┌─────────────────────────────────────────────────────────────┐
│ Name         │ Key         │ Description    │ Active│ Actions│
├──────────────┼─────────────┼────────────────┼───────┼────────┤
│ Examples     │ examples    │ Code samples   │ Yes   │[Ed][Del]│
└─────────────────────────────────────────────────────────────┘

╔═════════════════════════════════════════════════════════════╗
║ Uncategorized                                               ║
╚═════════════════════════════════════════════════════════════╝
┌─────────────────────────────────────────────────────────────┐
│ Name         │ Key         │ Description    │ Active│ Actions│
├──────────────┼─────────────┼────────────────┼───────┼────────┤
│ Introduction │ introduction│ Opening text   │ Yes   │[Ed][Del]│
│ Conclusion   │ conclusion  │ Closing text   │ Yes   │[Ed][Del]│
└─────────────────────────────────────────────────────────────┘
```

## Add/Edit Structure Modal (With Category Dropdown)

```
┌─────────────────────────────────────────────────────────────┐
│ Add New Article Structure                              [X]  │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Name *                                                     │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                                                      │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Description                                                │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                                                      │   │
│  │                                                      │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Category                                                   │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ -- No Category --                               [▼] │   │
│  └─────────────────────────────────────────────────────┘   │
│      • -- No Category --                                    │
│      • How-To Guides                                        │
│      • Tutorials                                            │
│      • Reference Docs                                       │
│      • Opinion Pieces                                       │
│                                                             │
│  Sections (Select one or more)                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Introduction                                         │   │
│  │ Prerequisites                                        │   │
│  │ Steps                                                │   │
│  │ Examples                                             │   │
│  │ Tips                                                 │   │
│  └─────────────────────────────────────────────────────┘   │
│  Hold Ctrl (Cmd on Mac) to select multiple items           │
│                                                             │
│  Prompt Template *                                          │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                                                      │   │
│  │                                                      │   │
│  │                                                      │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  ☑ Active                                                   │
│  ☐ Set as Default                                           │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│                              [Cancel] [Save Structure]      │
└─────────────────────────────────────────────────────────────┘
```

## Add/Edit Section Modal (With Category Dropdown)

```
┌─────────────────────────────────────────────────────────────┐
│ Add New Prompt Section                                 [X]  │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Name *                                                     │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                                                      │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Key *                                                      │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                                                      │   │
│  └─────────────────────────────────────────────────────┘   │
│  Use a unique key. Lowercase letters, numbers, and          │
│  underscores recommended.                                   │
│                                                             │
│  Description                                                │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                                                      │   │
│  │                                                      │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Category                                                   │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ -- No Category --                               [▼] │   │
│  └─────────────────────────────────────────────────────┘   │
│      • -- No Category --                                    │
│      • How-To Guides                                        │
│      • Tutorials                                            │
│      • Reference Docs                                       │
│      • Opinion Pieces                                       │
│                                                             │
│  Content *                                                  │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                                                      │   │
│  │                                                      │   │
│  │                                                      │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  ☑ Active                                                   │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│                              [Cancel] [Save Section]        │
└─────────────────────────────────────────────────────────────┘
```

## Category Modal

```
┌─────────────────────────────────────────────────────────────┐
│ Add New Category                                       [X]  │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Name *                                                     │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                                                      │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Description                                                │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                                                      │   │
│  │                                                      │   │
│  │                                                      │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│                              [Cancel] [Save Category]       │
└─────────────────────────────────────────────────────────────┘
```

## Visual Design Notes

### Category Headings
- **Background**: Light gray (#f0f0f1)
- **Left Border**: 4px solid blue (#2271b1)
- **Font**: 14px, bold, dark gray (#1d2327)
- **Padding**: 10px 15px
- **Spacing**: 20px margin top (except first), 10px margin bottom

### Tables
- Standard WordPress `.wp-list-table` styling
- Striped rows for better readability
- Each category group has its own table
- Bottom border on last row of each table

### Tabs
- Standard WordPress admin tabs (`.nav-tab-wrapper`)
- Active tab highlighted with `.nav-tab-active`
- Three tabs: Article Structures, Structure Sections, Categories

### Modals
- Centered overlay with semi-transparent backdrop
- White background with shadow
- Header with title and close button
- Form body with proper spacing
- Footer with action buttons aligned right

### Category Dropdown
- Standard WordPress select styling
- "-- No Category --" as first option (value: 0)
- All categories listed alphabetically
- Currently selected value highlighted

### Empty States
- Icon (dashicon) centered
- Heading and description text
- Primary action button
- Used when no items exist in a list

## Responsive Behavior

- Tables scroll horizontally on small screens
- Modals are responsive and centered
- Forms stack vertically on mobile
- Tab navigation wraps if needed
- Category headings remain full width

## Accessibility

- All buttons have proper ARIA labels
- Modals can be closed with Escape key
- Form fields have associated labels
- Focus management in modals
- Keyboard navigation supported
- Screen reader friendly structure
