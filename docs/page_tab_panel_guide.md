# Page Tab Panel Guide

This guide explains how to use the seamless **Page Tab Panel** component in the AI Post Scheduler admin views. This design eliminates visual margins, gaps, and double borders, merging active navigation tabs directly into the white container body below them.

---

## Layout Architecture

The layout relies on a parent tab bar immediately followed by a tab content wrapper.

### HTML Structure
To implement this layout, use the following markup pattern in your templates:

```html
<!-- Tab Navigation Bar -->
<div class="aips-tab-nav">
    <!-- Active Link -->
    <a href="#" class="aips-tab-link active">Active Tab</a>
    <!-- Inactive Link -->
    <a href="#" class="aips-tab-link">Inactive Tab</a>
</div>

<!-- Page Tab Panel Wrapper -->
<div class="aips-page-tab-panel">
    <!-- Inner panels (if any) will have their outer border decoration stripped automatically -->
    <div class="aips-content-panel">
        <div class="aips-filter-bar">
            <!-- Filter Actions -->
        </div>
        <div class="aips-panel-body">
            <!-- Main Content / Table / Form -->
        </div>
    </div>
</div>
```

---

## Styling Rules (`admin.css`)

The core styles are defined in `assets/css/admin.css` and handle:
1. **Tabs Alignment**: Pulls active tabs down by `1px` using `margin-bottom: -1px` to sit on top of the tab nav's bottom border.
2. **Seamless Border Blending**: Sets `border-bottom-color: var(--aips-white)` on the active tab, hiding the border segment beneath it.
3. **Corner Rounding**: Restricts the panel's border-radius (`0 var(--aips-radius-lg) var(--aips-radius-lg) var(--aips-radius-lg)`) so that the top-left corner is sharp, aligning flush with the left edge of the tab menu.
4. **Nested Flattening**: Automatically strips backgrounds, shadows, and outer borders from any nested `.aips-content-panel` elements inside `.aips-page-tab-panel`. Re-draws unified borders specifically around table components (`.aips-filter-bar`, `.aips-table`, `.tablenav`).

---

## How to Apply to a Page

1. Ensure the tab wrapper has the class `aips-tab-nav` and the individual tabs have class `aips-tab-link`.
2. Add the `aips-page-tab-panel` class to the container immediately following the `aips-tab-nav` block.
3. If the page templates inside the tab utilize `.aips-content-panel` elements, do not remove them; the CSS automatically refactors their borders, padding, and backgrounds when nested.
