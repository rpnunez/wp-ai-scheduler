# JavaScript and CSS Refactor Plan

## Purpose

AI Post Scheduler has a strong PHP architecture, but its client-side code has grown into many disconnected, page-specific scripts and stylesheets. The goal of this plan is to introduce a formal, WordPress-friendly JavaScript and CSS architecture without turning the plugin into a single-page application or abandoning the existing admin-rendered WordPress plugin model.

The refactor should preserve the best parts of the current client-side layer:

- `window.AIPS` as the public namespace.
- `AIPS.Utilities` for shared UI helpers and backward compatibility.
- `AIPS.Templates` as a lightweight templating adapter.
- PHP-rendered admin pages in `templates/admin/`.
- WordPress admin AJAX endpoints and existing controller patterns.

The refactor should fix the current pain points:

- Too many page-specific JavaScript and CSS files.
- Large, overloaded scripts such as `assets/js/admin.js` and `assets/js/authors.js`.
- Repeated helpers such as escaping, toast handling, modal handling, AJAX wrappers, and loading-state logic.
- JavaScript tightly coupled to visual CSS classes and brittle template structure.
- CSS button, form, panel, and table inconsistencies across admin pages.
- Lack of a documented client-side architecture equivalent to the PHP side.

## Recommended Direction

Use a WordPress-native progressive framework, not a SPA framework.

The recommended stack is:

> **Backbone + Underscore/wp.template + a formal AIPS client architecture + an internal AIPS CSS design system.**

This gives the project real client-side models, collections, views, event maps, and page lifecycle conventions while staying compatible with classic WordPress admin pages.

## Suggested Library Stack

| Area | Recommendation | Add Dependency? | Reason |
| --- | --- | --- | --- |
| Data framework | Backbone | No, use the WordPress `backbone` script handle | Provides models, collections, views, events, and declarative event maps without requiring a SPA. |
| Utility/data helpers | Underscore | No, use the WordPress `underscore` script handle | Already paired with Backbone in WordPress and useful for collection transforms and templates. |
| Template engine | WordPress `wp.template` / Underscore templates first | No | Low migration cost from `AIPS.Templates`; avoids adding another template dependency immediately. |
| AJAX abstraction | `AIPS.Api` wrapper first; optional `@wordpress/api-fetch` later | Initially no | Wraps existing `admin-ajax.php` actions now, while preserving a path to REST later. |
| UI state/events | Backbone.Events + `AIPS.Events` event bus | No | Replaces scattered document-level events with named domain events. |
| Modals, toasts, prompts | Keep `AIPS.Utilities`, split into focused services | No | Preserves existing behavior while centralizing UI primitives. |
| CSS/UI framework | Internal AIPS Design System, not global Bootstrap | No initially | Avoids WordPress admin style collisions while normalizing components and tokens. |
| Build tooling | Add npm + esbuild later | Yes, later phase | Allows modular source files while shipping fewer browser assets. |
| Optional micro-interactivity | Alpine.js only for isolated widgets | Maybe later | Useful for small isolated controls, but not a primary architecture because it can increase markup coupling. |
| Charts | Keep existing Chart.js vendor asset | Already present | Existing use is appropriate for dashboard and telemetry charts. |

## Architecture Principles

1. **Keep WordPress as the application shell.** Admin pages remain PHP-rendered, menu-driven WordPress plugin pages.
2. **Progressively enhance pages.** JavaScript should enhance server-rendered markup rather than replace all rendering.
3. **Centralize repeated behavior.** AJAX, escaping, notifications, modals, loading states, and templating should have one implementation.
4. **Separate behavior hooks from styling hooks.** JavaScript should prefer `data-aips-*` attributes; CSS should use component classes.
5. **Use formal page lifecycles.** Page modules should initialize through a registry instead of anonymous document-ready blocks.
6. **Maintain backward compatibility.** Existing `AIPS.Utilities`, `AIPS.Templates`, and page handles should remain available during migration.
7. **Refactor one page at a time.** Avoid large, all-at-once rewrites of `admin.js` or `authors.js`.

## Proposed JavaScript Structure

Create a client-side architecture under `ai-post-scheduler/assets/js/` initially. A later build step can move source modules to `assets/src/js/` and compiled output to `assets/dist/js/`.

```text
assets/js/
  core/
    aips-app.js
    aips-api.js
    aips-collection.js
    aips-escape.js
    aips-event-bus.js
    aips-model.js
    aips-page-registry.js
    aips-view.js
  ui/
    aips-confirm.js
    aips-form-state.js
    aips-modal.js
    aips-toast.js
  pages/
    sources/
      models.js
      collections.js
      views.js
      controller.js
      index.js
    settings/
      views.js
      controller.js
      index.js
    authors/
      models.js
      collections.js
      views.js
      controller.js
      index.js
```

### Core Namespaces

Expose these public namespaces:

- `window.AIPS.App`
- `window.AIPS.Api`
- `window.AIPS.Events`
- `window.AIPS.Escape`
- `window.AIPS.Model`
- `window.AIPS.Collection`
- `window.AIPS.View`
- `window.AIPS.Pages`
- `window.AIPS.Templates`
- `window.AIPS.Utilities`

### Page Lifecycle

Each admin page should declare a root marker:

```html
<div class="aips-page" data-aips-page="sources">
```

A shared bootstrap should:

1. Find `.aips-page[data-aips-page]`.
2. Read the page ID.
3. Resolve the page initializer from `AIPS.Pages`.
4. Initialize only the matching page module.
5. Store the running page instance for teardown and debugging.

Example API:

```js
AIPS.Pages.register('sources', function(rootEl, config) {
	return new AIPS.Pages.Sources.Controller({
		el: rootEl,
		config: config
	});
});
```

### Base View

`AIPS.View` should extend `Backbone.View` and provide shared helpers for:

- scoped selectors
- loading states
- disabled buttons
- form serialization
- API error rendering
- standardized notifications
- safe teardown

Example shape:

```js
AIPS.View = Backbone.View.extend({
	find: function(selector) {},
	setLoading: function(isLoading, options) {},
	notify: function(message, type, options) {},
	handleApiError: function(error, fallbackMessage) {},
	remove: function() {}
});
```

## Proposed AJAX Architecture

Create `AIPS.Api` as the only approved route for plugin AJAX calls.

### Required API

- `AIPS.Api.request(action, data, options)`
- `AIPS.Api.get(action, data, options)`
- `AIPS.Api.post(action, data, options)`

### Responsibilities

`AIPS.Api` should:

- Default to `window.ajaxurl`.
- Inject the plugin nonce from a centralized localized config object.
- Normalize WordPress AJAX success and failure payloads.
- Return a consistent Promise or jQuery Deferred.
- Trigger global events through `AIPS.Events`:
  - `api:request`
  - `api:success`
  - `api:error`
- Avoid directly showing toasts unless explicitly requested by options.

### Migration Rule

1. After `AIPS.Api` is introduced, no migrated page should call `$.ajax()`, `$.post()`, or `fetch()` directly for plugin admin operations.
2. Before registering a new AJAX action or implementing a duplicate handler, check if the action is already registered in the central AJAX registry (e.g., `AIPS_Ajax_Registry`) or handled by another controller to avoid duplicate implementations.

## Proposed Template Architecture

Keep `AIPS.Templates`, but turn it into a formal adapter.

### Required API

- `AIPS.Templates.compile(idOrString, options)`
- `AIPS.Templates.render(idOrString, data, options)`
- `AIPS.Templates.register(name, templateString)`
- `AIPS.Templates.escape(value)`

### Preferred Template Sources

Use one of these patterns:

1. Server-rendered initial markup for first paint.
2. `<script type="text/html" id="tmpl-aips-name">` templates for repeatable rows/cards/modals.
3. Registered template strings for small client-only fragments.

### Escaping Rule

All template escaping should delegate to `AIPS.Escape.html()`. Any intentional raw HTML insertion must be explicit and documented at the call site.

## Proposed Escaping Architecture

Create `AIPS.Escape` and move all client-side escaping to it.

### Required API

- `AIPS.Escape.html(value)`
- `AIPS.Escape.attribute(value)`
- `AIPS.Escape.url(value)` if needed
- `AIPS.Escape.raw(value)` only for trusted, sanitized HTML

### Migration Targets

Replace local escape helpers and fallback functions in:

- `assets/js/admin.js`
- `assets/js/admin-research.js`
- `assets/js/taxonomy.js`
- `assets/js/campaign-wizard.js`
- Any other file containing local `escape`, `escapeHtml`, or `var esc =` patterns.

## Proposed UI Services

Split `AIPS.Utilities` into focused UI services while preserving it as a compatibility facade.

### New Services

- `AIPS.Toast`
- `AIPS.Modal`
- `AIPS.Confirm`
- `AIPS.FormState`

### Backward Compatibility

Keep these existing methods and aliases working until all page scripts are migrated:

- `AIPS.Utilities.showToast()`
- `AIPS.Utilities.escapeHtml()`
- `AIPS.Utilities.escapeAttribute()`
- `window.AIPS.showToast()`
- `window.AIPS.escapeHtml()`
- `window.AIPS.escapeAttribute()`

## Proposed CSS Architecture

Build an internal AIPS Design System instead of importing Bootstrap globally.

### File Structure

```text
assets/css/
  aips-tokens.css
  aips-base.css
  aips-layout.css
  aips-components.css
  aips-utilities.css
  aips-pages.css
```

A later build step can compile these into `assets/dist/css/aips-admin.css`.

### Design Tokens

Define tokens for:

- colors
- status colors
- spacing
- border radii
- shadows
- typography
- z-index layers
- transitions

Example token categories:

```css
:root {
	--aips-color-primary: #2271b1;
	--aips-color-primary-hover: #135e96;
	--aips-color-danger: #d63638;
	--aips-color-success: #00a32a;
	--aips-space-2: 0.5rem;
	--aips-radius-sm: 4px;
	--aips-shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.08);
}
```

### Component Classes

Define canonical components:

- `.aips-btn`
- `.aips-btn--primary`
- `.aips-btn--secondary`
- `.aips-btn--danger`
- `.aips-btn--ghost`
- `.aips-card`
- `.aips-panel`
- `.aips-table`
- `.aips-modal`
- `.aips-toast`
- `.aips-form-field`
- `.aips-badge`
- `.aips-toolbar`

### Legacy Aliases

Keep legacy aliases temporarily:

- `.aips-btn-primary`
- `.aips-btn-secondary`
- `.aips-btn-danger`
- `.aips-btn-sm`
- Any existing page-specific button aliases still in active templates.

Map legacy aliases to new component styles, then migrate templates gradually.

## Template and Selector Contract

Admin templates should distinguish styling, behavior, and accessibility responsibilities.

### CSS Classes

Use CSS classes for styling only:

```html
<button class="aips-btn aips-btn--primary">
```

### JavaScript Hooks

Use `data-aips-*` attributes for behavior:

```html
<button
	class="aips-btn aips-btn--primary"
	data-aips-action="save-source"
	data-aips-id="123">
	Save
</button>
```

### IDs

Use IDs only for:

- form label relationships
- ARIA attributes
- unique anchors
- WordPress-required fields
- browser-native form behavior that requires an ID

JavaScript should not use visual layout classes as its primary selector in migrated pages.

## Asset Loading Plan

Refactor `AIPS_Admin_Assets` into three enqueue layers.

### Layer 1: Core

- `aips-core`
- `aips-event-bus`
- `aips-api`
- `aips-escape`
- `aips-templates`
- `aips-utilities-compat`

### Layer 2: Shared UI

- `aips-ui-toast`
- `aips-ui-modal`
- `aips-ui-confirm`
- `aips-ui-form-state`

### Layer 3: Page Modules

- `aips-page-sources`
- `aips-page-settings`
- `aips-page-authors`
- `aips-page-history`
- `aips-page-campaigns`
- etc.

Every admin page should receive the core layer. Only matching pages should receive page modules.

Existing handles such as `aips-admin-script`, `aips-utilities-script`, and `aips-templates-script` should remain during the transition as compatibility handles or dependency aliases.

## Build Pipeline Plan

Do not introduce a build pipeline as the first step. First stabilize the architecture and migrate one or two pages.

Once patterns are proven, add a minimal npm/esbuild pipeline inside `ai-post-scheduler/`.

### Proposed Structure

```text
ai-post-scheduler/
  package.json
  assets/src/js/
  assets/src/css/
  assets/dist/js/
  assets/dist/css/
```

### Proposed Outputs

- `assets/dist/js/aips-admin-core.js`
- `assets/dist/js/aips-admin-pages.js`
- `assets/dist/css/aips-admin.css`

### Proposed Scripts

- `npm run build`
- `npm run watch`
- `npm run lint`
- `npm run format`

`AIPS_Admin_Assets` can support a temporary constant such as `AIPS_USE_DIST_ASSETS`, defaulting to legacy assets until the migration is stable.

## Migration Roadmap

### Phase 0: Inventory and Documentation

Create static inventory documents before changing behavior.

Deliverables:

- `docs/CLIENT_ASSET_INVENTORY.md`
- `docs/CLIENT_ARCHITECTURE.md`

Inventory should include:

- all JS files
- all CSS files
- approximate line counts
- page ownership
- enqueue handles
- dependencies
- duplicate helpers
- high-risk scripts

### Phase 1: Shared Foundation

Add shared architecture without rewriting pages.

Deliverables:

- `AIPS.Escape`
- `AIPS.Api`
- `AIPS.Events`
- `AIPS.View`
- `AIPS.Pages`
- improved `AIPS.Templates`
- compatibility facade for `AIPS.Utilities`

Success criteria:

- Existing pages still work.
- Existing global aliases still work.
- New core files load before page files.
- No large page rewrites are included in this phase.

### Phase 2: First Reference Page

Migrate one low-risk page, preferably Sources or Settings.

Recommended first target: **Sources** or **Settings**.

Deliverables:

- page root marker with `data-aips-page`
- page module registered through `AIPS.Pages`
- AJAX calls through `AIPS.Api`
- repeated markup through `AIPS.Templates`
- behavior selectors through `data-aips-*`
- no local escaping helpers

Success criteria:

- The migrated page becomes the reference pattern for all future migrations.
- The PHP template remains server-rendered.
- Existing admin-ajax controller behavior remains unchanged.

### Phase 3: CSS Design System

Normalize the visual system.

Deliverables:

- tokens file
- base styles
- layout helpers
- component styles
- utility classes
- legacy aliases

Priority components:

1. Buttons
2. Forms
3. Tables
4. Panels/cards
5. Modals
6. Toasts
7. Toolbars
8. Badges/status indicators

Success criteria:

- Submit buttons, destructive buttons, secondary buttons, and ghost buttons use consistent tokens.
- Page-specific CSS stops redefining base button behavior.
- Legacy classes remain functional during migration.

### Phase 4: Decompose `admin.js`

Split `assets/js/admin.js` after the foundation and first reference page are stable.

Deliverables:

- identify feature sections inside `admin.js`
- move shared logic to core or UI services
- move page-specific behavior to page modules
- keep `aips-admin-script` as a compatibility entrypoint until migration completes

Success criteria:

- `admin.js` becomes either a thin compatibility loader or is retired.
- No page loses behavior during the split.
- Each migrated feature has an owner module.

### Phase 5: Migrate Remaining Pages

Migrate pages by risk and complexity.

Suggested order:

1. Settings
2. Sources
3. Post Slices
4. Campaigns list
5. Telemetry
6. Dashboard
7. History
8. Internal Links
9. Calendar
10. Authors
11. Templates/admin core flows

High-risk files such as `authors.js` and the remaining pieces of `admin.js` should be migrated only after patterns are proven.

### Phase 6: Optional REST Modernization

After `AIPS.Api` stabilizes around admin-ajax, prepare it for REST-backed endpoints.

Deliverables:

- `AIPS.Api.requestAjax()`
- `AIPS.Api.requestRest()`
- optional `@wordpress/api-fetch` integration
- shared error normalization between admin-ajax and REST

Success criteria:

- Existing admin-ajax endpoints remain supported.
- New heavier data resources can move to REST over time.

### Phase 7: Bundle Consolidation

After architecture and page migration are stable, introduce source bundling.

Deliverables:

- npm/esbuild setup
- source modules in `assets/src/`
- compiled assets in `assets/dist/`
- updated enqueue support

Success criteria:

- Developers can work in modular source files.
- The browser receives fewer files.
- Production assets can be minified.
- Legacy mode remains available during rollout.

## Concrete Task Backlog

### Task: Create Client Architecture Documentation

Create `docs/CLIENT_ARCHITECTURE.md` with:

- selected library stack
- folder structure
- page lifecycle
- model, collection, and view conventions
- API conventions
- event bus naming
- template conventions
- escaping rules
- CSS token and component rules
- migration checklist

### Task: Create Client Asset Inventory

Create `docs/CLIENT_ASSET_INVENTORY.md` with:

- JS file list and line counts
- CSS file list and line counts
- current enqueue handles
- page ownership
- duplicated helpers
- migration risk score per file

### Task: Add `AIPS.Escape`

Create `assets/js/core/aips-escape.js`.

Migrate `AIPS.Templates.escape()` and `AIPS.Utilities.escapeHtml()` to delegate to `AIPS.Escape.html()`.

### Task: Add `AIPS.Api`

Create `assets/js/core/aips-api.js`.

Centralize:

- admin AJAX URL
- nonce injection
- request lifecycle events
- response normalization
- error normalization

### Task: Add `AIPS.Events`

Create `assets/js/core/aips-event-bus.js` using `Backbone.Events`.

Use namespaced events such as:

- `api:request`
- `api:success`
- `api:error`
- `page:init`
- `page:ready`
- `modal:open`
- `modal:close`
- `toast:show`

### Task: Add `AIPS.View`

Create `assets/js/core/aips-view.js` extending `Backbone.View`.

Provide shared helpers for:

- selectors
- loading states
- form state
- notifications
- API errors
- teardown

### Task: Add `AIPS.Pages`

Create `assets/js/core/aips-page-registry.js`.

Provide:

- `register(pageId, initializer)`
- `init(rootEl, config)`
- `initFromDom()`

### Task: Split `AIPS.Utilities`

Create focused UI files:

- `assets/js/ui/aips-toast.js`
- `assets/js/ui/aips-modal.js`
- `assets/js/ui/aips-confirm.js`
- `assets/js/ui/aips-form-state.js`

Keep `assets/js/utilities.js` as a compatibility facade until migration is complete.

### Task: Convert `AIPS.Templates` into an Adapter

Update `assets/js/templates.js` with:

- compile
- render
- register
- escape
- compatibility with existing template syntax
- support for WordPress/Underscore templates where appropriate

### Task: Migrate First Reference Page

Migrate Sources or Settings.

Required changes:

- template root marker
- `data-aips-*` behavior hooks
- `AIPS.Pages` registration
- Backbone view/controller
- `AIPS.Api` requests
- `AIPS.Templates` rendering
- no local escape helpers

### Task: Create CSS Design System

Create or reorganize CSS into:

- `aips-tokens.css`
- `aips-base.css`
- `aips-layout.css`
- `aips-components.css`
- `aips-utilities.css`
- `aips-pages.css`

Include legacy aliases to avoid breaking current templates.

### Task: Refactor Asset Enqueues

Update `AIPS_Admin_Assets` to support:

- core layer
- shared UI layer
- page module layer
- compatibility handles
- future dist bundles

### Task: Add Build Pipeline

After architecture stabilizes, add:

- `package.json`
- esbuild config or script
- build/watch/lint/format commands
- dist output enqueues

## Page Migration Checklist

A page is considered migrated when:

- It has `.aips-page[data-aips-page="page-id"]`.
- Its initializer is registered through `AIPS.Pages`.
- It does not make direct plugin AJAX calls outside `AIPS.Api`.
- It does not define local escape helpers.
- It uses `AIPS.Templates` for repeated client-rendered markup.
- It uses `data-aips-*` attributes for JavaScript hooks.
- It does not rely on visual CSS classes as primary JavaScript selectors.
- It uses shared UI services for toasts, modals, confirms, and loading states.
- It uses canonical design-system components for buttons, forms, tables, panels, and badges.
- It preserves existing PHP rendering and WordPress admin behavior.

## Non-Goals

Do not:

- Convert the plugin into a React, Vue, Svelte, or Angular SPA.
- Import Bootstrap globally into the WordPress admin.
- Remove PHP-rendered admin templates.
- Rewrite all page scripts in one patch.
- Remove `AIPS.Utilities` or `AIPS.Templates` immediately.
- Migrate admin-ajax endpoints to REST before the client API wrapper is stable.
- Couple new JavaScript behavior to visual-only CSS selectors.

## Recommended First Implementation Sequence

1. Create `docs/CLIENT_ARCHITECTURE.md` and `docs/CLIENT_ASSET_INVENTORY.md`.
2. Add `AIPS.Escape` and route existing escape helpers through it.
3. Add `AIPS.Events`.
4. Add `AIPS.Api`.
5. Add `AIPS.View`.
6. Add `AIPS.Pages`.
7. Migrate Sources or Settings as the first reference page.
8. Add CSS design tokens and canonical button styles.
9. Expand the design system to forms, panels, tables, modals, toasts, toolbars, and badges.
10. Decompose `admin.js` feature by feature.
11. Migrate high-risk pages such as Authors only after the pattern is proven.
12. Add npm/esbuild bundling after the architecture is stable.

## Success Metrics

The refactor is successful when:

- Shared behavior is implemented once and reused across pages.
- New page features can be built with models, collections, views, templates, and shared API calls.
- JavaScript files are modular in source but can be bundled for fewer browser requests.
- CSS buttons, forms, tables, panels, modals, toasts, and badges are consistent across pages.
- PHP templates contain less behavior-specific coupling.
- The plugin remains a classic WordPress admin plugin, not a SPA.
- Existing admin pages continue working throughout the migration.
