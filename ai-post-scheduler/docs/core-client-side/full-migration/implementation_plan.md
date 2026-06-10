# Implementation Plan: Full Backbone MVC Client-Side Refactoring

This plan outlines the architecture, roadmap, and steps to fully refactor all 32 legacy client-side JavaScript files from `assets/js/` into modular, compiled Backbone.js Models, Collections, and Views under `assets/src/js/`. Once completed, all legacy scripts will be deleted and the bundler (`main.js`) will import only modular ES components.

---

## Architectural Decisions

Following our alignment, the refactored architecture adheres to the following decisions:

1. **Conditional View Initialization**: To avoid running unnecessary logic on unrelated admin pages, page-specific Backbone Views will be conditionally instantiated in `main.js` based on the presence of their target DOM elements.
   * *Example*: `if ($('#aips-telemetry-panel').length) { AIPS.telemetryView = new TelemetryView(); }`
2. **External Third-Party Libraries**: Libraries like Chart.js and FullCalendar will remain externalized (loaded via standard WordPress `wp_enqueue_script` and accessed globally via `window.Chart` and `window.FullCalendar`). This keeps the compiled JS bundle small and preserves existing filter hooks in PHP.
3. **Shared Base Views**: In addition to `BaseFormView`, we will introduce two new abstract base classes, `BaseListView` and `BaseModalView`, to enforce strict and DRY patterns for tabular data lists and modal dialog interactions across all feature views (e.g., Schedules, History, Telemetry, Campaigns).

---

## Shared Architecture & Utilities

### 1. Shared Utilities (`assets/src/js/utils/`)
- **Event Bus (`mediator.js`)**: Keep the global `Backbone.Events` bus `AIPS.mediator` for cross-component event coordination.
- **UI Helpers (`ui-helpers.js`) [NEW]**: Port the global helper methods in `assets/js/utilities.js` (`showToast`, `confirm`, `setButtonLoading`, `resetButton`, `showProgressBar`) into a clean ES module. Keep compatibility bindings so `window.AIPS.Utilities` continues to work for legacy inline scripts.
- **DateTime (`datetime.js`) [NEW]**: Port date formatting and parsing functions from `assets/js/datetime.js` into a utility helper class.

### 2. Base WordPress Model (`assets/src/js/models/base.js`)
- Expose `BaseModel` to encapsulate standard jQuery AJAX sync with WordPress admin-ajax actions via the `actionMap` property. 
- Handles nonce headers, `ajaxurl` lookups, and parses standard `AIPS_Ajax_Response` envelopes.

### 3. Shared Base Views (`assets/src/js/views/`)
- **BaseFormView (`base-form.js`)**: Standardizes form validation, submits, spinners, disabled button states, and toast notifications.
- **BaseListView (`base-list.js`) [NEW]**: Standardizes tabular listings, pagination controls, keyword search, status/type filters, selection checkbox syncing, and bulk actions dispatch.
- **BaseModalView (`base-modal.js`) [NEW]**: Standardizes basic modal container management, including click-to-close wrappers, escape key bindings, and transitions.

---

## Migration Roadmap

The remaining files will be refactored across the following phases:

### Phase 2: Core Refactoring Completion (In Progress)
Refactor the primary Schedulers, Wizards, and Template files.

#### 1. Completing Schedules View (`assets/src/js/views/schedules.js`)
- Refactor the view to extend `BaseListView` (for unified listings table) and integrate `BaseModalView` triggers.
- Implement remaining methods for schedule cloning, wizard navigation steps validation (`validateWizardStep`, `getFirstInvalidStep`), immediate cron execution triggers, and history modals.

---

### Phase 3: Tabular Feature Panels & Calendars
Refactor pages that display complex tabular list data or custom calendar grids.

#### 1. Models & Collections
- **History Model (`assets/src/js/models/history.js`) [NEW]**: CRUD mappings for history, rollbacks, and log details (merging `admin-history.js` and `admin-view-session.js` logic).
- **Internal Link Model (`assets/src/js/models/link.js`) [NEW]**: Manage indexing states and list models (merging `admin-internal-links.js`).
- **Research Model (`assets/src/js/models/research.js`) [NEW]**: CRUD mappings for trending research keywords (merging `admin-research.js`).

#### 2. Views
- **History View (`assets/src/js/views/history.js`) [NEW]**:
  - Extends `BaseListView` to handle history table pagination, filters, log viewer drawer modal, and bulk deletion actions.
- **Planner View (`assets/src/js/views/planner.js`) [NEW]**:
  - Handles the planner grid UI and drag-and-drop planning actions.
- **Calendar View (`assets/src/js/views/calendar.js`) [NEW]**:
  - Backbone wrapper around `window.FullCalendar` for rendering schedule events.
- **Research View (`assets/src/js/views/research.js`) [NEW]**:
  - Extends `BaseListView` to handle keywords search, filters, trend scanner status, and topic approval flows.
- **Internal Links View (`assets/src/js/views/internal-links.js`) [NEW]**:
  - Coordinates post indexing progress bar and manual link insertion.
- **Sources View (`assets/src/js/views/sources.js`) [NEW]**:
  - Extends `BaseListView` for sources listing, schedules daily fetch runs, and displays logs (merging `admin-sources.js`).

---

### Phase 4: Support Views & System Tools
Port onboarding wizards, system utilities, settings pages, and telemetry charts.

#### 1. Models & Collections
- **Telemetry Model (`assets/src/js/models/telemetry.js`) [NEW]**: Manage telemetry queries.
- **Campaign Model (`assets/src/js/models/campaign.js`) [NEW]**: Manage campaign states.

#### 2. Views
- **Telemetry View (`assets/src/js/views/telemetry.js`) [NEW]**:
  - Extends `BaseListView` to handle table pagination, filters, log payload modals, and `window.Chart` configurations.
- **Campaigns View (`assets/src/js/views/campaigns.js`) [NEW]**:
  - Extends `BaseListView` to manage campaign listings and multi-step Campaign Creation Wizard (merging `campaigns.js` and `campaign-wizard.js`).
- **Settings View (`assets/src/js/views/settings.js`) [NEW]**:
  - Extends `BaseFormView` to manage the settings tabs, API connection tests, and option updates (merging `admin-settings.js`).
- **Post Slices View (`assets/src/js/views/post-slices.js`) [NEW]**:
  - Extends `BaseListView` to manage reusable content slice modals and cards (merging `admin-post-slices.js`).
- **System Status View (`assets/src/js/views/system-status.js`) [NEW]**:
  - Handles diagnostic list indicators, manual checks execution, and copy diagnostics payload (merging `admin-system-status.js`).
- **Onboarding View (`assets/src/js/views/onboarding.js`) [NEW]**:
  - Coordinates onboarding setup step navigation (merging `onboarding.js`).
- **Developer Tools View (`assets/src/js/views/dev-tools.js`) [NEW]**:
  - Coordinates cache monitor indicators, DB repair triggers, mock data seeding, and diagnostics helpers (merging `admin-db.js`, `admin-dev-tools.js`, `admin-seeder.js`, and `cache-monitor.js`).
- **Embeddings View (`assets/src/js/views/embeddings.js`) [NEW]**:
  - Coordinates vector indexing states and progress tracker UI (merging `admin-embeddings.js`).
- **Post Review View (`assets/src/js/views/post-review.js`) [NEW]**:
  - Manages approval list details and AI block editor regeneration features (merging `admin-post-review.js` and `admin-ai-edit.js`).
- **On-Page Views (`assets/src/js/views/admin-bar.js`, `assets/src/js/views/block-editor.js`) [NEW]**:
  - Port standalone features like block editor suggestion panels, admin bar menu shortcuts, and taxonomy tag assigner views (merging `admin-bar.js`, `ai-assistance.js`, and `taxonomy.js`).

---

## Verification Plan

### Automated Tests
1. **Bundler Verification**: Run `npm run build` inside `ai-post-scheduler/` to check that the Vite compiler finishes with exit code `0` and outputs correct distribution files.
2. **PHP Regression Tests**: Run `composer test:setup` followed by `composer test` (with `AIPS_WP_TEST_SKIP_DB_CREATE=true` if required) to verify that asset enqueues and AJAX registry logic remain perfectly functional.

### Manual Verification
1. **Modal & Form Saving**: Verify that editing and saving Voices, Structures, and Sections using the new shared `BaseFormView` completes correctly and updates tables inline using `refreshContentPanel`.
2. **Wizards Operations**: Verify multi-step navigation, field validation, and save features for the Template, Schedule, Campaign, and Onboarding wizards.
3. **Advanced Tabular Rendering**: Check pagination, search filters, and details modal rendering in Telemetry, History, and Research pages.
