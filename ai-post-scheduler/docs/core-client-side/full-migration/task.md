# Client-Side Refactoring Tasks (Full Backbone Migration)

- `[x]` **Phase 2: Core Refactoring**
  - `[x]` Create `models/topic.js` (Topic model & collection)
  - `[x]` Create `views/author-topics.js` (approval actions, approvals modal, feedback, logs)
  - `[x]` Update `models/author.js` to inherit `BaseModel` and map CRUD
  - `[x]` Update `views/authors.js` (validation wizard, suggestion import)
  - `[x]` Create `models/voice.js` & `views/voices.js` (Voice CRUD list extending BaseFormView)
  - `[x]` Create `models/structure.js` & `views/structures.js` (Structure CRUD list extending BaseFormView)
  - `[x]` Create `models/section.js` & `views/sections.js` (Section CRUD list extending BaseFormView)
  - `[x]` Refactor `views/templates.js` (integrate Template Wizard, validate fields, variables scanner)
  - `[x]` Create `views/base-list.js` (Shared `BaseListView` class)
  - `[x]` Create `views/base-modal.js` (Shared `BaseModalView` class)
  - `[x]` Refactor `views/schedules.js` (integrate Schedule Wizard, status strip read-model)

- `[x]` **Phase 3: Secondary Feature Panels & Calendars**
  - `[x]` Create `models/history.js` & `views/history.js` (History logs table, pagination, details modal)
  - `[x]` Create `views/planner.js` (Planner content grid, card drag-and-drop actions)
  - `[x]` Create `views/calendar.js` (FullCalendar Backbone wrapper)
  - `[x]` Create `models/research.js` & `views/research.js` (Research keywords search, trend scanner, source groups)
  - `[x]` Create `models/link.js` & `views/internal-links.js` (Links index list, manual indexer progress UI)
  - `[x]` Create `views/sources.js` (Sources CRUD & fetched data viewer)

- `[x]` **Phase 4: Minor Admin Views & System Tools**
  - `[x]` Create `views/base-form.js` (Shared `BaseFormView` parent class)
  - `[x]` Create `models/telemetry.js` & `views/telemetry.js` (Telemetry logs pagination, details payload, Chart.js charts)
  - `[x]` Create `models/campaign.js` & `views/campaigns.js` (Campaign list and Campaigns Wizard)
  - `[x]` Create `views/settings.js` (Settings tabs connection tests extending BaseFormView)
  - `[x]` Create `views/post-slices.js` (Content slices manager extending BaseFormView)
  - `[x]` Create `views/system-status.js` (Diagnostics checklist and copy status)
  - `[x]` Create `views/onboarding.js` (Onboarding installation steps wizard)
  - `[x]` Create `views/dev-tools.js` (Developer tools view, clear cache monitor, DB repair triggers, mock data seeding)
  - `[x]` Create `views/embeddings.js` (Embeddings indexing progress bar)
  - `[x]` Create `views/post-review.js` (Pending reviews & AI regeneration)
  - `[x]` Create `views/admin-bar.js` & `views/block-editor.js` (WP toolbar hooks & inline block editor tools)

- `[ ]` **Phase 5: Code Cleanup & Purge**
  - `[ ]` Create `utils/ui-helpers.js` & `utils/datetime.js` (Port shared globals)
  - `[ ]` Update `assets/src/js/main.js` (Remove all 32 legacy imports, instantiate Backbone views)
  - `[ ]` Delete all files in `assets/js/` directory
  - `[ ]` Run `npm run build` and ensure compilation completes correctly

- `[ ]` **Verification**
  - `[ ]` Build assets successfully in production mode
  - `[ ]` Execute PHPUnit test suite to confirm zero server regressions
