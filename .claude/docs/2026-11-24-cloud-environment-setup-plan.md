# Cloud environment setup script plan ("AI Post Scheduler" cloud environment)

Date: 2026-11-24
Status: plan agreed, not implemented yet

## Goal

When a Claude Code cloud session starts on `rpnunez/wp-ai-scheduler`, the VM should be ready for:

1. **PHPUnit**: the full suite runs on the host, with no Docker (`composer test` / `make test-ci` path).
2. **A runnable WordPress site** with the plugin active and seeded with realistic data from the live site. Claude can then check admin pages in the VM's Chrome for Testing.
3. **Mock AI by default.** Live Gemini calls stay off unless a flag turns them on.

Base VM, as documented: Ubuntu 24.04 with PHP 8.3, Composer, Docker, git, gh, jq and ripgrep. No MariaDB/MySQL server and no svn.

## Decisions

| Topic | Decision |
|---|---|
| Scope | Test library **and** a runnable WP site |
| Seed data | Sanitized export from the live site, made by a new WP-CLI exporter, committed gzipped |
| Seed location | `.docker/seed/aips-seed.sql.gz`, in the repo |
| Seed reuse | Cloud and local: `start-dev.sh` defaults `AIPS_IMPORT_SQL` to the seed |
| Sanitizing | URLs normalized to a placeholder. The exporter also skips telemetry, clicks, logs, cache and embeddings, and redacts credential-like values (the repo is **public**). |
| Extra tools | Subversion only (exact WP test-lib tags). No PHPCS, PHPStan or PCOV for now. |
| AI provider | Mocks by default. Google key wiring exists behind a flag (`AIPS_CLOUD_LIVE_AI=1`). |
| WP version | Env-var controlled: `AIPS_WP_TEST_WP_VERSION` (default `7.1`), the variable `scripts/run-wp-tests-docker.sh` already reads |

## Slices

### Slice 1: live-site seed exporter (`ai-post-scheduler/scripts/export-aips-seed.php`)

Modeled on `export-aips-snapshot.php`. It reuses that exporter's `SECRET_PATTERN` / redaction approach and has the same CLI-only, read-only guard. Output is `aips-seed.sql.gz`, written to the WP-CLI user's home directory (never the web root).

- **Includes:** config and content `aips_` tables (templates, schedule, authors, author_topics, voices, article_structures, prompt_sections, post_slices, campaigns, sources, source_group_terms, taxonomy, trending_topics, topic_feedback, history (summary rows), internal_links, relationships, redirects, post_images, affiliate_links (redacted IDs)). Also the AIPS-generated posts with their postmeta, terms and term relationships, and the `aips_*` options (redacted).
- **Excludes:** `aips_telemetry`, `aips_link_clicks`, `aips_history_log`, `aips_cache*`, `aips_embeddings`, `aips_post_embeddings`, `aips_notifications`, `aips_sources_data`, `aips_background_processes`, `aips_bulk_batch_jobs`, `aips_jobs`, `aips_content_audits`, `wp_users` / `wp_usermeta`, transients.
- **Rewrites:**
  - table prefix → `wp_`
  - site URL → `https://aips-seed.invalid` (placeholder)
  - every `post_author` / author user reference → user 1 (the local `admin`)
  - emails → `@example.test`
- **Header comment:** source `AIPS_VERSION`, `aips_db_version`, export date and row counts. Import then lets `AIPS_DB_Migrations::check_and_run()` upgrade older seeds.
- **Tests:** `tests/Test_AIPS_Seed_Exporter.php`, in the style of `Test_AIPS_Snapshot_Exporter.php`. Covers redaction, the exclude list, URL/prefix rewriting and author remapping.

### Slice 2: produce and gate the seed

1. The user runs the exporter on the live site: `wp eval-file .../export-aips-seed.php`.
2. The user drops the `.sql.gz` into the session, and Claude moves it to `.docker/seed/aips-seed.sql.gz`.
3. `scripts/check-seed-secrets.sh` must pass before commit. It greps the decompressed dump for `AIza`, `sk-`, `sk-ant-`, `ghp_` / `github_pat_`, `xox[bp]-`, `-----BEGIN`, bearer tokens, the live domain, and any email outside `example.test`. The same script runs in CI (`ci-pr.yml`) when `.docker/seed/**` changes.
4. Size budget: under 5 MB gzipped. If it's bigger, trim `aips_history` / post content revisions.

### Slice 3: cloud setup script (`scripts/cloud-setup.sh`) and the environment panel

The environment panel's **Setup script** stays a one-line wrapper, so the real logic is versioned and reviewed:

```bash
#!/usr/bin/env bash
set -euo pipefail
cd "$(git rev-parse --show-toplevel 2>/dev/null || find / -maxdepth 4 -name ai-post-scheduler -type d -path '*wp-ai-scheduler*' -printf '%h\n' -quit)"
bash scripts/cloud-setup.sh
```

`scripts/cloud-setup.sh` is idempotent, logs to `.artifacts/cloud-setup.log`, and must finish in about 5 minutes so the environment cache holds. Steps:

1. **System packages:** `apt-get install -y mariadb-server subversion`, plus `php8.3-mysql` / `php8.3-gd` / `php8.3-xml` / `php8.3-mbstring` only if `php -m` lacks them. Then start MariaDB with `service mariadb start`.
2. **Databases:** create `wordpress` (site) and `wp_ns_tests_docker` (tests), with user `root`/`root` over `127.0.0.1:3306`. The values match `.env.example` defaults.
3. **Composer:** `cd ai-post-scheduler && composer install --no-interaction --prefer-dist`.
4. **WP test library:** `bash scripts/install-wp-tests.sh wp_ns_tests_docker root root 127.0.0.1 "${AIPS_WP_TEST_WP_VERSION:-7.1}" true`, with `WP_TESTS_DIR=/tmp/wordpress-tests-lib`, `WP_CORE_DIR=/tmp/wordpress/`.
5. **WP-CLI:** download the phar to `/usr/local/bin/wp`.
6. **Runnable site** under `/opt/aips-site`:
   1. `wp core download --version=$AIPS_WP_TEST_WP_VERSION`
   2. `wp config create` (DB `wordpress`)
   3. `wp core install --url=http://localhost:8080` with admin/admin
   4. symlink `ai-post-scheduler` into `wp-content/plugins`
   5. import `.docker/seed/aips-seed.sql.gz`
   6. `wp search-replace https://aips-seed.invalid http://localhost:8080 --skip-columns=guid`
   7. `wp plugin activate ai-post-scheduler` (runs migrations)
7. **AI mode:**
   - With `AIPS_CLOUD_LIVE_AI` unset or `0`: set `AIPS_AI_PROVIDER=mock` (or the plugin's test provider) and leave provider keys empty.
   - With `AIPS_CLOUD_LIVE_AI=1`: set the provider to Google and wire the key. Two candidate mechanisms; pick after the check in **Open items**:
     - **(a) Network secret:** a header injected for `generativelanguage.googleapis.com`, with a placeholder key in plugin settings.
     - **(b) Plain variable:** a `GOOGLE_API_KEY` environment variable, written with `wp option update` and never committed.

     Also run a reachability check that warns if the domain isn't in **Allowed domains**.
8. **Smoke check (non-fatal):**
   - `composer lint:repository-boundary`
   - one fast PHPUnit file
   - `curl -fsS localhost:8080/wp-login.php` after a short `wp server` start/stop

   Results go to the log.

Per-session (not cached): a `SessionStart` hook gated on `CLAUDE_CODE_REMOTE=true` runs `scripts/cloud-session-start.sh`. It runs `service mariadb start` and launches `wp server --host=127.0.0.1 --port=8080` in the background, because services don't survive VM pause/resume.

**Environment panel settings**

- **Network:** Limited, with **Allow package managers** checked.
- **Allowed domains:** `wordpress.org`, `*.wordpress.org` (api, downloads, develop.svn), `raw.githubusercontent.com` (WP-CLI phar). Add `generativelanguage.googleapis.com` only when live AI is on.
- **Environment variables:**
  - `AIPS_WP_TEST_WP_VERSION=7.1`
  - `AIPS_CLOUD_LIVE_AI=0`
  - `AIPS_WP_TEST_SKIP_DB_CREATE=false`
  - `WP_TESTS_DIR=/tmp/wordpress-tests-lib`
  - `WP_CORE_DIR=/tmp/wordpress/`

### Slice 4: local reuse

- `start-dev.sh`: when `AIPS_IMPORT_SQL` is unset, `.docker/seed/aips-seed.sql.gz` exists and the DB is empty, use the seed. The existing import path already handles `.gz` and URL search-replace (line ~643). Add the placeholder URL to the replace list.
- Document it in `README.md` / `TESTING.md`.

### Slice 5: docs

- Add a "Cloud sessions" section to `TESTING.md`: what's installed, how to run tests (`cd ai-post-scheduler && composer test`), how to reach the site, and how to turn on live AI.
- Add a pointer in `CLAUDE.md`.

## Verification

1. `shellcheck` on the new scripts.
2. Run `scripts/cloud-setup.sh` in a local `ubuntu:24.04` container with PHP 8.3 + Composer installed to mimic the cloud base. Check the whole run finishes in under 5 minutes, then re-run it to prove idempotence.
3. Start a real cloud session from the "AI Post Scheduler" environment. Run one PHPUnit file, load `/wp-admin` and the AIPS Schedules page in Chrome for Testing, and confirm seeded templates and schedules show up.
4. Flip `AIPS_CLOUD_LIVE_AI=1` in a second environment copy and run `--group abilities-live-ai`.

## Open items to confirm during implementation

- Whether the cloud setup script runs with the cwd at the cloned repo. The wrapper handles both cases.
- Whether a network secret can inject `x-goog-api-key` in a way the plugin's Google provider accepts, or whether mechanism (b) is needed.
- Whether the plugin has a mock/test AI provider selectable by setting. Otherwise, add one in the test bootstrap only and leave the site with no provider.
- The exact list of AIPS-generated posts. Candidates: posts referenced by `aips_history.post_id`, or a postmeta marker. Pick the most reliable one.
