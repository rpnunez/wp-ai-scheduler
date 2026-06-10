# Client-Side Refactoring Plan (JS & CSS Architecture)

Refactor the client-side architecture of the `wp-ai-scheduler` WordPress plugin. The goals are to resolve the spaghetti jQuery code, standardise UI/UX components (like buttons and modals), eliminate duplicated utility functions, implement a modern compilation workflow using Vite/ESBuild, and establish a robust JavaScript model-view architecture using Backbone.js.

## User Review Required

Please review the architectural details and the planned structure below. The main changes are:
- **Build Step**: Introduction of a `package.json` in `ai-post-scheduler/` for compiling assets.
- **Consolidation**: Moving from 30+ separate JavaScript and CSS files enqueued dynamically per page to a single consolidated bundle: `aips-admin.min.js` and `aips-admin.min.css`.
- **Backbone Integration**: Transitioning state and page controllers into Backbone Models, Collections, and Views, while keeping templates in PHP as `<script type="text/html">` tags for native PHP localization support.

## Selected Libraries & Technologies

The following table summarizes the choices and recommendations for each client-side component type:

| Layer / Type | Legacy / Current Implementation | Refactored Choice | Rationale |
| :--- | :--- | :--- | :--- |
| **JS Framework (Data & MVC)** | Disconnected parenthesized modules extending global `window.AIPS` namespace. Custom DOM queries. | **Backbone.js** (built-in WordPress core) | Zero extra download size, standard structure in WordPress, provides Models/Collections/Views to clean up AJAX/DOM logic. |
| **JS Utilities** | Duplicated local inline helpers (`escapeHtml`, `escape`) and `utilities.js` helpers. | **Underscore.js** (built-in WordPress core) | Native, standardized utilities (e.g. `_.escape`, `_.debounce`, `_.each`) replacing custom shims. |
| **Template Engine** | Light custom regex parser in `templates.js` (`AIPS.Templates`). | **Underscore Templates** (`_.template`) | Industry standard, powerful, natively supports compiled functions, zero extra bundle footprint. |
| **CSS/UI Structure** | Single CSS files per page, mix of utility classes, element selectors, and interchangeable class/ID declarations. | **BEM CSS with Design Tokens** (using CSS Variables) | Prevents button and UI discrepancies, avoids framework conflicts, preserves native WordPress dashboard aesthetics. |
| **Asset Compilation** | Raw un-minified files enqueued separately page-by-page in PHP. | **Vite / ESBuild** (configured in `ai-post-scheduler/`) | Allows modular ES6 development (import/export), minification, linting, and outputs fixed files (`aips-admin.min.js/css`). |

---

## Proposed Changes

We will introduce a node build environment and structure our source assets under `assets/src/`. The compiled output will go to `assets/dist/`.

### 1. Build Infrastructure [NEW]

#### [NEW] [package.json](file:///C:/Users/rpnunez/.gemini/antigravity/worktrees/wp-ai-scheduler/refactor-js-css-architecture/ai-post-scheduler/package.json)
Configure npm scripts (`dev`, `build`) and dev dependencies (Vite, PostCSS, Autoprefixer).

#### [NEW] [vite.config.js](file:///C:/Users/rpnunez/.gemini/antigravity/worktrees/wp-ai-scheduler/refactor-js-css-architecture/ai-post-scheduler/vite.config.js)
Vite configuration:
- Entry point: `assets/src/js/main.js` and `assets/src/css/main.css`.
- Output directory: `assets/dist/`.
- Fixed bundle naming: `assets/dist/js/aips-admin.min.js` and `assets/dist/css/aips-admin.min.css`.
- Externalize `jquery`, `backbone`, and `underscore` (provided by WordPress globally).

---

### 2. Client-Side Directory Structure [NEW]

```
ai-post-scheduler/
├── package.json
├── vite.config.js
└── assets/
    ├── src/
    │   ├── css/
    │   │   ├── main.css           # Imports tokens, components, and page styles
    │   │   ├── _variables.css     # Global Design Tokens (colors, typography, buttons)
    │   │   ├── _components.css    # Shared BEM components (.aips-btn, .aips-card, .aips-modal)
    │   │   └── _pages.css         # Page-specific override styling
    │   └── js/
    │       ├── main.js            # Main entry point (initializes Backbone app router/views)
    │       ├── models/            # Backbone Models & Collections
    │       │   ├── template.js    # Model for Templates
    │       │   ├── schedule.js    # Model for Schedules
    │       │   └── author.js      # Model for Authors
    │       ├── views/             # Backbone Views
    │       │   ├── dashboard.js
    │       │   ├── templates.js   # Backbone View for template listings & wizards
    │       │   ├── schedules.js   # Backbone View for schedules
    │       │   └── authors.js     # Backbone View for authors & topics
    │       └── utils/             # Consolidated helpers & Backbone extensions
    │           └── mediator.js    # Shared Event Bus (Backbone.Events)
    └── dist/                      # Compiled production assets
        ├── js/
        │   └── aips-admin.min.js
        └── css/
            └── aips-admin.min.css
```

---

### 3. CSS Refactoring Plan

- **Design Tokens**: Centralize standard brand colors, button states (primary, secondary, danger, hover, focus), spacing scales, and rounded borders in `assets/src/css/_variables.css`.
- **Button Standards**:
  ```css
  .aips-btn {
      /* Shared button styling (padding, borders, transitions) */
  }
  .aips-btn--primary { background-color: var(--aips-color-primary); }
  .aips-btn--danger  { background-color: var(--aips-color-danger); }
  .aips-btn--secondary { background-color: var(--wp-admin-theme-color, #2271b1); }
  ```
- **File Consolidation**: Merge 12+ page CSS files into a single bundle to reduce HTTP requests.

---

### 4. JavaScript Refactoring Plan

- **Backbone Integration**:
  - Backbone Models/Collections will manage CRUD operations via AJAX (replacing inline `$.ajax` calls in different files with `Model.save()` / `Collection.fetch()`).
  - Backbone Views will bind events declaratively:
    ```javascript
    const TemplateListView = Backbone.View.extend({
        el: '#aips-templates-container',
        events: {
            'click .aips-add-template-btn': 'openAddModal',
            'click .aips-delete-template': 'deleteTemplate'
        },
        initialize() {
            this.listenTo(this.collection, 'sync reset destroy', this.render);
        },
        render() {
            // Render rows using Underscore template
        }
    });
    ```
- **Templates**: Replace legacy `AIPS.Templates` regex engine with Underscore's `_.template`.
  - Templates remain in PHP files (e.g. `templates/admin/authors.php`) using `<script type="text/html">` to maintain support for WP translations.
- **De-duplication**: Replace custom `escapeHtml` with native `_.escape`.

---

### 5. PHP Enqueuing Changes

#### [MODIFY] [class-aips-admin-assets.php](file:///C:/Users/rpnunez/.gemini/antigravity/worktrees/wp-ai-scheduler/refactor-js-css-architecture/ai-post-scheduler/includes/class-aips-admin-assets.php)
- Remove all page-specific JS and CSS enqueues.
- Declare `jquery`, `backbone`, and `underscore` as script dependencies.
- Enqueue the single consolidated `aips-admin.min.js` and `aips-admin.min.css` under `enqueue_global_assets()`.
- Unify translations: localize all translation strings as a single hierarchical object `aipsL10n` passed once to `aips-admin.min.js`.

---

## Verification Plan

### Automated Tests
Verify that assets compile correctly and no lint errors are introduced:
- Run `npm run build` in `ai-post-scheduler/` and ensure compilation finishes without errors.
- Ensure PHPUnit tests continue to pass:
  ```bash
  cd ai-post-scheduler
  composer test
  ```

### Manual Verification
- Deploy build to WordPress dashboard and load the plugin admin.
- Confirm all menu tabs load correctly.
- Test main operations:
  - Add/Edit Template Modal (Wizards).
  - Add/Edit Author + Topic Generation.
  - Modals & Toast alerts open and look consistent.
  - Buttons use identical spacing, design styling, and hover states.
