# 04 — JavaScript, Frontend, and Full-Stack Assessment

**Scope:** Part 4 of the audit — what in AIPS credibly transfers to a TypeScript/React/Next.js/Node/Express/PostgreSQL role, and what explicitly does not.
**Method:** Direct grep/find across the repository for frontend tech signals; code reading of representative JS modules. Read-only.
**Measured:** 2026-11-14.

This section is written to be used defensively in interview prep: it states plainly what is **not** present so no resume bullet overclaims.

---

## 1. What is NOT in this codebase (confirmed by direct search)

| Technology | Finding |
|---|---|
| TypeScript | **Absent.** No `.ts`/`.tsx` files anywhere in the repository. |
| React | **Absent.** `grep -i react` across all JS source returns only the English word "react" used in code comments (e.g. `authors.js:2734,2862`, `admin.js:748` — "React to ... events"), never the library. |
| Next.js | **Absent.** No `next` dependency, no `pages/`/`app/` router structure (this is a WordPress plugin, not a Next.js app). |
| Node.js server | **Absent as a runtime.** No server-side Node process exists; the only Node usage found is `node:test` for one JS unit test file (see §4). |
| Express | **Absent.** No Express dependency or route definitions anywhere. |
| PostgreSQL | **Absent.** `grep -i postgres` / `pg_connect` across `includes/` returns 0 matches. The entire data layer is MySQL/MariaDB via WordPress's `$wpdb`, accessed only through repository classes per the enforced layering in `01-architecture-and-ai-capabilities.md`. |
| npm/build tooling | **Absent.** No `package.json` anywhere in the repository (root or plugin). No bundler config (webpack/vite/esbuild/rollup). No `.eslintrc*` anywhere. |

**Conclusion for resume purposes:** do not claim React, Next.js, TypeScript, Node.js server-side, Express, or PostgreSQL experience *from this project*. Any such claim would be false and indefensible under interview follow-up.

## 2. What IS in this codebase, and why it still transfers

### 2.1 JavaScript architecture

`ai-post-scheduler/assets/js/` contains roughly 53–64 files and ~40,569 lines of plain jQuery-based JavaScript, with no module bundler — files are enqueued directly by WordPress (`wp_enqueue_script`). Every module follows a consistent, documented pattern (confirmed against `admin.js` and `authors.js`):

```js
(function($) {
  'use strict';
  window.AIPS = window.AIPS || {};
  var AIPS = window.AIPS;

  AIPS.ModuleName = {
    init() { this.bindEvents(); },
    bindEvents() { $(document).on('event', '.selector', this.handler.bind(this)); },
    handler(e) { ... }
  };

  $(document).ready(function() { AIPS.ModuleName.init(); });
})(jQuery);
```

This is a self-contained, namespace-isolated module pattern — the IIFE + shared namespace object is functionally the same problem a React component tree or an ES module graph solves (avoiding global collisions, giving each feature its own lifecycle), just implemented pre-ES-module. It demonstrates the *underlying* skill (structuring a non-trivial client-side codebase into cohesive, testable units) independent of framework.

### 2.2 Client-side templating discipline

`AIPS.Templates` (`assets/js/templates.js`) reads `<script type="text/html">` blocks and does `{{token}}` substitution, with two explicit modes: `render(id, data)` (auto-escaping) and `renderRaw(id, data)` (trusted HTML only). CLAUDE.md's hard rule — "always `AIPS.Templates.render`... Never string concatenation" — is an XSS-prevention discipline enforced as a house convention, the same category of concern that drives React's default-escaping JSX or a sanitize-on-render policy in any templating system.

### 2.3 UI feedback and state patterns

- Toasts: `AIPS.Utilities.showToast(message, type)` (`utilities.js:241`, used at multiple call sites) — never `alert()`.
- Confirm dialogs: `AIPS.Utilities.confirm(message, heading, buttons)` (`utilities.js:354`) — never the blocking native `confirm()`.
- DOM refresh pattern: re-fetch via AJAX and re-render through `AIPS.Templates`, explicitly never `location.reload()` — i.e., the admin UI treats itself as a client-rendered view over server data, refreshed incrementally, which is the same mental model as re-fetching and re-rendering a React component after a mutation.
- `tests/js/pending-review-selection.test.js` (499 lines) is a real regression test for a non-trivial **client-side state bug**: keeping bulk-action checkbox selection consistent between a Cards view and a List view of the same underlying data. This is state-management work in substance (reconciling two view representations of one selection state) even though it predates adopting a state library.

### 2.4 Asynchronous/real-time-style UI: the Heartbeat API

`assets/js/background-processes.js` drives the admin bar pulse and Diagnostics → Background Processes UI off WordPress's Heartbeat API (`window.wp.heartbeat`), with **adaptive polling intervals** — an `ACTIVE_INTERVAL` and a separate, slower `IDLE_INTERVAL` constant, switching based on whether a background job is actually running. This is a genuine browser-performance consideration (don't poll aggressively when there's nothing to poll for) and is architecturally the same problem as choosing a polling vs. WebSocket/SSE strategy and backing off when idle in a React/Node app.

### 2.5 REST API design (one endpoint, done properly)

Exactly one `register_rest_route` call exists in the entire plugin: `includes/class-aips-link-click-tracking-service.php:130`, registering `POST aips/v1/link-click` for anonymous visitor click tracking. It is registered via `add_action('rest_api_init', ...)` from a dedicated `register_frontend()` method, uses `permission_callback => '__return_true'` deliberately (anonymous endpoint, not an oversight — it's visitor-facing click tracking, not an authenticated action), and defines typed/sanitized argument schemas (e.g., `source` → `absint`). This is a small but correctly-designed REST endpoint: versioned namespace (`aips/v1`), explicit permission callback (not omitted), typed argument validation — the exact shape of a well-designed Express or Next.js API route handler.

Everything else in the plugin uses the WordPress AJAX registry pattern (`AIPS_Ajax_Registry`) rather than REST — this is a WordPress-idiomatic choice, not a REST-design gap; the plugin clearly knows how to build a REST route when the use case calls for one.

### 2.6 Backend/frontend separation

54 call sites of `wp_localize_script` / `wp_add_inline_script` across `includes/` pass server-computed data into JS — the standard WordPress data-bridging pattern (equivalent to hydrating client state from server-rendered props). This was confirmed as pervasive (54 sites) but not individually spot-checked per template in this pass; combined with CLAUDE.md's "templates: presentation only, no SQL, no heavy logic" rule and the CI-enforced repository-boundary lint (`01-architecture-and-ai-capabilities.md §4`), the evidence supports a genuine PHP-computes/JS-renders separation rather than logic leaking into templates.

### 2.7 Frontend testing

One JS test file exists: `ai-post-scheduler/tests/js/pending-review-selection.test.js`, using Node's **built-in** `node:test` + `node:assert/strict` + `vm` module (no Jest, no QUnit — neither is installed or configured anywhere in the repo). It is a real regression test for a specific, previously-buggy UI interaction, run via `node --test`. This shows disciplined unit testing of client-side state logic, using a minimal/no-dependency approach rather than a mainstream framework — worth naming accurately (Node's built-in test runner) rather than implying Jest experience that isn't there.

## 3. Honest framing for resume/interview use

**Accurate framing:** "Built and maintained a ~40K-line vanilla JavaScript admin UI layer (jQuery-based, namespaced module pattern) for a WordPress plugin, including a custom templating/auto-escaping system, adaptive real-time-style polling via the WordPress Heartbeat API, and a REST API endpoint with typed argument validation — demonstrating client-side state management, API design, and XSS-safe rendering discipline that transfer directly to React/Node, without claiming direct production experience with React, TypeScript, Next.js, Express, or PostgreSQL from this project specifically."

**Inaccurate/indefensible framing to avoid:** "Built a React/TypeScript frontend," "developed Node.js/Express APIs," "worked with PostgreSQL" — none of these are supported by anything in this repository.

If the candidate has React/TypeScript/Node/Express/PostgreSQL experience from elsewhere, that should be sourced from that other work, not implied by this project. This audit found no evidence of a `website/` directory build step beyond a plain static HTML/CSS/JS marketing site (`website/index.html`, `script.js`, `styles.css` — no framework, no `package.json`), so even the project's public-facing site does not provide cover for those claims.
