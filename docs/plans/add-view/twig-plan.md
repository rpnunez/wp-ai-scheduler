Plan: Add Twig as a First-Class View Layer
TL;DR — Introduce twig/twig (~3.x) via Composer, build an AIPS_View singleton that wraps Twig\Environment, and a companion AIPS_Twig_WP_Extension that registers WordPress functions/filters. Register AIPS_View in the DI container. Migrate templates incrementally: first fix the two ugliest pages (templates.php, content.php) as the pilot, then sweep the remaining 24 pages. The PHP files stay as-is until their Twig equivalents are verified — zero big-bang rewrites.

Phase 1 — Foundation (no template migrations yet)
1.1 Install Twig

composer require twig/twig:^3.0 in ai-post-scheduler/
This adds vendor/twig/ and updates composer.lock. No runtime impact yet.
1.2 Create includes/class-aips-view.php — AIPS_View

Constructor creates Twig\Loader\FilesystemLoader pointing at AIPS_PLUGIN_DIR . 'templates/admin/twig'
Constructor creates Twig\Environment with:
autoescape => 'html' — all {{ var }} output is HTML-escaped by default; replaces manual esc_html() calls in templates
cache => WP_DEBUG ? false : AIPS_PLUGIN_DIR . 'cache/twig' — file-based cache in production
debug => WP_DEBUG — enables {% dump %} in dev
Registers AIPS_Twig_WP_Extension immediately after
Two public methods:
render(string $template, array $context = array()): void — echoes directly (used by render_page() methods)
capture(string $template, array $context = array()): string — returns string (for nesting/partials from PHP side)
Static factory: none needed — resolved via AIPS_Container
1.3 Create includes/class-aips-twig-wp-extension.php — AIPS_Twig_WP_Extension extends Twig\Extension\AbstractExtension

Registered Twig functions (return Twig\Markup where output is trusted HTML to prevent double-escaping):

Twig name	Calls	Notes
t(text)	__($text, 'ai-post-scheduler')	String; Twig autoescape handles HTML-escaping in output
tn(single, plural, count)	_n($single, $plural, $count, 'ai-post-scheduler')	String
esc_url(url)	esc_url($url)	Returns Twig\Markup (safe)
nonce_field(action, name)	wp_nonce_field($action, $name, true, false)	Returns Twig\Markup
admin_url(path)	admin_url($path)	Returns Twig\Markup
add_query_arg(args, url)	add_query_arg($args, $url)	Returns Twig\Markup
remove_query_arg(key, url)	remove_query_arg($key, $url)	Returns Twig\Markup
aips_page_url(page, args)	AIPS_Admin_Menu_Helper::get_page_url($page, $args)	Returns Twig\Markup
dashicon(name)	Generates <span class="dashicons dashicons-{name}"></span>	Returns Twig\Markup
selected(val, current)	selected($val, $current, false)	Returns Twig\Markup
number_fmt(n)	number_format_i18n($n)	String
Registered Twig filters: |absint, |sanitize_text (for debug/display), |esc_attr (for edge cases where attribute escaping must be explicit).

1.4 Register AIPS_View as a container singleton

In register_container_bindings() in ai-post-scheduler.php, add:

$container->singleton(AIPS_View::class, function() { return new AIPS_View(); });
Only boot_admin() needs it — call only from there via lazy singleton (container defers until first make() call, so cron/ajax paths pay zero cost)
1.5 Create templates/admin/twig/ directory tree


templates/admin/twig/├── layouts/│   └── admin-page.html.twig      # base layout: .wrap.aips-wrap + .aips-page-container├── pages/                         # one file per admin page (populated in Phase 2+)└── partials/                      # reusable fragments    ├── filter-bar.html.twig    ├── pagination.html.twig    └── empty-state.html.twig
1.6 Create templates/admin/twig/layouts/admin-page.html.twig

Contains the outer .wrap.aips-wrap > .aips-page-container shell
Defines blocks: {% block page_header %}, {% block content %}, {% block modals %}
Pages {% extends 'layouts/admin-page.html.twig' %} and fill those blocks
1.7 Add cache/twig/ to .gitignore

Add ai-post-scheduler/cache/ entry so compiled Twig caches are not committed
Phase 2 — Pilot Migration: templates.php + content.php
These are the two most problematic files (direct object instantiation inside template; closure-inside-template for URL building).

2.1 Fix AIPS_Templates::render_page() — move logic out of the template

Currently templates/admin/templates.php does new AIPS_History() and new AIPS_Templates() to pre-fetch stats. Move that logic into AIPS_Templates::render_page() in includes/class-aips-templates.php:

Resolve AIPS_History_Service and AIPS_Template_Repository via the container
Call $history_service->get_all_template_stats() and $templates_class->get_all_pending_stats() in the controller
Also move the get_terms() call (line ~351 in templates.php Step 2 modal) up to render_page()
Pass all results explicitly: $this->view->render('pages/templates.html.twig', ['templates' => $templates, 'template_stats' => $stats, 'pending_stats' => $pending_stats, 'source_groups' => $source_groups])
2.2 Create templates/admin/twig/pages/templates.html.twig

{% extends 'layouts/admin-page.html.twig' %}
Data comes from context array — no PHP logic, no new, no get_terms()
All output uses {{ var }} (HTML-escaped) or Twig functions from AIPS_Twig_WP_Extension
The JS modal (.aips-template-modal) is a {% block modals %} section
i18n strings use {{ t('Add Template') }} pattern
2.3 Fix AIPS_Generated_Posts_Controller::render_page() — extract pagination builder

Currently tab-generated-posts.php defines a $build_generated_posts_page_url closure inline. Move it to render_page() as a private build_page_url(int $page, ...): string method and pass pre-built page URL arrays (or a precomputed array of page links) into the Twig context.

2.4 Create templates/admin/twig/pages/content.html.twig and templates/admin/twig/partials/tab-generated-posts.html.twig

content.php is the tab container; the tabs (tab-generated-posts.php, tab-pending-review.php, tab-partial-generations.php) become partials via {% include 'partials/...' %}
Pagination HTML extracted to partials/pagination.html.twig (reusable across all pages that page)
Empty-state HTML extracted to partials/empty-state.html.twig
2.5 Update AIPS_Templates and AIPS_Generated_Posts_Controller

Both receive AIPS_View via constructor injection: $this->view = AIPS_Container::get_instance()->makeIfExists(AIPS_View::class)
render_page() calls $this->view->render(...) instead of include
Phase 3 — Reusable Partials Extraction
Parallel with or after Phase 2.

3.1 partials/filter-bar.html.twig — generic filter bar (author select, template select, search input, submit/clear buttons). Used by content.html.twig and others.

3.2 partials/pagination.html.twig — generic prev/pages/next pagination. Accepts current_page, total_pages, build_url (a precomputed array of page_number => url from the controller).

3.3 partials/empty-state.html.twig — accepts icon, title, description, optional actions array.

Phase 4 — Full Migration (remaining 22 pages)
Migrate one page at a time. For each page:

Identify which class owns render_page() (see the AIPS_Admin_Menu render map from the exploration above)
Move any logic out of the .php template into the owning controller/render_page() method
Create templates/admin/twig/pages/{page-name}.html.twig
Switch include → $this->view->render()
Pages in rough priority order (most logic → least logic):

history.php (complex filter + pagination — same pattern as content.php)
author-topics.php, authors.php (moderate logic)
schedule.php, calendar.php (data-heavy but mostly display)
settings.php, dashboard.php, onboarding.php
Simpler info pages: system-status.php, dev-tools.php, telemetry.php, taxonomy.php, etc.
Pages rendered directly by AIPS_Admin_Menu (no dedicated controller) need a thin controller class or a render_*_page() static method to own the data prep. Prefer adding a render_page() method to the owning service class (e.g., AIPS_Voices::render_page() already exists).

Phase 5 — Cleanup
Delete migrated .php template files from templates/admin/ after their Twig counterparts are verified
Remove any now-dead include AIPS_PLUGIN_DIR . 'templates/admin/...' calls
Run composer lint:repository-boundary + composer test to confirm nothing broke
Relevant Files
ai-post-scheduler/composer.json — add "twig/twig": "^3.0" to require
ai-post-scheduler/ai-post-scheduler.php — add AIPS_View singleton to register_container_bindings(), load in boot_admin() context
ai-post-scheduler/includes/class-aips-view.php — NEW
ai-post-scheduler/includes/class-aips-twig-wp-extension.php — NEW
ai-post-scheduler/includes/class-aips-templates.php — move logic from template into render_page() (line ~217 is the include)
ai-post-scheduler/includes/class-aips-generated-posts-controller.php — extract $build_generated_posts_page_url closure; inject AIPS_View
ai-post-scheduler/includes/class-aips-admin-menu.php — swap include calls for $this->view->render() per page
ai-post-scheduler/templates/admin/templates.php — migrated → deleted in Phase 5
ai-post-scheduler/templates/admin/tab-generated-posts.php — migrated → deleted in Phase 5
ai-post-scheduler/templates/admin/twig/ — NEW directory tree
ai-post-scheduler/.gitignore — add cache/ entry
Verification
composer require twig/twig:^3.0 installs without errors; vendor/twig/ present
Activate plugin — no PHP fatal errors (AIPS_View registered and resolved from container)
Navigate to Templates admin page — renders identically to the PHP version; no logic errors
Navigate to Content (Generated Posts) tab — renders with correct pagination URLs built from controller
composer test — all tests pass (no regressions in container or AJAX layer)
WP_DEBUG=true — Twig debug mode active, templates cached to disk in cache/twig/
WP_DEBUG=false — cache/twig/ populated with compiled templates; page load time same or faster
Manual XSS test: pass a title containing <script>alert(1)</script> as post title — autoescape prevents execution
Decisions
Twig ^3.x — current stable major; PHP 8.0+ only, matches our PHP 8.2+ requirement
autoescape => 'html' — on by default; templates do NOT need esc_html() around every echo; use |raw only for explicitly trusted HTML
Text domain baked into t() wrapper — avoids repeating 'ai-post-scheduler' in every Twig i18n call; Twig templates only call {{ t('Some text') }}
Twig cache disabled in WP_DEBUG mode — live reload during development; production gets compiled-PHP cache for speed
capture() not render() for nested includes called from PHP (e.g., a modal partial included inside another template from PHP side)
PHP templates kept alive until Twig version verified — zero-downtime migration; old .php files removed only in Phase 5
Out of scope: Frontend templates (non-admin), REST API responses, email templates
Further Considerations
Modal markup in templates.php is ~400 lines of the file and contains complex JS-driven wizard steps. The modals can migrate to Twig as a {% block modals %} section, but the migration is cosmetic (no PHP logic inside them beyond i18n strings) — this is easy but lengthy; could be done in a separate sub-task.

Cache invalidation: The cache/twig/ compiled cache is invalidated automatically by Twig when a .twig file changes (Twig checks filemtime). No manual cache flush needed during development. Consider a hook (save_post, plugin update action) to clear the cache on plugin upgrades — or simply re-use the existing AIPS_Data_Management cache-clearing infrastructure.

AIPS_Admin_Menu pages without a dedicated controller (e.g., schedule.php, calendar.php rendered directly in AIPS_Admin_Menu::render_schedule_page()): these already have a render_*_page() method on AIPS_Admin_Menu. Moving data prep up one level (into AIPS_Admin_Menu) is sufficient — no new controller class needed for simple pages.

Now saving this to docs/plans/add-view-layer/twig-plan.md.

