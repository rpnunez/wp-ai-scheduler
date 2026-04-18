# Architectural Improvement Plan — Performance & Developer Velocity

_Analyzed from codebase snapshot — April 2026_

This document identifies high-value architectural patterns and structural improvements that simultaneously increase runtime performance and development speed. Where `plan.md` targets individual hotspots, this document targets the **underlying patterns** that cause the hotspots to exist in the first place — fixing the root cause removes entire categories of future bugs and performance regressions rather than patching them one by one.

Items are ordered by combined impact (performance + developer velocity) and annotated with effort level.

---

## 1 — Dependency Injection Container

### Problem

The plugin currently wires dependencies by hand everywhere: every constructor calls `new ClassName()` for its dependencies, and those constructors call `new ClassName()` for theirs, creating deep chains of eager allocation that can't be intercepted, cached, or replaced. This pattern is the single root cause of every issue in `plan.md § A` (duplicate instantiation, cron objects on page loads, etc.).

The problem compounds at development time: because concrete classes are referenced everywhere, swapping a class (e.g., replacing `AIPS_History_Service` with a caching decorator) requires hunting down every `new AIPS_History_Service()` across the codebase. Tests that need to inject mocks must work around this via constructor optional-parameter defaults, which is already a workaround pattern visible in `AIPS_Generator`.

### Recommendation — Introduce `AIPS_Container`

Add a minimal service container (`ai-post-scheduler/includes/class-aips-container.php`) that:

1. Registers **bindings** — either factories (closures) or resolved instances.
2. Supports **singleton scope** (resolved once, shared) and **transient scope** (new instance per `make()` call).
3. Provides a static `AIPS_Container::get_instance()` accessor so any class can resolve without needing the container passed to it — useful during the migration period.

A minimal implementation does not need Reflection or auto-wiring; explicit registrations are sufficient and stay readable:

```php
class AIPS_Container {
    private static ?self $instance = null;
    private array $bindings  = [];
    private array $singletons = [];

    public static function get_instance(): self { ... }

    /** Register a transient factory. */
    public function bind(string $id, Closure $factory): void { ... }

    /** Register a singleton factory. */
    public function singleton(string $id, Closure $factory): void { ... }

    /** Resolve a binding, constructing it if needed. */
    public function make(string $id): mixed { ... }
}
```

**Adoption path:**

- Phase 1: Create the container. Register the most-duplicated singletons: `AIPS_History_Repository`, `AIPS_History_Service`, `AIPS_Notifications_Repository`, `AIPS_Config`.
- Phase 2: Migrate `AI_Post_Scheduler::init()` to build controllers by calling `$container->make(AIPS_Schedule_Controller::class)` etc., so the container (not `init()`) owns the graph.
- Phase 3: Register controllers as singletons so the admin-AJAX split (see item 3) can reuse already-resolved instances.

**Impact:**
- **Performance:** Eliminates all duplicate-instantiation bugs from `plan.md § A` structurally — a singleton binding prevents the object from ever being constructed more than once, regardless of where `make()` is called.
- **Developer velocity:** Adding a new controller means one `singleton()` registration; its dependencies are resolved automatically from registered bindings. No more grep-and-hunt when renaming or decorating a class.

---

## 2 — Singletons for All Stateless Infrastructure Services

### Problem

Stateless services — classes that hold no mutable per-request state — are currently constructed on demand wherever they are used. The most common offenders are `AIPS_History_Repository`, `AIPS_History_Service`, `AIPS_Notifications_Repository`, `AIPS_Logger`, `AIPS_Config`, and `AIPS_Interval_Calculator`. Each is effectively a function namespace wrapping `$wpdb` or `get_option()` calls with no state that needs to differ between callers.

### Recommendation — Static `instance()` factory on each class

This is the fastest individual win and can be done class-by-class without the container (complements item 1):

```php
class AIPS_History_Repository {
    private static ?self $instance = null;

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    // ... rest unchanged
}
```

Once the container exists (item 1), these static factories become one-line registrations:
```php
$container->singleton(AIPS_History_Repository::class, fn() => new AIPS_History_Repository());
```

**Classes to add `instance()` to immediately:**
- `AIPS_History_Repository`
- `AIPS_History_Service` — already accepts an optional `$repository`; default should use `AIPS_History_Repository::instance()`
- `AIPS_Notifications_Repository`
- `AIPS_Logger`
- `AIPS_Config` — already has `get_instance()` (the pattern is established; extend to all stateless services)
- `AIPS_Interval_Calculator`
- `AIPS_Template_Repository`
- `AIPS_AI_Service`

**Impact:**
- **Performance:** Each singleton reduces object allocation from N per request to 1. For `AIPS_History_Service`, this alone eliminates ~10 allocations (and their cascading `new AIPS_History_Repository()` sub-allocations) per admin request.
- **Developer velocity:** Removes the cognitive burden of "which instance am I using?" — there is always one, and any configuration or state applied to it (e.g., mock in tests) applies everywhere.

---

## 3 — Context-Aware Bootstrap (Replace Flat `init()`)

### Problem

`AI_Post_Scheduler::init()` is a single monolithic method that boots every subsystem unconditionally, regardless of what the current request actually needs. The four request contexts have very different needs:

| Context | What is needed |
|---------|---------------|
| **Frontend page load** | Admin bar (if `manage_options`), cron hooks |
| **Admin non-AJAX page view** | Admin menu, assets, settings UI, the specific page being rendered |
| **Admin AJAX request** | Only the single controller whose action matches `$_REQUEST['action']` |
| **WP-Cron execution** | Only the scheduler/generator whose hook is being fired |

All four contexts currently receive all subsystems.

### Recommendation — Context providers

Replace the flat `init()` with a dispatcher that delegates to context-specific boot methods:

```php
public function init(): void {
    $this->boot_common();          // taxonomy, text domain — always needed

    if (wp_doing_cron()) {
        $this->boot_cron();
    } elseif (wp_doing_ajax()) {
        $this->boot_ajax();
    } elseif (is_admin()) {
        $this->boot_admin();
    } else {
        $this->boot_frontend();
    }
}
```

Each boot method only registers hooks/instantiates objects relevant to that context:

- **`boot_cron()`** — Binds `AIPS_Scheduler`, `AIPS_Author_Topics_Scheduler`, `AIPS_Author_Post_Generator`, `AIPS_Embeddings_Cron` and their cron hooks. Nothing else.
- **`boot_ajax()`** — Reads `$_REQUEST['action']`, maps it to a controller class via a static action→class map, resolves only that controller from the container.
- **`boot_admin()`** — Registers admin menu, assets, and defers controller instantiation to `load-{page}` hooks so only the active page's controller runs.
- **`boot_frontend()`** — Creates `AIPS_Admin_Bar` only (needed for `manage_options` users on the frontend).

**Impact:**
- **Performance:** On a frontend request, zero controllers and zero schedulers are instantiated. On an AJAX request, one controller is instantiated instead of twenty. On an admin page view, only the page being rendered boots.
- **Developer velocity:** Adding a new controller becomes a two-step operation — register it in the action map and add it to the relevant `load-{page}` hook. There is no monolithic file to understand and update. New developers can understand each context in isolation.

---

## 4 — Interface-Driven Dependencies

### Problem

Controller and service constructors reference concrete classes directly:

```php
// Current: hardcoded concrete type
private AIPS_History_Repository $repo;

// Usage:
public function __construct() {
    $this->repo = new AIPS_History_Repository();
}
```

When a concrete class needs to be decorated, cached, or replaced in tests, every reference must be updated. The codebase already has some interfaces (`interface-aips-generation-context.php`, `interface-aips-cron-generation-handler.php`) demonstrating the intent but the pattern is not applied to repositories or services.

### Recommendation — Define and use interfaces for all major seams

Priority interfaces to add:

| Interface | Concrete | Used by |
|-----------|----------|---------|
| `AIPS_History_Repository_Interface` | `AIPS_History_Repository` | ~10 classes |
| `AIPS_History_Service_Interface` | `AIPS_History_Service` | ~10 classes |
| `AIPS_AI_Service_Interface` | `AIPS_AI_Service` | `AIPS_Generator`, `AIPS_Image_Service` |
| `AIPS_Notifications_Repository_Interface` | `AIPS_Notifications_Repository` | `AIPS_Admin_Bar`, notifications stack |
| `AIPS_Logger_Interface` | `AIPS_Logger` | ~8 classes |
| `AIPS_Schedule_Repository_Interface` | `AIPS_Schedule_Repository` | `AIPS_Scheduler`, `AIPS_Schedule_Controller` |

Type hints change from `AIPS_History_Repository` to `AIPS_History_Repository_Interface`. Constructors continue to accept `null` and resolve via the container.

**Impact:**
- **Performance:** Enables caching decorators to be injected transparently. For example, `AIPS_Caching_History_Repository` wrapping `AIPS_History_Repository` can be swapped in one container registration to cache all history reads in the object cache, with zero changes to callers.
- **Developer velocity:** PHPUnit test setup becomes one line per dependency (`$this->createMock(AIPS_History_Repository_Interface::class)`) rather than complex subclassing hacks. Adding a cache layer, a logging decorator, or a test double requires only a container re-registration, not a codebase-wide find-and-replace.

---

## 5 — Typed DTOs / Value Objects for Data Transfer

### Problem

The primary currency between layers is plain PHP `array()`. For example, `AIPS_Generator` returns an associative array for generation results, template objects are passed as row arrays from `$wpdb`, and AJAX responses assemble ad-hoc arrays inline. This creates silent breakage when array keys are renamed, makes IDE completion impossible, and puts the burden of knowing the shape on every caller.

### Recommendation — Introduce typed value objects for the highest-traffic data shapes

Start with the shapes that cross the most class boundaries:

1. **`AIPS_Generation_Result`** — replaces the current ad-hoc array returned by generator methods. Properties: `post_id`, `status` (enum: `completed|partial|failed`), `errors[]`, `component_statuses[]`, `generation_time`. Builders in tests can use named constructor `AIPS_Generation_Result::success($post_id)` vs `AIPS_Generation_Result::failure($errors)`.

2. **`AIPS_Schedule_Entry`** — wraps the DB row from `aips_schedule`. Typed properties instead of `$row['next_run']` everywhere.

3. **`AIPS_Template_Data`** — wraps the DB row from `aips_templates`. Gives IDE-completable access to all template fields.

4. **`AIPS_AJAX_Response`** — replaces ad-hoc `wp_send_json_success()` / `wp_send_json_error()` calls in controllers. Centralizes nonce generation, standard field shape, and error code propagation.

`AIPS_Bulk_Generation_Result` (already uses `public readonly` properties — PHP 8.1+) is the existing precedent for this pattern; extend it to all major data exchange shapes.

**Impact:**
- **Developer velocity:** IDE auto-completion on all inter-layer data; typos in property names become compile-time (static-analysis) errors instead of runtime `null` surprises. New feature development is faster because the data shape is self-documenting.
- **Performance:** Minor — eliminates repeated `isset()` guards throughout calling code, and centralizing array-to-DTO mapping in one place enables caching that shape.

---

## 6 — PSR-4 Autoloading via Composer

### Problem

The custom `AIPS_Autoloader` performs a string transformation and a `file_exists()` check on every class load for any `AIPS_`-prefixed class. It only searches one directory (`includes/`) and cannot be extended without modifying the autoloader file. As the codebase grows (100+ classes), the flat single-directory structure becomes unwieldy and the autoloader a bottleneck.

### Recommendation — Add PSR-4 autoloading to `composer.json`

```json
{
    "autoload": {
        "psr-4": {
            "AIPS\\": "src/"
        }
    }
}
```

Migrate classes to namespaced equivalents in `src/` over time (or immediately via a single rename pass), keeping the legacy autoloader as a fallback shim during transition. Composer's generated `vendor/autoload.php` uses a precomputed classmap after `composer dump-autoload --optimize`, eliminating `file_exists()` on every class load and enabling IDE navigation through standard PSR-4 paths.

**Short-term path without full namespace migration:**

Composer supports classmap autoloading with no namespacing required:
```json
{
    "autoload": {
        "classmap": ["includes/"]
    }
}
```

This generates a static classmap at `composer dump-autoload` time, so PHP resolves every class in O(1) hash lookup instead of two `file_exists()` calls per class.

**Impact:**
- **Performance:** Static classmap eliminates per-class filesystem hits, noticeable on sites without opcache warmup.
- **Developer velocity:** Full IDE support (go-to-definition, auto-import), Rector/PHPStan compatibility, and compatibility with third-party tools that expect PSR-4.

---

## 7 — Action→Controller Registry for AJAX Routing

### Problem

AJAX routing currently works by instantiating all controllers upfront and letting each register its own `wp_ajax_*` hooks. The router is implicit — it lives spread across 20+ constructors. Discovering which controller handles a given AJAX action requires grepping across the entire codebase.

### Recommendation — Centralized AJAX action registry

Add a static registry that maps action names to controller classes:

```php
class AIPS_Ajax_Registry {
    private static array $map = [
        'aips_save_schedule'          => AIPS_Schedule_Controller::class,
        'aips_generate_topics'        => AIPS_Author_Topics_Controller::class,
        'aips_approve_topic'          => AIPS_Author_Topics_Controller::class,
        'aips_generate_post_from_topic' => AIPS_Author_Topics_Controller::class,
        // ... all ~100 actions
    ];

    public static function get_controller_for(string $action): ?string {
        return self::$map[$action] ?? null;
    }

    public static function all_actions(): array {
        return array_keys(self::$map);
    }
}
```

`boot_ajax()` resolves only the controller class for `$_REQUEST['action']`. Each controller's constructor registers only its own hooks as before, but it is only constructed when its action is actually being dispatched.

**Bonus — auto-registration:** On admin page views, the registry's `all_actions()` can be used to register thin no-op hooks that resolve the controller lazily on demand, satisfying WordPress's requirement that `wp_ajax_*` hooks are added during `init` while deferring construction.

**Impact:**
- **Performance:** Direct realization of item 3 — one controller constructed per AJAX request instead of twenty.
- **Developer velocity:** The registry is the single source of truth for all AJAX actions. Security audits, action discovery, and documentation generation all become one-file operations.

---

## 8 — In-Request Repository Cache (Identity Map)

### Problem

Repositories currently execute a DB query on every call, even for the same data within a single request. For example, `AIPS_Template_Repository::get_by_id()` is called by the scheduler, the processor, the context factory, and potentially a controller — all in the same request — with no in-memory result sharing.

### Recommendation — Add a simple identity map inside each repository

```php
class AIPS_Template_Repository {
    private array $cache = [];

    public function get_by_id(int $id): ?array {
        if (!isset($this->cache[$id])) {
            $this->cache[$id] = $this->fetch_by_id($id);
        }
        return $this->cache[$id];
    }
}
```

Since repositories will be singletons (items 1–2), the cache lives for the duration of the request and is automatically scoped. No invalidation is needed because WordPress requests are stateless.

For repositories that return collections (`get_all()`, `get_active()`), a simple boolean `$all_loaded` flag prevents repeat `SELECT *` calls.

**Impact:**
- **Performance:** Eliminates repeat identical queries within a request — especially valuable for templates and schedules, which are read multiple times during generation.
- **Developer velocity:** No behavioral changes; callers transparently get cached results. Cache is automatically invalidated across requests. Zero new code surface for developers to maintain.

---

## 9 — Standardized Result / Response Objects for All AJAX Handlers

### Problem

AJAX controllers currently construct their JSON response shape ad-hoc using inline arrays passed to `wp_send_json_success()` / `wp_send_json_error()`. There is no enforced contract on what fields a success or error response includes. This causes inconsistencies that break JavaScript error handling and makes writing reliable AJAX tests harder than it should be.

Current inconsistencies include: some handlers return `{ success: true, data: { message: '...' } }`, others return `{ success: true, data: { html: '...' } }`, and error responses vary between `{ message: '...' }`, `{ error: '...' }`, and `{ code: '...', message: '...' }`.

### Recommendation — `AIPS_Ajax_Response` value object

```php
class AIPS_Ajax_Response {
    public static function success(array $data = [], string $message = ''): void {
        wp_send_json_success(array_merge(['message' => $message], $data));
    }

    public static function error(string $message, string $code = 'error', int $http_status = 200): void {
        wp_send_json_error(['message' => $message, 'code' => $code], $http_status);
    }
}
```

All controllers call `AIPS_Ajax_Response::success()` / `::error()` rather than inline arrays. A contract is established in one place; JavaScript can rely on a consistent structure.

**Impact:**
- **Developer velocity:** New AJAX endpoints follow a template automatically. JavaScript error-handling logic is written once. PHPUnit test assertions on `getActualOutput()` use shared helpers instead of per-test JSON shapes.
- **Performance:** Negligible direct impact, but reduces the number of subtle bugs that require debug cycles.

---

## 10 — Settings Access Centralization Through `AIPS_Config`

### Problem

`AIPS_Config` already exists as a singleton with `get_option()` fallback to defaults. However, many classes still call `get_option()` directly, bypassing the config layer, and some classes call `get_option()` for the same key multiple times in a single method. There is no in-request cache for option reads beyond what WordPress's own `$alloptions` provides.

### Recommendation — All option reads through `AIPS_Config`

1. Replace all direct `get_option('aips_*', ...)` calls with `AIPS_Config::get_instance()->get_option('aips_*')`.
2. Add a per-request resolved-values cache inside `AIPS_Config::get_option()` to skip repeated `get_option()` calls for the same key.
3. Add typed accessor methods for option groups that are read together (AI config, retry config, rate-limit config already exist — extend this pattern to all groups).

**Impact:**
- **Developer velocity:** Refactoring a setting's key or default value requires changing one file (`AIPS_Config`) rather than grepping the entire codebase.
- **Performance:** Reduces repeated `get_option()` calls, which do involve hash lookups and unserialize operations even with WordPress's `$alloptions` cache.

---

## 11 — Summary: Adoption Roadmap

The items above form a coherent dependency chain. The recommended order maximizes early wins while building toward the full architecture:

### Phase 1 — Immediate wins (low risk, no structural changes)

| Item | Action | Effort |
|------|--------|--------|
| 2 | Add `instance()` to `AIPS_History_Repository`, `AIPS_History_Service`, `AIPS_Notifications_Repository`, `AIPS_Logger`, `AIPS_Config` | 1–2 days |
| 8 | Add identity-map cache to `AIPS_Template_Repository`, `AIPS_Schedule_Repository`, `AIPS_Voices_Repository` | 1 day |
| 10 | Route all `get_option('aips_*')` through `AIPS_Config` | 1 day |
| 9 | Introduce `AIPS_Ajax_Response` and migrate all controller handlers | 2 days |

### Phase 2 — Core structural improvements

| Item | Action | Effort |
|------|--------|--------|
| 1 | Build `AIPS_Container`; register existing singletons from Phase 1 | 2 days |
| 3 | Refactor `init()` into `boot_cron()`, `boot_ajax()`, `boot_admin()`, `boot_frontend()` | 2–3 days |
| 7 | Build `AIPS_Ajax_Registry`; wire `boot_ajax()` to use it | 1 day |

### Phase 3 — Developer-velocity structural improvements

| Item | Action | Effort |
|------|--------|--------|
| 4 | Add interfaces for `AIPS_History_Repository`, `AIPS_History_Service`, `AIPS_AI_Service`, `AIPS_Logger` | 2 days |
| 5 | Introduce `AIPS_Generation_Result`, `AIPS_AJAX_Response` typed DTOs; migrate callers | 3 days |
| 6 | Switch to Composer classmap autoloading | 0.5 days |

### Phase 4 — Long-term completions

- Full PSR-4 namespace migration (large effort, staged over multiple releases).
- Caching decorator implementations for `AIPS_History_Repository` and `AIPS_Template_Repository` using object cache, enabled via container swap.

---

## 12 — Relationship to `plan.md`

Items in this document address the structural root causes of nearly every item in `plan.md`:

| plan.md Item | Root cause fixed by |
|---|---|
| A1, E1 — 100+ hook registrations on every page | Items 3 (context bootstrap) + 7 (AJAX registry) |
| A2 — Scheduler instantiated twice | Item 1 (container singleton) + Item 3 (context bootstrap) |
| A3 — Author post generator × 3 | Item 1 (container singleton) |
| A4 — Notifications × 3 | Item 2 (static singleton) |
| A5 — History service × 10 | Item 2 (static singleton) |
| A6 — Cron objects on non-cron requests | Item 3 (context bootstrap) |
| B2 — Object cache TTL/invalidation | Item 10 (config centralization + cache layer) |
| B4 — 3 meta queries per save_post | Addressable via interface + caching decorator (item 4 + 8) |
| B5 — Repository in admin bar constructor | Item 2 (lazy singleton) |
| D2 — Oversized localization object | Item 3 (context bootstrap controls what assets to push) |

Implementing the architectural plan does not replace `plan.md`; the individual fixes in `plan.md` are still valid and can be implemented while the larger architecture is being established. Think of this plan as eliminating the conditions that would generate new `plan.md`-style issues in the future.
