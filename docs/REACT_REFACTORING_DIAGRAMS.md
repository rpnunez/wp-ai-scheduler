# React Refactoring Architecture Diagrams

Visual reference for the React refactoring architecture. Companion to [React Refactoring Feasibility Study](./REACT_REFACTORING_FEASIBILITY_STUDY.md).

---

## Current Architecture (jQuery + PHP Templates)

```
┌─────────────────────────────────────────────────────────────────┐
│                        WordPress Admin                           │
└─────────────────────────────────────────────────────────────────┘
                                │
                                │ User navigates to page
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                     PHP Controller                               │
│  (class-aips-templates-controller.php)                          │
│                                                                  │
│  ┌────────────────────────────────────────────────────────┐    │
│  │  Load data from database                                │    │
│  │  Pass to template                                       │    │
│  └────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                   PHP Template Renders HTML                      │
│  (templates/admin/templates.php)                                │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  <?php foreach ($templates as $template): ?>             │  │
│  │    <tr>                                                   │  │
│  │      <td><?php echo $template->name; ?></td>            │  │
│  │      <td><button class="edit-btn">Edit</button></td>    │  │
│  │    </tr>                                                  │  │
│  │  <?php endforeach; ?>                                    │  │
│  └──────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                   Browser Renders Page                           │
│  Static HTML table with data                                    │
└─────────────────────────────────────────────────────────────────┘
                                │
                                │ User clicks "Edit"
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                      jQuery Event Handler                        │
│  (assets/js/admin.js)                                           │
│                                                                  │
│  $('.edit-btn').click(function() {                             │
│    var id = $(this).data('id');                                │
│    $.ajax({                                                     │
│      url: ajaxUrl,                                             │
│      data: { action: 'aips_get_template', id: id }            │
│    }).done(function(response) {                                │
│      // Manually populate modal                                │
│      $('#name').val(response.data.name);                       │
│      $('#modal').show();                                       │
│    });                                                          │
│  });                                                            │
└─────────────────────────────────────────────────────────────────┘
                                │
                                │ AJAX Request
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                    wp_ajax_* Action Hook                         │
│  (class-aips-templates-controller.php)                          │
│                                                                  │
│  public function ajax_get_template() {                          │
│    check_ajax_referer('aips_nonce', 'nonce');                  │
│    $id = absint($_POST['template_id']);                        │
│    $template = $this->repository->get($id);                    │
│    wp_send_json_success($template);                            │
│  }                                                              │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│              Database Query via Repository                       │
│  (class-aips-template-repository.php)                           │
└─────────────────────────────────────────────────────────────────┘

Problems:
❌ State scattered (DOM + JavaScript globals)
❌ Manual DOM manipulation (error-prone)
❌ No single source of truth
❌ Hard to test
❌ Difficult to reuse code
```

---

## Proposed React Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        WordPress Admin                           │
└─────────────────────────────────────────────────────────────────┘
                                │
                                │ User navigates to page
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                     PHP Controller                               │
│  (class-aips-react-admin.php)                                   │
│                                                                  │
│  ┌────────────────────────────────────────────────────────┐    │
│  │  Enqueue React app bundle                              │    │
│  │  Output: <div id="aips-react-root"></div>             │    │
│  │  Localize: API URL, nonce, current user               │    │
│  └────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                    React App Mounts                              │
│  (build/index.js - compiled from src/)                          │
│                                                                  │
│  ┌────────────────────────────────────────────────────────┐    │
│  │  <HashRouter>                                           │    │
│  │    <Route path="/templates" element={<TemplatesPage/>} │    │
│  │  </HashRouter>                                          │    │
│  └────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                   TemplatesPage Component                        │
│  (src/pages/Templates/index.jsx)                                │
│                                                                  │
│  function TemplatesPage() {                                     │
│    const { templates, loading } = useTemplates(); // ◄─┐       │
│    const [searchTerm, setSearchTerm] = useState('');   │       │
│    const [selectedId, setSelectedId] = useState(null); │       │
│                                                          │       │
│    return (                                              │       │
│      <div>                                               │       │
│        <SearchControl value={searchTerm}                │       │
│                      onChange={setSearchTerm} />        │       │
│        <TemplatesTable templates={filtered}             │       │
│                       onEdit={setSelectedId} />         │       │
│        {selectedId && <TemplateModal id={selectedId}/>} │       │
│      </div>                                              │       │
│    );                                                    │       │
│  }                                                       │       │
│                                                          │       │
│  Single source of truth: Component state                │       │
│  UI auto-updates when state changes                     │       │
└──────────────────────────────────────────────────────────────────┘
                                │                           │
                                │                           │
                         ┌──────┴──────────┐              │
                         │                 │              │
                         ▼                 ▼              │
              ┌─────────────────┐  ┌────────────────┐   │
              │ TemplatesTable  │  │ TemplateModal  │   │
              │   Component     │  │   Component    │   │
              └─────────────────┘  └────────────────┘   │
                                                          │
                                   ┌──────────────────────┘
                                   │ Custom Hook
                                   ▼
┌─────────────────────────────────────────────────────────────────┐
│                    useTemplates Hook                             │
│  (src/pages/Templates/useTemplates.js)                          │
│                                                                  │
│  export function useTemplates() {                               │
│    const [templates, setTemplates] = useState([]);             │
│    const [loading, setLoading] = useState(true);               │
│                                                                  │
│    useEffect(() => {                                            │
│      apiFetch({ path: '/aips/v1/templates' })                  │
│        .then(setTemplates)                                      │
│        .finally(() => setLoading(false));                       │
│    }, []);                                                      │
│                                                                  │
│    return { templates, loading, refetch };                     │
│  }                                                              │
└─────────────────────────────────────────────────────────────────┘
                                │
                                │ REST API Request
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│              REST API Endpoint (WordPress)                       │
│  /wp-json/aips/v1/templates                                     │
│                                                                  │
│  class AIPS_REST_Templates_Controller                           │
│    extends WP_REST_Controller {                                 │
│                                                                  │
│    public function register_routes() {                          │
│      register_rest_route('aips/v1', '/templates', [            │
│        'methods' => 'GET',                                      │
│        'callback' => [$this, 'get_items'],                      │
│        'permission_callback' => [$this, 'check_permissions'],  │
│      ]);                                                         │
│    }                                                            │
│                                                                  │
│    public function get_items($request) {                        │
│      $templates = $this->repository->get_all();                │
│      return rest_ensure_response($templates);                  │
│    }                                                            │
│  }                                                              │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│              Database Query via Repository                       │
│  (class-aips-template-repository.php)                           │
└─────────────────────────────────────────────────────────────────┘

Benefits:
✅ Single source of truth (React state)
✅ Declarative UI (describe what, not how)
✅ Automatic UI updates
✅ Easy to test
✅ Reusable components
✅ RESTful API design
```

---

## Data Flow Comparison

### Current (jQuery) Data Flow

```
┌─────────────┐
│   Browser   │  Initial page load
│             │
│  ┌───────┐  │  1. PHP renders full HTML table
│  │ Table │  │     with all template data
│  └───────┘  │
│             │
│  ┌───────┐  │  2. jQuery attaches event listeners
│  │jQuery │  │     after DOM ready
│  └───────┘  │
└─────────────┘
      │
      │ User clicks "Edit Template"
      ▼
┌─────────────────────────────────────┐
│  jQuery Event Handler               │
│                                     │
│  1. Extract template ID from DOM   │ ◄─── State stored in DOM!
│  2. Make AJAX request               │
│  3. Wait for response...            │
└─────────────────────────────────────┘
      │
      ▼
┌─────────────────────────────────────┐
│  AJAX Response Received             │
│                                     │
│  1. Parse JSON response             │
│  2. MANUALLY update DOM:            │
│     $('#name').val(data.name)       │ ◄─── Imperative!
│     $('#desc').val(data.desc)       │      Error-prone!
│     // ... 20+ more fields          │
│  3. Show modal                      │
└─────────────────────────────────────┘
      │
      ▼
┌─────────────┐
│   Browser   │  Modal visible
│             │
│  ┌───────┐  │  State scattered:
│  │ Modal │  │  - DOM attributes (data-id)
│  └───────┘  │  - Form values (#name, #desc)
│             │  - Global variables (currentTemplate)
└─────────────┘
```

### Proposed (React) Data Flow

```
┌─────────────┐
│   Browser   │  Initial page load
│             │
│  ┌───────┐  │  1. PHP outputs empty div
│  │ Empty │  │     <div id="root"></div>
│  │  Div  │  │  2. React bundle loads
│  └───────┘  │  3. React app mounts
└─────────────┘
      │
      │ React useEffect hook fires
      ▼
┌─────────────────────────────────────┐
│  useTemplates Custom Hook           │
│                                     │
│  const [templates, setTemplates]    │ ◄─── Single source of truth!
│    = useState([]);                  │      All in React state
│                                     │
│  useEffect(() => {                  │
│    apiFetch('/aips/v1/templates')   │
│      .then(setTemplates)            │
│  }, []);                            │
└─────────────────────────────────────┘
      │
      ▼
┌─────────────┐
│   Browser   │  React renders table
│             │
│  ┌───────┐  │  templates.map(t => <TemplateRow />)
│  │ Table │  │
│  └───────┘  │  UI automatically reflects state
└─────────────┘
      │
      │ User clicks "Edit Template"
      ▼
┌─────────────────────────────────────┐
│  React Event Handler                │
│                                     │
│  onClick={(template) => {           │
│    setSelectedTemplate(template)    │ ◄─── Just update state!
│  }}                                 │      That's it!
│                                     │
│  State change triggers re-render    │ ◄─── Declarative!
└─────────────────────────────────────┘
      │
      ▼
┌─────────────┐
│   Browser   │  React automatically re-renders
│             │
│  ┌───────┐  │  {selectedTemplate && 
│  │ Modal │  │    <TemplateModal template={selected} />}
│  └───────┘  │
│             │  Modal receives template as prop
│             │  Form fields automatically populated
└─────────────┘

State lives in ONE place: React state
UI automatically syncs when state changes
No manual DOM manipulation needed!
```

---

## Component Hierarchy

### Templates Page Component Tree

```
<TemplatesPage>
│
├─ <PageHeader>
│  ├─ "Post Templates"
│  └─ <Button variant="primary">Add New</Button>
│
├─ <SearchControl>
│  └─ (filters templates in state)
│
├─ <TemplatesTable>
│  │
│  ├─ <thead>
│  │  └─ <TemplateTableHeader>
│  │
│  └─ <tbody>
│     └─ {templates.map(t => 
│         <TemplateRow
│           key={t.id}
│           template={t}
│           onEdit={handleEdit}
│           onDelete={handleDelete}
│           onClone={handleClone}
│         />
│     )}
│
└─ {isModalOpen && (
    <TemplateModal
      template={selectedTemplate}
      onSave={handleSave}
      onClose={() => setIsModalOpen(false)}
    >
      <TemplateWizard>
        ├─ <BasicInfoStep>
        │  ├─ <TextControl label="Name" />
        │  └─ <TextareaControl label="Description" />
        │
        ├─ <ContentPromptsStep>
        │  ├─ <TextareaControl label="Content Prompt" />
        │  └─ <TextControl label="Title Prompt" />
        │
        ├─ <PostSettingsStep>
        │  ├─ <SelectControl label="Status" />
        │  ├─ <CategorySelect />
        │  └─ <TagsInput />
        │
        └─ <FeaturedImageStep>
           ├─ <CheckboxControl label="Generate Image" />
           └─ <TextareaControl label="Image Prompt" />
      </TemplateWizard>
    </TemplateModal>
  )}

Props flow DOWN ⬇️
Events flow UP ⬆️ via callbacks
State managed at appropriate level
Reusable components (Button, TextControl, etc.)
```

---

## File Structure Comparison

### Current Structure

```
ai-post-scheduler/
├── assets/
│   ├── css/
│   │   ├── admin.css               (Global styles)
│   │   └── authors.css             (Page-specific styles)
│   └── js/
│       ├── admin.js                (2,195 lines - monolithic!)
│       ├── authors.js              (1,246 lines)
│       ├── admin-activity.js       (426 lines)
│       └── ... (9 more files)
│
├── includes/
│   ├── class-aips-templates-controller.php    (AJAX handlers)
│   ├── class-aips-schedules-controller.php
│   └── ... (many more controllers)
│
└── templates/
    └── admin/
        ├── templates.php           (559 lines - big template!)
        ├── generated-posts.php     (353 lines)
        ├── authors.php             (325 lines)
        └── ... (16 more templates)

Total: ~3,700 lines PHP templates + ~5,850 lines jQuery
```

### Proposed React Structure

```
ai-post-scheduler/
├── package.json                    (NPM dependencies)
├── webpack.config.js              (Optional overrides)
│
├── src/                           ◄─── NEW: React source
│   ├── index.js                   (Entry point)
│   ├── App.jsx                    (Router, main app)
│   │
│   ├── components/                (Shared components)
│   │   ├── Button.jsx
│   │   ├── Modal.jsx
│   │   ├── Table.jsx
│   │   ├── SearchControl.jsx
│   │   ├── ConfirmDialog.jsx
│   │   └── ...
│   │
│   ├── pages/                     (Page components)
│   │   ├── Templates/
│   │   │   ├── index.jsx         (Main page)
│   │   │   ├── TemplatesTable.jsx
│   │   │   ├── TemplateRow.jsx
│   │   │   ├── TemplateModal.jsx
│   │   │   ├── TemplateWizard/
│   │   │   │   ├── BasicInfo.jsx
│   │   │   │   ├── ContentPrompts.jsx
│   │   │   │   ├── PostSettings.jsx
│   │   │   │   └── FeaturedImage.jsx
│   │   │   ├── useTemplates.js   (Custom hook)
│   │   │   └── templates.css
│   │   │
│   │   ├── Schedules/
│   │   ├── History/
│   │   ├── Authors/
│   │   └── ...
│   │
│   ├── hooks/                     (Shared custom hooks)
│   │   ├── useAPI.js
│   │   ├── useDebounce.js
│   │   └── useLocalStorage.js
│   │
│   ├── utils/                     (Helper functions)
│   │   ├── api.js
│   │   ├── formatters.js
│   │   └── validators.js
│   │
│   └── styles/
│       └── global.css
│
├── build/                         ◄─── NEW: Compiled output
│   ├── index.js                   (Bundled app)
│   ├── index.asset.php            (Auto-generated deps)
│   └── index.css
│
├── includes/
│   ├── class-aips-rest-templates-controller.php  ◄─── NEW: REST API
│   ├── class-aips-rest-schedules-controller.php
│   ├── class-aips-react-admin-assets.php        ◄─── NEW: Enqueue logic
│   └── ... (existing PHP classes)
│
└── templates/
    └── admin/
        └── react-root.php         ◄─── NEW: Simple wrapper
                                       Just: <div id="aips-react-root"></div>

Cleaner separation of concerns
Modular, reusable components
Smaller, focused files
Better organization
```

---

## Build Process Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                      Developer Workflow                          │
└─────────────────────────────────────────────────────────────────┘

  Developer writes code in src/
         │
         │
         ▼
┌──────────────────────────┐
│  npm start               │  ◄─── Development mode
│  (@wordpress/scripts)    │       Hot reload, fast refresh
└──────────────────────────┘
         │
         │ Webpack watches files
         │ Babel transpiles JSX → JS
         │ SASS compiles to CSS
         │
         ▼
┌──────────────────────────┐
│  build/index.js          │  ◄─── Development bundle
│  build/index.css         │       (not minified)
│  build/index.asset.php   │       (dependency list)
└──────────────────────────┘
         │
         │ WordPress loads bundle
         │
         ▼
┌──────────────────────────┐
│  Browser                 │  ◄─── See changes instantly!
│  React DevTools          │       Debug with React DevTools
└──────────────────────────┘

         │
         │ Ready for production?
         │
         ▼
┌──────────────────────────┐
│  npm run build           │  ◄─── Production mode
│  (@wordpress/scripts)    │       Minify, optimize
└──────────────────────────┘
         │
         │ Webpack production build
         │ Code splitting
         │ Minification
         │ Source maps
         │
         ▼
┌──────────────────────────┐
│  build/index.js          │  ◄─── Production bundle
│  build/index.css         │       (minified, optimized)
│  build/index.asset.php   │
└──────────────────────────┘
         │
         │ Deploy to production
         │
         ▼
┌──────────────────────────┐
│  WordPress.org           │  ◄─── Plugin repository
│  Users download          │
└──────────────────────────┘

build/ directory should be in .gitignore during dev
build/ directory should be included in production release
```

---

## Migration Strategy Phases

```
┌─────────────────────────────────────────────────────────────────┐
│                     Phase 1: Infrastructure                      │
│                            Week 1                                │
└─────────────────────────────────────────────────────────────────┘
│
├─ Setup package.json and @wordpress/scripts
├─ Create REST API endpoints (parallel to AJAX)
├─ Build shared component library
├─ Setup routing and mount strategy
│
└─ ✅ MILESTONE: Build process working, first API endpoint ready

┌─────────────────────────────────────────────────────────────────┐
│                   Phase 2: Pilot Conversion                      │
│                           Week 2                                 │
└─────────────────────────────────────────────────────────────────┘
│
├─ Convert Templates page to React
│  ├─ Templates list with search
│  ├─ Template modal/wizard
│  ├─ CRUD operations
│  └─ Stats display
│
└─ ✅ MILESTONE: One page fully working in React
                 🚦 DECISION POINT: Continue or revert?

┌─────────────────────────────────────────────────────────────────┐
│                    Phase 3: Core Pages                           │
│                         Weeks 3-4                                │
└─────────────────────────────────────────────────────────────────┘
│
├─ Convert high-traffic pages:
│  ├─ Schedules (similar to templates)
│  ├─ Generated Posts (tabbed interface)
│  └─ History (filtering, pagination)
│
└─ ✅ MILESTONE: Core functionality in React

┌─────────────────────────────────────────────────────────────────┐
│                  Phase 4: Remaining Pages                        │
│                         Weeks 5-6                                │
└─────────────────────────────────────────────────────────────────┘
│
├─ Convert remaining pages:
│  ├─ Authors & Topics (Kanban board)
│  ├─ Planner (bulk operations)
│  ├─ Research/Trending Topics
│  ├─ Voices, Structures, Sections
│  ├─ Settings
│  └─ Dashboard
│
├─ Deprecate old AJAX endpoints
├─ Remove jQuery code
│
└─ ✅ MILESTONE: Full migration complete

┌─────────────────────────────────────────────────────────────────┐
│                Phase 5: Polish & Optimization                    │
│                            Week 7                                │
└─────────────────────────────────────────────────────────────────┘
│
├─ Performance optimization (code splitting, lazy loading)
├─ Accessibility audit (WCAG 2.1 AA)
├─ Cross-browser testing
├─ User testing and feedback
├─ Documentation updates
│
└─ ✅ MILESTONE: Production-ready React admin interface

Timeline: 7 weeks total (10 weeks if working solo)
Can parallelize some work with 2 developers
```

---

## Coexistence Strategy During Migration

```
WordPress Admin Menu
│
├─ Dashboard (PHP) ─────────────► templates/admin/dashboard.php
│                                 assets/js/admin-dashboard.js
│
├─ Templates (REACT) ───────────► <div id="root"></div>
│                                 build/index.js (React app)
│                                 React Router: #/templates
│
├─ Schedules (PHP) ─────────────► templates/admin/schedule.php
│                                 assets/js/admin-schedule.js
│
├─ Generated Posts (PHP) ───────► templates/admin/generated-posts.php
│                                 assets/js/admin-generated-posts.js
│
└─ History (PHP) ───────────────► templates/admin/history.php
                                  assets/js/admin-history.js

Both systems coexist safely!
PHP pages use wp_ajax_* handlers
React pages use REST API
No interference between the two
```

---

## Deployment Checklist

```
┌─────────────────────────────────────────────────────────────────┐
│                       Before Deployment                          │
└─────────────────────────────────────────────────────────────────┘

□ Run: npm run build (production build)
□ Verify: build/ directory contains compiled files
□ Test: All React pages load correctly
□ Test: All API endpoints return expected data
□ Test: User permissions enforced
□ Test: Cross-browser compatibility (Chrome, Firefox, Safari, Edge)
□ Test: Mobile responsive design
□ Verify: No console errors
□ Verify: Accessibility audit passed
□ Verify: Performance benchmarks met
□ Update: Plugin version number
□ Update: Changelog
□ Commit: build/ directory to git (for release)

┌─────────────────────────────────────────────────────────────────┐
│                         Deployment                               │
└─────────────────────────────────────────────────────────────────┘

□ Tag: version in git
□ Build: release package
□ Upload: to WordPress.org (or deployment target)
□ Activate: on production
□ Monitor: for errors
□ Collect: user feedback

┌─────────────────────────────────────────────────────────────────┐
│                       Post-Deployment                            │
└─────────────────────────────────────────────────────────────────┘

□ Monitor: JavaScript errors (via error tracking)
□ Monitor: API response times
□ Monitor: User feedback
□ Address: any issues quickly
□ Document: lessons learned
□ Plan: next features
```

---

**Document Version:** 1.0  
**Last Updated:** February 10, 2026
