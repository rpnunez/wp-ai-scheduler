# Admin UI Design Guidelines

This document is the **single source of truth** for AI Post Scheduler admin UI design.

Scope:
- Admin templates in `ai-post-scheduler/templates/admin/`
- Admin styles in `ai-post-scheduler/assets/css/`
- Admin scripts that render HTML/UI states

Primary CSS source audited:
- `ai-post-scheduler/assets/css/admin.css`

---

## 1) Design Tokens

Use CSS custom properties (`--aips-*`) first. Do not hard-code visual values in templates or ad-hoc CSS when a token exists.

### 1.1 Color Tokens

#### Primary
- `--aips-primary`: primary actions, links, focus accents
- `--aips-primary-hover`: hover/active state for primary surfaces
- `--aips-primary-light`: focus rings, subtle highlighted backgrounds

#### Neutral Grays
- `--aips-gray-900` through `--aips-gray-50`, plus `--aips-white`
- Use darker grays for text hierarchy, lighter grays for borders/backgrounds

#### Semantic Colors
- Success: `--aips-success`, `--aips-success-dark`, `--aips-success-light`
- Warning: `--aips-warning`, `--aips-warning-dark`, `--aips-warning-light`
- Error: `--aips-error`, `--aips-error-dark`, `--aips-error-light`
- Info: `--aips-info`, `--aips-info-dark`, `--aips-info-light`

**Approved usage**
- Use semantic tokens for state communication (success/warning/error/info).
- Use neutral tokens for structure and non-semantic text/backgrounds.
- Use primary tokens for brand/action emphasis only.

**Do not**
- Do not use semantic colors for purely decorative styling.
- Do not introduce raw hex/RGB in templates.
- Do not introduce new one-off shades when an existing token is close enough.

---

### 1.2 Typography Scale

Font size tokens:
- `--aips-text-3xl`, `--aips-text-2xl`, `--aips-text-xl`, `--aips-text-lg`
- `--aips-text-base`, `--aips-text-sm`, `--aips-text-xs`, `--aips-text-2xs`

Line-height tokens:
- `--aips-leading-none`, `--aips-leading-tight`, `--aips-leading-snug`, `--aips-leading-normal`, `--aips-leading-relaxed`

Font-weight tokens:
- `--aips-font-normal`, `--aips-font-medium`, `--aips-font-semibold`, `--aips-font-bold`

**Approved usage**
- Page titles: `2xl` + `semibold`
- Panel/card titles: `lg` + `semibold`
- Body text and form controls: `sm` or `base`
- Secondary/meta text: `xs`
- Badge/table header labels: `xs` + uppercase where relevant

**Do not**
- Do not hard-code pixel font sizes if a token exists.
- Do not mix arbitrary font-weight values (e.g., 550) in new styles.

---

### 1.3 Spacing Scale

Spacing tokens:
- `--aips-space-0`, `--aips-space-1`, `--aips-space-2`, `--aips-space-3`, `--aips-space-4`, `--aips-space-5`, `--aips-space-6`, `--aips-space-8`, `--aips-space-10`, `--aips-space-12`, `--aips-space-16`

**Approved usage**
- Intra-component gaps: `space-1` to `space-3`
- Component internal padding: `space-3` to `space-6`
- Section spacing: `space-5` and above
- Large empty states/major layout rhythm: `space-8+`

**Do not**
- Do not use arbitrary values like `margin-top: 13px` in new UI.
- Do not mix token spacing and random pixel spacing in same component.

---

### 1.4 Border Radius, Shadows, Elevation

Radius tokens:
- `--aips-radius-none`, `--aips-radius-sm`, `--aips-radius-base`, `--aips-radius-md`, `--aips-radius-lg`, `--aips-radius-xl`, `--aips-radius-full`

Shadow tokens:
- `--aips-shadow-xs`, `--aips-shadow-sm`, `--aips-shadow-base`, `--aips-shadow-md`, `--aips-shadow-lg`

**Approved usage**
- Inputs/buttons/badges: `radius-base`
- Cards/panels/modals: `radius-md` or `radius-lg`
- Default cards/panels: `shadow-sm`
- Hover elevation: `shadow-base`
- Higher overlays/dialog prominence: `shadow-md+`

**Do not**
- Do not invent unique border radius values per component unless there is a clear design-system update.
- Do not add heavy shadow by default on dense tables/forms.

---

### 1.5 Z-Index Layers

Z-index tokens:
- `--aips-z-base`
- `--aips-z-dropdown`
- `--aips-z-sticky`
- `--aips-z-fixed`
- `--aips-z-modal-backdrop`
- `--aips-z-modal`
- `--aips-z-popover`
- `--aips-z-tooltip`

**Approved usage**
- Sticky filter/toolbars: `--aips-z-sticky`
- Backdrops: `--aips-z-modal-backdrop`
- Modal surfaces: `--aips-z-modal`
- Popovers/tooltips above modals only when required

**Do not**
- Do not hard-code very high z-index values in templates or inline styles.
- Do not create local stacking hacks when tokenized layer is sufficient.

---

## 2) Component and Utility Class Families

### 2.1 Buttons (`aips-btn*`)

Core classes:
- `.aips-btn`
- Variants: `.aips-btn-primary`, `.aips-btn-secondary`, `.aips-btn-ghost`, `.aips-btn-danger`, `.aips-btn-danger-solid`
- Sizes/modifiers: `.aips-btn-sm`, `.aips-btn-icon`
- Grouping: `.aips-btn-group`, `.aips-btn-group-inline`

**Use when**
- Any plugin-owned admin action button/link needs consistent visual and interaction behavior.

**Avoid when**
- Styling native WordPress controls directly without intent to align plugin design system.

---

### 2.2 Panels, Cards, Layout

Core classes:
- `.aips-page-container`, `.aips-page-header`, `.aips-content-panel`
- `.aips-panel-header`, `.aips-panel-body`, `.aips-panel-title`
- `.aips-card`, `.aips-card-header`, `.aips-card-body`, `.aips-card-footer`
- Grid/layout: `.aips-grid`, `.aips-grid-cols-*`, `.aips-layout-with-sidebar`

**Use when**
- Structuring page sections, grouped controls, content modules, and dashboard blocks.

**Avoid when**
- Creating one-off wrappers with duplicate behavior.

---

### 2.3 Tables (`aips-table*`)

Core classes:
- `.aips-table`
- Cell helpers: `.cell-primary`, `.cell-meta`, `.cell-actions`
- Related families: `.aips-history-table`, `.aips-schedule-table`

**Use when**
- Listing schedules/history/items where sortable/scannable tabular structure is needed.

**Avoid when**
- Data is better represented as cards or stacked detail blocks on small screens.

---

### 2.4 Tabs and Toolbars

Core classes:
- `.aips-filter-bar`, `.aips-filter-left`, `.aips-filter-right`
- `.aips-panel-toolbar`, `.aips-toolbar-left`, `.aips-toolbar-right`

**Use when**
- Page-level filters, segmented controls, contextual actions at top of panel/table.

**Avoid when**
- You need vertical form layout; prefer form section classes there.

---

### 2.5 Badges and Status

Core classes:
- `.aips-badge`
- Variants: `.aips-badge-success`, `.aips-badge-warning`, `.aips-badge-error`, `.aips-badge-info`, `.aips-badge-neutral`

**Use when**
- Compact state indicators inside tables/cards/headers.

**Avoid when**
- Long-form messaging; use notice/banner/panel patterns instead.

---

### 2.6 Modals

Core classes:
- `.aips-modal`, `.aips-modal-content`, `.aips-modal-large`
- `.aips-modal-header`, `.aips-modal-body`, `.aips-modal-footer`, `.aips-modal-close`

**Use when**
- Confirmations, detail views, and bounded workflows that should not navigate away.

**Avoid when**
- The interaction is lightweight enough for inline expansion.

---

### 2.7 Common Utilities / Helpers

Current helpers include:
- `.aips-sr-only`
- `.aips-no-data`
- `.aips-filter-label-inline`

State helpers used across UI:
- `.is-hidden` (preferred hidden utility)

Width helpers used across UI:
- `.aips-w-auto` (preferred width utility)

**Use when**
- Solving a common presentation need repeatedly.

**Avoid when**
- A new helper duplicates existing one with different naming.

---

## 3) Required UI Rules (Non-Negotiable)

1. **No inline CSS in `templates/admin/*.php`.**
2. **No hard-coded hex/RGB colors in templates.**
3. **Prefer semantic classes over one-off CSS selectors.**
4. **New UI must use shared tokens and component classes first.**

If a needed pattern is missing, extend the shared design system in CSS rather than adding per-template style attributes.

---

## 4) Migration Mapping (Old → Approved)

Use this mapping during refactors and feature work:

- `style="display:none"` → `.is-hidden`
- `style="width:auto"` → `.aips-w-auto`
- `style="margin-top: X"` → spacing classes/utilities based on token scale (`--aips-space-*`)
- `style="color:#..."` / `style="background:#..."` → semantic or neutral token-backed class
- Inline button sizing/padding styles → `.aips-btn` + size/variant modifiers
- Ad-hoc status pills → `.aips-badge` + semantic variant
- Ad-hoc panel wrappers → `.aips-content-panel` / `.aips-card`
- Repeated per-view table styles → `.aips-table` + scoped helper classes

When no approved replacement exists, add a reusable utility/component class in shared CSS and document it here.

---

## 5) Contribution Workflow for Admin UI

Before shipping UI changes:
1. Reuse existing `aips-*` tokens/classes first.
2. If extending styles, add to shared admin CSS with token-based values.
3. Verify no inline styles remain in admin templates.
4. Verify state colors and spacing use approved scales.
5. Keep class naming semantic and reusable.

This document should be updated whenever the design system evolves.
