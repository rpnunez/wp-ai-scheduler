# Database Retention & Telemetry Pruning

_As of: 2026-10-07_

## Overview

This feature adds automated and manual cleanup for three fast-growing tables: `aips_telemetry` (performance/usage events), `aips_history_log` (per-event generation logs), and orphaned rows left behind in `aips_embeddings` / `aips_relationships` and `aips_author_topics` after posts are deleted or topics expire.

Two ways to run it:

- **Manual** — buttons on the System Status admin page let you prune, purge, clean, or optimize any of these tables on demand, with before/after sizes shown live.
- **Automated** — a WP-Cron job (`aips_database_prune_cleanup`) can run these same cleanups on a schedule (daily/twice-daily/weekly), governed by retention windows you configure.

Both are configurable from **Settings → Performance tab**, in the "Database & Data Retention" card.

## Where to configure it

`AIPS_Settings::register_settings()` registers a `aips_database_retention_section` with five fields (master on/off toggle, telemetry retention period, prune frequency, history-log retention period, orphan-cleanup toggles), and every field has a working render callback in `class-aips-settings-ui.php`. They're also correctly read everywhere via `AIPS_Config::get_instance()->get_option()`.

This is now wired up: it's rendered as its own "Database & Data Retention" card on the Settings page's Performance tab (`?page=aips-settings&tab=settings-cache`), alongside the Cache System card.

**Alternative (WP-CLI):** set the options directly, e.g. via WP-CLI from the plugin's Docker container:

```
wp option update aips_auto_prune_enabled 1
wp option update aips_telemetry_retention_value 30
wp option update aips_telemetry_retention_unit days
wp option update aips_telemetry_prune_interval daily
wp option update aips_history_log_retention_value 60
wp option update aips_history_log_retention_unit days
wp option update aips_clean_orphaned_embeddings 1
wp option update aips_clean_expired_topics 1
```

See the reference table at the end of this doc for every option name and its default.

## Finding the System Status page

This page is registered as a **hidden submenu** (`add_submenu_page(null, ...)`), so it won't appear in the left-hand AI Post Scheduler nav. Reach it by direct URL:

```
wp-admin/admin.php?page=aips-status
```

It's also linked automatically from some system error notifications, but there's no persistent menu link today.

## Manual actions on System Status

The page shows a **Database Storage & Table Status** matrix — one row per plugin table with live record count, data size, index size, storage engine, and overhead (highlighted in red above ~1MB). Each row updates immediately after an action runs, and the grand-totals row at the top recomputes from the current row data (it no longer needs a full page refresh to catch up).

| Button | What it does | Confirmation |
| --- | --- | --- |
| **Prune Old Telemetry** | Batched `DELETE` of telemetry rows older than the configured retention window; auto-runs `OPTIMIZE TABLE` afterward if more than 500 rows were removed | Yes |
| **Purge All** (telemetry) | `TRUNCATE TABLE` — instantly resets the telemetry table to 0 bytes. Irreversible. | Yes, requires typing "PURGE" |
| **Prune History Logs** | Same batched-delete pattern as telemetry, against `aips_history_log` | Yes |
| **Clean Orphaned Embeddings** | Removes embedding vectors (and their relationship-graph entries) for posts that no longer exist in WordPress | Yes |
| **Optimize Table** | Runs `OPTIMIZE TABLE` on a single selected table to reclaim fragmented InnoDB space | Yes (added in review — previously ran with no confirmation) |

All destructive actions here require `manage_options` capability and a per-action nonce, and run as chunked batch deletes (not a single giant `DELETE`) to avoid long table locks on large sites.

## "Refresh System" and the destructive-task gate

System Status also has a bundled **Refresh System** button that runs a batch of maintenance tasks in one click (cache warming, notification cleanup, cron resync, etc.). Telemetry/history-log pruning and orphaned-embeddings cleanup are *available* as tasks in that bundle, but they:

- are **unchecked by default** and marked with a "Deletes data" badge,
- are **excluded** from the implicit "run everything" default if no task list is explicitly sent, and
- trigger an **extra confirmation dialog** the moment any of them is checked, before the bundle runs.

(Earlier in development these three ran silently as part of the default bundle — that was fixed during code review.)

## Automated background pruning

When `aips_auto_prune_enabled` is on, a WP-Cron event (`aips_database_prune_cleanup`) runs on the configured interval (daily/twice-daily/weekly) and calls `AIPS_DB_Prune_Service::run_automated_prune()`, which:

1. Prunes telemetry older than the configured retention window.
2. Prunes `aips_history_log` rows older than its own retention window.
3. Cleans orphaned embeddings, if that toggle is on.
4. Cleans expired/rejected author topics, if that toggle is on.

To keep a single slow run from starving later tasks, the four tasks rotate order between runs and the job stops early if it's approaching the PHP execution time limit — anything skipped that tick runs first on the next one, rather than the cron always restarting at task 1.

Enable and tune this from **Settings → Performance tab**, or via the WP-CLI commands above. If the toggle is off (the default), the cron event isn't scheduled at all.

## Option reference

| Option | Default | Controls |
| --- | --- | --- |
| `aips_auto_prune_enabled` | `false` | Master on/off switch for the background cron |
| `aips_telemetry_retention_value` | `30` | Telemetry retention amount (1–365) |
| `aips_telemetry_retention_unit` | `days` | Unit for the above: `days` / `weeks` / `months` |
| `aips_telemetry_prune_interval` | `daily` | Cron frequency: `daily` / `twicedaily` / `weekly` |
| `aips_history_log_retention_value` | `60` | History-log retention amount (1–730) |
| `aips_history_log_retention_unit` | `days` | Unit for the above |
| `aips_clean_orphaned_embeddings` | `true` | Whether automated runs also clean orphaned embeddings |
| `aips_clean_expired_topics` | `true` | Whether automated runs also clean expired/rejected author topics |

All read via `AIPS_Config::get_instance()->get_option('key', default)`; all registered through `register_setting()` with working sanitize callbacks, so they're safe to set via WP-CLI or the Settings UI.
