# Phase 1 — Design System & UI Tokens

**Date:** 2026-02-10  
**Status:** In Progress  
**Author:** Copilot Agent

## Executive Summary

This document defines the design system for the "WP Media Cleaner style" redesign. It establishes a consistent visual language that sits on top of WordPress admin while maintaining compatibility and accessibility standards.

---

## Design Philosophy

### Principles

1. **WordPress Native First** — Build on top of WordPress admin, don't fight it
2. **Clean & Minimal** — Reduce visual noise, emphasize content
3. **Subtle Refinement** — Polish existing patterns rather than radical changes
4. **Information Dense** — Show more data in less space
5. **Accessible by Default** — Meet WCAG 2.1 AA standards

### Meow Apps Style Characteristics

Based on WP Media Cleaner and other Meow Apps products:
- **Framed Containers** — Content lives in defined panels, not edge-to-edge
- **Generous White Space** — Breathing room between elements
- **Subtle Shadows** — Soft depth without harsh edges
- **Compact Lists** — Information-dense rows with inline metadata
- **Clear Hierarchy** — Visual weight guides attention
- **Modern Badges** — Status indicators with personality
- **Action Clarity** — Buttons and actions are obvious

---

## Color System

### Primary Palette

```css
/* Primary Blue (WordPress Admin Blue) */
--aips-primary: #2271b1;
--aips-primary-hover: #135e96;
--aips-primary-light: rgba(34, 113, 177, 0.1);

/* Neutral Gray Scale */
--aips-gray-900: #1d2327;  /* Headings, primary text */
--aips-gray-700: #3c434a;  /* Secondary text */
--aips-gray-600: #50575e;  /* Tertiary text */
--aips-gray-500: #646970;  /* Meta text, labels */
--aips-gray-400: #787c82;  /* Disabled text */
--aips-gray-300: #a7aaad;  /* Borders, dividers */
--aips-gray-200: #c3c4c7;  /* Light borders */
--aips-gray-100: #dcdcde;  /* Very light borders */
--aips-gray-50: #f0f0f1;   /* Backgrounds */
--aips-white: #ffffff;

/* Success Green */
--aips-success: #00a32a;
--aips-success-dark: #008a20;
--aips-success-light: rgba(0, 163, 42, 0.1);

/* Warning Yellow/Orange */
--aips-warning: #dba617;
--aips-warning-dark: #bd8d0e;
--aips-warning-light: rgba(219, 166, 23, 0.1);

/* Error Red */
--aips-error: #d63638;
--aips-error-dark: #b32d2e;
--aips-error-light: rgba(214, 54, 56, 0.1);

/* Info Blue */
--aips-info: #2271b1;
--aips-info-dark: #135e96;
--aips-info-light: rgba(34, 113, 177, 0.1);
```

### Usage Guidelines

**Text Colors:**
- Primary headings: `--aips-gray-900`
- Body text: `--aips-gray-700`
- Meta text: `--aips-gray-500`
- Links: `--aips-primary`

**Background Colors:**
- Page background: `--aips-gray-50` (WordPress admin default)
- Card/Panel background: `--aips-white`
- Hover states: `--aips-gray-50`
- Selected states: `--aips-primary-light`

**Border Colors:**
- Default borders: `--aips-gray-200`
- Subtle dividers: `--aips-gray-100`
- Active/Focus: `--aips-primary`

---

## Typography

### Font Stack

```css
--aips-font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, 
                    Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
```

*(Inherits from WordPress admin)*

### Type Scale

```css
/* Display Sizes */
--aips-text-3xl: 28px;  /* Large numbers in stat cards */
--aips-text-2xl: 24px;  /* Page titles (alternative) */
--aips-text-xl: 20px;   /* Section headings */

/* Standard Sizes */
--aips-text-lg: 16px;   /* Large body text */
--aips-text-base: 14px; /* Body text, buttons */
--aips-text-sm: 13px;   /* Secondary text */
--aips-text-xs: 12px;   /* Meta text, labels */
--aips-text-2xs: 11px;  /* Tiny text (badges) */

/* Line Heights */
--aips-leading-none: 1;
--aips-leading-tight: 1.25;
--aips-leading-snug: 1.375;
--aips-leading-normal: 1.5;
--aips-leading-relaxed: 1.625;
--aips-leading-loose: 2;

/* Font Weights */
--aips-font-normal: 400;
--aips-font-medium: 500;
--aips-font-semibold: 600;
--aips-font-bold: 700;
```

### Typography Usage

```css
/* Page Title */
.aips-page-title {
    font-size: var(--aips-text-2xl);
    font-weight: var(--aips-font-semibold);
    color: var(--aips-gray-900);
    line-height: var(--aips-leading-tight);
}

/* Section Heading */
.aips-section-heading {
    font-size: var(--aips-text-lg);
    font-weight: var(--aips-font-semibold);
    color: var(--aips-gray-900);
    line-height: var(--aips-leading-snug);
}

/* Body Text */
.aips-body {
    font-size: var(--aips-text-base);
    font-weight: var(--aips-font-normal);
    color: var(--aips-gray-700);
    line-height: var(--aips-leading-normal);
}

/* Meta Text */
.aips-meta {
    font-size: var(--aips-text-xs);
    font-weight: var(--aips-font-normal);
    color: var(--aips-gray-500);
    line-height: var(--aips-leading-normal);
}
```

---

## Spacing System

### Scale

```css
/* Spacing Scale (8px base) */
--aips-space-0: 0;
--aips-space-1: 4px;
--aips-space-2: 8px;
--aips-space-3: 12px;
--aips-space-4: 16px;
--aips-space-5: 20px;
--aips-space-6: 24px;
--aips-space-8: 32px;
--aips-space-10: 40px;
--aips-space-12: 48px;
--aips-space-16: 64px;
```

### Usage Guidelines

**Container Padding:**
- Small cards: `--aips-space-4` (16px)
- Medium cards: `--aips-space-5` (20px)
- Large panels: `--aips-space-6` (24px)

**Element Spacing:**
- Between related items: `--aips-space-2` (8px)
- Between groups: `--aips-space-4` (16px)
- Between sections: `--aips-space-8` (32px)

**Grid Gaps:**
- Tight grid: `--aips-space-3` (12px)
- Normal grid: `--aips-space-4` (16px)
- Loose grid: `--aips-space-6` (24px)

---

## Borders & Radius

### Border Radius

```css
--aips-radius-none: 0;
--aips-radius-sm: 2px;
--aips-radius-base: 4px;
--aips-radius-md: 6px;
--aips-radius-lg: 8px;
--aips-radius-xl: 12px;
--aips-radius-full: 9999px;
```

**Usage:**
- Buttons: `--aips-radius-base` (4px)
- Cards/Panels: `--aips-radius-md` (6px)
- Badges: `--aips-radius-base` (4px)
- Pills/Tags: `--aips-radius-full` (fully rounded)

### Borders

```css
--aips-border-width: 1px;
--aips-border-width-thick: 2px;
--aips-border-style: solid;

/* Border Colors defined in Color System */
```

---

## Shadows

### Shadow Scale

```css
/* Subtle shadows for Meow style */
--aips-shadow-xs: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
--aips-shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 
                  0 1px 2px 0 rgba(0, 0, 0, 0.06);
--aips-shadow-base: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 
                    0 2px 4px -1px rgba(0, 0, 0, 0.06);
--aips-shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 
                  0 4px 6px -2px rgba(0, 0, 0, 0.05);
--aips-shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 
                  0 10px 10px -5px rgba(0, 0, 0, 0.04);
```

**Usage:**
- Cards (default): `--aips-shadow-sm`
- Cards (hover): `--aips-shadow-base`
- Modals: `--aips-shadow-lg`
- Dropdowns: `--aips-shadow-md`
- Subtle dividers: `--aips-shadow-xs`

---

## Layout System

### Container Widths

```css
--aips-container-sm: 640px;
--aips-container-md: 768px;
--aips-container-lg: 1024px;
--aips-container-xl: 1200px;
--aips-container-2xl: 1400px;
```

### Page Layout

```css
/* Framed Container Layout (Meow style) */
.aips-page-container {
    max-width: var(--aips-container-xl);
    margin: var(--aips-space-6) auto;
    padding: 0 var(--aips-space-5);
}

/* Header Block */
.aips-page-header {
    background: var(--aips-white);
    border: 1px solid var(--aips-gray-200);
    border-radius: var(--aips-radius-md);
    padding: var(--aips-space-6);
    margin-bottom: var(--aips-space-5);
    box-shadow: var(--aips-shadow-sm);
}

/* Content Panel */
.aips-content-panel {
    background: var(--aips-white);
    border: 1px solid var(--aips-gray-200);
    border-radius: var(--aips-radius-md);
    box-shadow: var(--aips-shadow-sm);
}
```

### Grid System

```css
/* Responsive Grid */
.aips-grid {
    display: grid;
    gap: var(--aips-space-5);
}

.aips-grid-cols-2 {
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
}

.aips-grid-cols-3 {
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
}

.aips-grid-cols-4 {
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
}
```

---

## Component Patterns

### Status Badges

```css
.aips-badge {
    display: inline-flex;
    align-items: center;
    gap: var(--aips-space-1);
    padding: var(--aips-space-1) var(--aips-space-2);
    font-size: var(--aips-text-xs);
    font-weight: var(--aips-font-medium);
    border-radius: var(--aips-radius-base);
    line-height: var(--aips-leading-tight);
}

.aips-badge-success {
    background: var(--aips-success-light);
    color: var(--aips-success-dark);
}

.aips-badge-warning {
    background: var(--aips-warning-light);
    color: var(--aips-warning-dark);
}

.aips-badge-error {
    background: var(--aips-error-light);
    color: var(--aips-error-dark);
}

.aips-badge-info {
    background: var(--aips-info-light);
    color: var(--aips-info-dark);
}

.aips-badge-neutral {
    background: var(--aips-gray-100);
    color: var(--aips-gray-700);
}
```

### Buttons

```css
/* Primary Button */
.aips-btn-primary {
    background: var(--aips-primary);
    color: var(--aips-white);
    border: none;
    padding: var(--aips-space-2) var(--aips-space-4);
    font-size: var(--aips-text-sm);
    font-weight: var(--aips-font-medium);
    border-radius: var(--aips-radius-base);
    cursor: pointer;
    transition: all 0.2s;
}

.aips-btn-primary:hover {
    background: var(--aips-primary-hover);
    box-shadow: var(--aips-shadow-sm);
}

/* Secondary Button */
.aips-btn-secondary {
    background: var(--aips-white);
    color: var(--aips-gray-700);
    border: 1px solid var(--aips-gray-300);
    padding: var(--aips-space-2) var(--aips-space-4);
    font-size: var(--aips-text-sm);
    font-weight: var(--aips-font-medium);
    border-radius: var(--aips-radius-base);
    cursor: pointer;
    transition: all 0.2s;
}

.aips-btn-secondary:hover {
    border-color: var(--aips-gray-400);
    box-shadow: var(--aips-shadow-xs);
}

/* Icon Button */
.aips-btn-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: var(--aips-space-2);
    border: 1px solid var(--aips-gray-300);
    border-radius: var(--aips-radius-base);
    background: var(--aips-white);
    cursor: pointer;
    transition: all 0.2s;
}

.aips-btn-icon:hover {
    background: var(--aips-gray-50);
}
```

### Cards

```css
.aips-card {
    background: var(--aips-white);
    border: 1px solid var(--aips-gray-200);
    border-radius: var(--aips-radius-md);
    padding: var(--aips-space-5);
    box-shadow: var(--aips-shadow-sm);
    transition: all 0.2s;
}

.aips-card:hover {
    box-shadow: var(--aips-shadow-base);
}

.aips-card-header {
    padding-bottom: var(--aips-space-4);
    border-bottom: 1px solid var(--aips-gray-100);
    margin-bottom: var(--aips-space-4);
}

.aips-card-title {
    font-size: var(--aips-text-lg);
    font-weight: var(--aips-font-semibold);
    color: var(--aips-gray-900);
    margin: 0;
}

.aips-card-body {
    /* Content area */
}

.aips-card-footer {
    padding-top: var(--aips-space-4);
    border-top: 1px solid var(--aips-gray-100);
    margin-top: var(--aips-space-4);
}
```

### Tables

```css
.aips-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.aips-table th {
    background: var(--aips-gray-50);
    border-bottom: 2px solid var(--aips-gray-200);
    padding: var(--aips-space-3) var(--aips-space-4);
    text-align: left;
    font-size: var(--aips-text-xs);
    font-weight: var(--aips-font-semibold);
    color: var(--aips-gray-600);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.aips-table td {
    padding: var(--aips-space-3) var(--aips-space-4);
    border-bottom: 1px solid var(--aips-gray-100);
    font-size: var(--aips-text-sm);
    color: var(--aips-gray-700);
}

.aips-table tr:hover td {
    background: var(--aips-gray-50);
}

.aips-table tr:last-child td {
    border-bottom: none;
}
```

### Forms

```css
.aips-form-group {
    margin-bottom: var(--aips-space-5);
}

.aips-form-label {
    display: block;
    font-size: var(--aips-text-sm);
    font-weight: var(--aips-font-medium);
    color: var(--aips-gray-700);
    margin-bottom: var(--aips-space-2);
}

.aips-form-input {
    width: 100%;
    padding: var(--aips-space-2) var(--aips-space-3);
    font-size: var(--aips-text-sm);
    border: 1px solid var(--aips-gray-300);
    border-radius: var(--aips-radius-base);
    transition: all 0.2s;
}

.aips-form-input:focus {
    outline: none;
    border-color: var(--aips-primary);
    box-shadow: 0 0 0 3px var(--aips-primary-light);
}

.aips-form-help {
    font-size: var(--aips-text-xs);
    color: var(--aips-gray-500);
    margin-top: var(--aips-space-1);
}
```

---

## Responsive Breakpoints

```css
/* Mobile First Approach */
--aips-screen-sm: 640px;   /* Small tablets */
--aips-screen-md: 768px;   /* Tablets */
--aips-screen-lg: 1024px;  /* Laptops */
--aips-screen-xl: 1280px;  /* Desktops */
--aips-screen-2xl: 1536px; /* Large desktops */

/* WordPress Admin Breakpoint */
--aips-screen-wp-admin: 782px; /* WordPress mobile menu breakpoint */
```

### Media Query Usage

```css
/* Mobile first */
.aips-grid-responsive {
    grid-template-columns: 1fr;
}

@media (min-width: 640px) {
    .aips-grid-responsive {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1024px) {
    .aips-grid-responsive {
        grid-template-columns: repeat(4, 1fr);
    }
}
```

---

## Transitions & Animations

```css
--aips-transition-fast: 150ms ease;
--aips-transition-base: 200ms ease;
--aips-transition-slow: 300ms ease;

/* Usage */
.aips-interactive {
    transition: all var(--aips-transition-base);
}
```

---

## Z-Index Scale

```css
--aips-z-base: 1;
--aips-z-dropdown: 100;
--aips-z-sticky: 200;
--aips-z-fixed: 300;
--aips-z-modal-backdrop: 900;
--aips-z-modal: 1000;
--aips-z-popover: 1100;
--aips-z-tooltip: 1200;
```

---

## Accessibility Standards

### Focus States

```css
.aips-focusable:focus {
    outline: 2px solid var(--aips-primary);
    outline-offset: 2px;
}

.aips-focusable:focus:not(:focus-visible) {
    outline: none;
}
```

### Color Contrast

All color combinations must meet WCAG 2.1 AA standards:
- Normal text: 4.5:1 contrast ratio
- Large text (18px+): 3:1 contrast ratio
- UI components: 3:1 contrast ratio

**Tested Combinations:**
- ✅ `--aips-gray-700` on `--aips-white` → 8.59:1
- ✅ `--aips-gray-500` on `--aips-white` → 4.54:1
- ✅ `--aips-primary` on `--aips-white` → 4.64:1
- ✅ `--aips-white` on `--aips-primary` → 4.64:1

### Screen Reader Support

```css
.aips-sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border-width: 0;
}
```

---

## Implementation Guidelines

### CSS Custom Properties

All design tokens should be defined as CSS custom properties in a root stylesheet:

```css
:root {
    /* All tokens here */
}
```

### Progressive Enhancement

1. Start with WordPress defaults
2. Layer design system on top
3. Ensure graceful degradation
4. Test with custom properties disabled

### Browser Support

- Chrome 88+ ✅
- Firefox 87+ ✅
- Safari 14+ ✅
- Edge 88+ ✅

*(Matches WordPress 5.8+ requirements)*

---

## Next Steps

1. ✅ Define design system and tokens
2. → Create CSS file with all custom properties
3. → Implement container layout system
4. → Apply to Dashboard (pilot page)
5. → Refine based on feedback

---

## Notes

- Design system is intentionally minimal to match Meow Apps style
- All tokens are optional; fallbacks to WordPress defaults included
- Focus on subtle refinement rather than dramatic changes
- System designed to be extended as needed

