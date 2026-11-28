---
name: run-aips
description: Launch, open and drive the AI Post Scheduler WordPress dev site for this checkout to see a change working in the real app (wp-admin pages, AIPS admin screens, AJAX actions, cron). Use when asked to run, start, preview, open, screenshot or click through the plugin, or to confirm an admin UI change works beyond PHPUnit. Covers the per-checkout Docker stack, the Browser pane preview, login, plugin activation, WP-CLI, cron and logs. Not for running tests (see TESTING.md) or for production.
---

# Run AIPS

Each checkout (main or a `.claude/worktrees/*` worktree) owns one Docker stack: its own containers (`wp-ai-scheduler-{web,db}-<instance>`) and ports, recorded in its gitignored `.env`. `scripts/lib/aips-instance.sh` creates them on first use from any entry point. Never borrow another checkout's stack.

## 1. Start it

| Situation | Do |
|---|---|
| Desktop app, Browser pane available | `preview_start` with name `aips-wordpress` (from `.claude/launch.json`). It runs `make preview`, which moves WordPress onto the port the pane assigns, syncs the site URL and follows the web log. Stopping the preview does not reliably stop the containers (a hard kill skips the cleanup), so check `docker ps` and `make stop` what you started. |
| Terminal / no pane | `make preview` (foreground; Ctrl+C stops what it started) or `make start` + `make urls`. |
| Checkout never provisioned and you need an AI key or seed data | `./start-dev.sh` (add `--import-sql .docker/seed/aips-seed.sql.gz` when the seed exists). It prompts for `GOOGLE_API_KEY` unless one is set in the environment; use `AIPS_SKIP_API_KEY_CHECK=1` when none is available. |
| Cloud session (`CLAUDE_CODE_REMOTE=true`) | No Docker stack; see the cloud section in `TESTING.md` once it exists. |

The first start on an unprovisioned checkout builds the image and installs WordPress (several minutes). Run it in the background and wait on its log line `[preview] WordPress: http://localhost:<port>` instead of sleeping.

## 2. Log in and find the plugin

- Admin: `http://localhost:<WP_PORT>/wp-admin`. Credentials are the `WP_ADMIN_USER` / `WP_ADMIN_PASSWORD` test values in `.env` (default from `.env.example`); `make urls` prints them. These are local test credentials, fine to type into the local site, never anywhere else.
- AIPS screens live under the **AI Post Scheduler** admin menu: the dashboard is `admin.php?page=ai-post-scheduler`, and the subpages are `admin.php?page=aips-*`. Find the exact slug in `ai-post-scheduler/includes/class-aips-admin-menu.php` instead of guessing.
- Plugin not active: `make plugin-activate`. Inactive plugins: `make plugin-list`.

## 3. Verify a change

1. Reload the page in the Browser pane after editing. PHP and JS are bind-mounted, so no rebuild is needed. Hard-reload when JS/CSS is cached (`?ver=` follows `AIPS_VERSION`).
2. Prefer `read_page` / `get_page_text` over screenshots to check text and structure. Use `read_console_messages` (errors only) after every interaction, and `read_network_requests` with `urlPattern: admin-ajax.php` to inspect AJAX responses (`success`, `data`).
3. Exercise the real flow: open modals, submit forms, check the toast (`AIPS.Utilities.showToast`) and the re-rendered DOM. AIPS never reloads the page after an action, so a full reload is itself a bug.
4. For cron or background behavior: `docker compose exec -T web wp cron event list --path=/var/www/html --allow-root`, then `... wp cron event run <hook> ...`. Background processes are visible in **Diagnostics → Background Processes**.
5. Logs: `make logs-web` (follows, so run it in the background), the plugin's Diagnostics → Logs tab, and `make xdebug-log` when Xdebug is on.
6. Data checks go through the `sql-batch` skill (read-only), never ad-hoc SQL.

## 4. Clean up

- Leave the stack as you found it. If you started it outside the preview, `make stop`. Never `make down`, `make clean`, `docker compose down -v` or volume removal (the project hooks block these).
- Say in your report what you started and stopped.

## Troubleshooting

- **Container name conflict:** a stale *exited* container from another project holds the name. Check with `docker ps -a`, then `docker rm <name>` (never `-v`, never a running one) and retry.
- **Port busy:** `RANDOM_PORTS=1 ./start-dev.sh`, or let the preview pick one (`PORT=<n> make preview`).
- **Redirects to the wrong port:** the site URL is out of sync. `make preview` and `start-dev.sh` both re-sync it.
- **Docker not reachable:** Docker Desktop is not running. Ask the user to start it rather than working around it.
