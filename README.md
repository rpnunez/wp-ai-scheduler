# AI Post Scheduler

AI Post Scheduler is a WordPress plugin that automates editorial workflows with AI-generated content. It integrates with Meow Apps AI Engine to create, schedule, review, and monitor posts through a WordPress admin interface.

## About

This project is designed for teams that want repeatable, auditable content automation inside WordPress. The plugin supports both template-driven generation and author/topic workflows, with history tracking and scheduled execution via WordPress cron.

Core goals:
- Reduce manual work in content planning and drafting.
- Keep AI generation configurable through reusable admin tools.
- Preserve visibility with logs, review flows, and system status checks.

## Features

- Template-based post generation with reusable prompt variables.
- Voice and article-structure management for consistent output.
- AI-assisted topic research and scoring.
- Flexible scheduling for automated generation workflows.
- Author and topic pipelines for persona-driven content.
- Generated-post review and component regeneration tools.
- History logging and observability for AI calls and lifecycle events.
- Admin notifications and system-status tooling.

## Dependencies

Runtime dependencies:
- WordPress.
- Meow Apps AI Engine plugin (required for generation).

Development dependencies:
- Composer.
- PHPUnit.
- Docker (recommended for local development).

## Requirements

- PHP 8.2+
- WordPress 5.8+
- MySQL/MariaDB

## Project Structure

The plugin code lives in [ai-post-scheduler/](ai-post-scheduler/).

```text
ai-post-scheduler/
├── ai-post-scheduler.php    # Plugin bootstrap
├── includes/                # Core PHP classes (controllers, services, repositories)
├── templates/               # Admin templates
├── assets/                  # Admin CSS/JS
├── tests/                   # PHPUnit tests
└── readme.txt               # WordPress plugin readme
```

## Development

### Quick Start (Docker, Recommended)

> **Requires Bash** — run from Git Bash, WSL2, or a Mac/Linux terminal. Docker Desktop (or Docker Engine + Compose v2) must be running.

```bash
./start-dev.sh          # or: make start-dev
```

This builds the image (`wordpress:7.1-php8.3-apache`), provisions WordPress, the database, phpMyAdmin, the official WordPress **AI plugin**, the **Google AI Provider** connector, and activates AI Post Scheduler. The environment always uses the **WP AI Client** with the **Google** connector.

What `start-dev.sh` does, in order:

1. Creates `.env` from `.env.example` if missing, and gives this checkout a unique **instance id**, container names, and free host ports (so several checkouts/worktrees can run side by side).
2. **Starts a log** at `.artifacts/start-dev-<instance>-<id>.log` (gitignored). Every run's full output is saved there.
3. **Checks `GOOGLE_API_KEY`.** If it is empty or still the `your_google_api_key_here` placeholder, you are prompted for the key (hidden input) and it is saved to your gitignored `.env`. With no terminal (CI, agents) the script fails fast with instructions instead of starting a stack that can't generate content. `AIPS_SKIP_API_KEY_CHECK=1` starts anyway. The key value is never printed or logged.
4. Builds the image (with retries for transient Docker Hub errors), pulls only the images that aren't cached locally, then starts everything and waits for the health checks.

Flags:

| Flag | Purpose |
| :--- | :--- |
| `--import-sql <file.sql\|file.sql.gz>` | Seed the database from a dump (see [Starting with existing data](#starting-with-existing-data)). |
| `--force-import` | With `--import-sql`: back up the current database to `.artifacts/` and replace it even if it has data. |
| `-h`, `--help` | Show usage. |

Local URLs (default ports; `start-dev.sh` picks the first free port at or above these and stores them in `.env`, so check `make urls` for yours):

- WordPress: http://localhost:8080
- Admin: http://localhost:8080/wp-admin (`admin` / `admin`)
- phpMyAdmin: http://localhost:8082
- AI Connectors: http://localhost:8080/wp-admin/options-general.php?page=connectors

See [docs/SETUP.md](docs/SETUP.md) for full setup details.

### Daily Workflow

`make` shortcuts wrap `docker compose` and automatically use this checkout's names, ports, and credentials from `.env`.

```bash
make start-dev   # first time / after pulling changes (provision + build + start, logged)
make start       # start existing containers
make stop        # stop containers (keeps them and all data)
make down        # remove containers (keeps data volumes)
make logs        # follow all logs (logs-web, logs-db for one service)
make shell       # bash in the WordPress container
make wp-shell    # WP-CLI shell
make db-shell    # MariaDB shell (credentials from .env)
make urls        # URLs, ports and credentials for this instance
make status      # container status
make help        # list every target
```

| Target | What it does |
| :--- | :--- |
| `start-dev` | Runs `start-dev.sh` (`ARGS="--import-sql x.sql"` is passed through). |
| `up` / `start` / `stop` / `restart` / `down` | Plain compose lifecycle. `up` does **not** provision names/ports, so use `start-dev` on a fresh checkout. |
| `build` / `rebuild` | Build images / rebuild and restart. |
| `status`, `info`, `urls` | Container status; WordPress + plugin info; URLs and credentials. |
| `logs`, `logs-web`, `logs-db` | Follow logs. |
| `shell`, `wp-shell`, `db-shell` | Shells in the web container, WP-CLI, and the database. |
| `plugin-activate`, `plugin-deactivate`, `plugin-list` | Manage the plugin via WP-CLI. |
| `composer-install`, `composer-update` | Composer inside the plugin directory (container). |
| `db-backup`, `db-restore` | Dump to / restore from `backup.sql`. |
| `reload-php` | Reload Apache so `dev-php.ini` changes apply. |
| `xdebug-on`, `xdebug-off`, `xdebug-status`, `xdebug-log`, `xdebug-log-follow` | Xdebug control (see Debugging). |
| `test`, `test-verbose`, `test-coverage`, `test-ci` | PHPUnit (see [Testing](#testing)). |
| `install`, `clean` | Reinstall / remove everything **including data volumes** (`clean` asks first). |
| `prune` | Remove this project's dangling images only (other projects are untouched). |
| `sync-wp-core` | Mirror WordPress files to `./.docker/wp-html` for IDE path mapping. |

Set `NO_COLOR=1` to disable colored `make` output.

### Starting with existing data

By default every environment starts as an empty WordPress install. To seed it from a dump:

```bash
./start-dev.sh --import-sql path/to/dump.sql         # .sql or .sql.gz
make start-dev ARGS="--import-sql path/to/dump.sql"
```

- The import runs only when the database has **no tables**; on normal reruns (data present) it is skipped and the log says so. `--force-import` first backs the database up to `.artifacts/db-backup-<instance>-<id>.sql`, then replaces it.
- Set `AIPS_IMPORT_SQL` in `.env` for a standing default; the flag overrides it.
- The dump's table prefix is detected and applied (`WP_TABLE_PREFIX`), MySQL 8 collations are mapped for MariaDB, and URLs are rewritten to `http://localhost:<WP_PORT>` (serialization-safe).
- Admin credentials come from the imported database, not `.env`.

### Environment Variables

All variables live in `.env` (created from `.env.example`, gitignored). `docker compose` reads `.env` automatically; `start-dev.sh` and the `Makefile` read it too. Defaults below are what applies when a variable is unset.

**Database**

| Variable | Default | What it does |
| :--- | :--- | :--- |
| `MYSQL_ROOT_PASSWORD` | `root` | MariaDB root password (also used by the test runner to create the test database). |
| `MYSQL_DATABASE` | `wordpress` | Database name for the dev site. |
| `MYSQL_USER` | `wordpress` | Application database user. |
| `MYSQL_PASSWORD` | `wordpress` | Application database password. |
| `WP_TABLE_PREFIX` | `wp_` | WordPress table prefix. Set automatically when importing a dump with another prefix. |
| `DB_IMAGE` | `mariadb:10.6` | Database image. Defaults to an image likely cached locally; `mariadb:10.11` is a good upgrade. |
| `PMA_IMAGE` | `phpmyadmin/phpmyadmin:latest` | phpMyAdmin image (e.g. `phpmyadmin:5`). |

**WordPress**

| Variable | Default | What it does |
| :--- | :--- | :--- |
| `WP_ADMIN_USER` | `admin` | Admin username created on first install. |
| `WP_ADMIN_PASSWORD` | `admin` | Admin password. |
| `WP_ADMIN_EMAIL` | `admin@example.com` | Admin email. |
| `WP_SITE_TITLE` | `WP AI Scheduler Dev` | Site title. |
| `WP_SITE_URL` | `http://localhost:<WP_PORT>` | Derived from `WP_PORT` by `docker-compose.yml`; the value in `.env` is not read by compose. |
| `WP_IMAGE_TAG` | `7.1-php8.3-apache` | Tag of the official [`wordpress`](https://hub.docker.com/_/wordpress) base image (WordPress + PHP + Apache). Changing it rebuilds the image. |
| `WP_CORE_SOURCE` | `image` | `image` uses the WordPress core bundled in the image (reproducible). `trunk` clones WordPress trunk from GitHub on first boot. |
| `AIPS_IMPORT_SQL` | *(unset)* | Default SQL dump for `start-dev.sh` to import into an empty database. |

**AI (always WP AI Client + Google connector)**

| Variable | Default | What it does |
| :--- | :--- | :--- |
| `GOOGLE_API_KEY` | placeholder | Required. `start-dev.sh` prompts for it when empty or a placeholder (`your_*`, `*api_key*`, `*_here*`) and saves it here. |
| `AIPS_AI_PROVIDER` | `WP_AI_CLIENT` | Active AI provider. `start-dev.sh` normalizes this to `WP_AI_CLIENT`. |
| `DEFAULT_AI_CONNECTOR_PLUGIN` | `ai-provider-for-google` | Connector plugin installed/activated at startup. `start-dev.sh` normalizes this to the Google connector. |
| `OPENAI_API_KEY`, `ANTHROPIC_API_KEY` | *(empty)* | Written to `wp-config.php` only if the matching connector plugin is installed. |
| `AIPS_SKIP_API_KEY_CHECK` | `0` | Set to `1` (in the shell) to let `start-dev.sh` start without a Google key. |

**Ports** (auto-assigned to the first free port at or above the default on first run)

| Variable | Default | What it does |
| :--- | :--- | :--- |
| `WP_PORT` | `8080` | Host port for WordPress. |
| `PHPMYADMIN_PORT` | `8082` | Host port for phpMyAdmin. |
| `MYSQL_PORT` | `3307` | Host port for the database (for external DB tools). |
| `XDEBUG_PORT` | `9003` | Host port mapped to the container's Xdebug port. |

**Instance identity** (written by `start-dev.sh`; delete `AIPS_INSTANCE_ID` to re-provision)

| Variable | Default | What it does |
| :--- | :--- | :--- |
| `AIPS_INSTANCE_ID` | `<dir>-<hex>` | Unique id for this checkout; used in container names and log file names. |
| `AIPS_WEB_CONTAINER` | `wp-ai-scheduler-web` | Web container name (suffixed with the instance id). |
| `AIPS_DB_CONTAINER` | `wp-ai-scheduler-db` | Database container name. |
| `AIPS_PMA_CONTAINER` | `wp-ai-scheduler-phpmyadmin` | phpMyAdmin container name. |
| `COMPOSE_PROJECT_NAME` | directory name | Standard Compose variable; also scopes `make prune`. |

**Xdebug** (off by default; see [Debugging](#debugging-vs-code))

| Variable | Default | What it does |
| :--- | :--- | :--- |
| `XDEBUG_MODE` | `off` | `off`, `debug`, `develop,debug`, `profile`, `trace`, `coverage`. |
| `XDEBUG_START_WITH_REQUEST` | `trigger` | `trigger` (only when `XDEBUG_TRIGGER` is present), `yes` (every request, slow), `no`. |
| `XDEBUG_CLIENT_HOST` | `host.docker.internal` | Where the container connects back to your IDE. |
| `XDEBUG_CLIENT_PORT` | `9003` | IDE port. |
| `XDEBUG_IDEKEY` | `PHPSTORM` | IDE key (`.env.example` suggests `VSCODE`). |
| `XDEBUG_LOG` | `/tmp/xdebug.log` | Xdebug log path inside the container (`make xdebug-log`). |
| `XDEBUG_LOG_LEVEL` | `7` | Xdebug log verbosity. |
| `XDEBUG_VERSION` | `3.4.0` | PECL version installed at **build** time (empty = latest compatible). |

**Other**

| Variable | Default | What it does |
| :--- | :--- | :--- |
| `ENTRYPOINT_DEBUG` | `1` | Verbose container startup output (set in `docker-compose.yml`). |
| `PHP_IDE_CONFIG` | `serverName=localhost` | Xdebug server name for IDEs (set in `docker-compose.yml`). |
| `NO_COLOR` | unset | Shell variable: disable colors in `make` output. |
| `WP_USERNAME`, `WP_APP_PASSWORD`, `MCP_BRIDGE_URL` | placeholders | Only for the MCP bridge client; see [docs/MCP_BRIDGE.md](docs/MCP_BRIDGE.md). |

PHP limits (`memory_limit`, upload size, execution time) are not environment variables here: edit `dev-php.ini` and run `make reload-php`. Test-runner variables (`AIPS_WP_TEST_*`, `WP_TESTS_DIR`, `WP_CORE_DIR`) are listed in [TESTING.md](TESTING.md).

### Manual/Non-Docker Setup

- See [ai-post-scheduler/readme.txt](ai-post-scheduler/readme.txt) for plugin installation details.
- PHPUnit is maintained around the Docker-backed WordPress test environment. See [TESTING.md](TESTING.md).

### Debugging (VS Code)

Xdebug is **off by default** for performance. Enable it only when actively debugging:

1. `make xdebug-on` (sets `XDEBUG_MODE=develop,debug` with `trigger`-based startup and recreates `web`; no image rebuild needed).
2. Press `F5` in VS Code and select `Listen for Xdebug (Docker)`.
3. Trigger the request (with the Xdebug browser helper, or append `?XDEBUG_TRIGGER=1`).
4. When finished: `make xdebug-off`.

See [docs/SETUP.md](docs/SETUP.md#xdebug--vs-code-debugging) for the full env-var reference and mode explanations.

### Troubleshooting

| Symptom | Fix |
| :--- | :--- |
| Build fails with `auth.docker.io ... 500/504` | Docker Hub outage. `start-dev.sh` retries automatically; if it still fails, retry later or `docker login`. Images already cached locally are reused without contacting Docker Hub. |
| `start-dev.sh` exits with "GOOGLE_API_KEY is not set" | Run it in a terminal to be prompted, set the key in `.env`, or use `AIPS_SKIP_API_KEY_CHECK=1`. |
| Need to see what a run did | Open the newest `.artifacts/start-dev-*.log`. |
| Ports already in use | Delete `AIPS_INSTANCE_ID` from `.env` and re-run `start-dev.sh` to re-provision. |

## Configuration & Settings Reference

The tables below document all canonical options registered and maintained by AI Post Scheduler.

### 1. Vector Embeddings, Indexer & Background Queue

| Option Key | Type | Default | UI Location | Description |
| :--- | :--- | :--- | :--- | :--- |
| `aips_embeddings_enabled` | `boolean` | `true` | Indexer / AI Settings | Global kill-switch for the vector embeddings subsystem. |
| `aips_embeddings_provider` | `string` | `""` | Indexer / AI Settings | Active vector embeddings provider identifier (`openai`, `google`, `meow`). |
| `aips_embeddings_model` | `string` | `'text-embedding-3-small'` | Indexer / AI Settings | AI vector model used for embeddings generation. |
| `aips_embeddings_dimensions` | `integer` | `1536` | Indexer / AI Settings | Vector coordinate dimensions (e.g. 1536 for OpenAI, 768 for Gemini). |
| `aips_embeddings_env_id` | `string` | `""` | Indexer / AI Settings | Optional Meow Apps AI Engine environment ID for embeddings. |
| `aips_indexer_post_types` | `array` | `['post']` | Indexer / Scope | Array of public WordPress post types eligible for semantic indexing. |
| `aips_embeddings_scope` | `string` | `'aips_only'` | Indexer / Scope | Scope of posts indexed (`aips_only`, `all_posts`, `date_range`). |
| `aips_auto_index_on_publish` | `boolean` | `true` | Indexer / Scope | Master toggle for automatically processing posts when published. |
| `aips_indexer_publish_execution_timing` | `string` | `'queued'` | Indexer / Scope | Execution mode on publish: `'queued'` (debounced batch), `'immediate'` (sync), `'disabled'`. |
| `aips_indexer_batch_size` | `integer` | `10` | Indexer / Scope | Number of posts processed per background batch slice. |
| `aips_indexer_queue_debounce_seconds` | `integer` | `15` | Indexer / Scope | Delay in seconds before a scheduled batch queue executes. |
| `aips_indexer_quota_pause_enabled` | `boolean` | `true` | Indexer / Scope | Auto-pauses background indexing when approaching remote quota limits. |
| `aips_indexer_queue_notifications_enabled`| `boolean` | `true` | Indexer / Scope | Dispatches admin email notifications when queue auto-pauses on quota limits. |
| `aips_indexer_error_pause_duration` | `integer` | `30` | Indexer / Scope | Duration for which indexing halts upon encountering remote rate limit errors. |
| `aips_indexer_error_pause_unit` | `string` | `'minutes'` | Indexer / Scope | Time unit for auto-cooldown duration (`'minutes'`, `'hours'`, `'days'`). |
| `aips_indexer_consecutive_error_threshold`| `integer` | `2` | Indexer / Scope | Number of consecutive remote rate limit errors before triggering cooldown. |
| `aips_indexer_similarity_threshold` | `float` | `0.65` | Indexer / Scope | Minimum cosine similarity (0.40–0.95) for two posts to be considered related. |
| `aips_indexer_post_cluster_threshold` | `float` | `0.65` | Indexer / Clusters | Minimum similarity threshold for grouping connected posts into thematic clusters. |
| `aips_enable_post_insights_ui` | `boolean` | `true` | Settings > AI Scope | Toggles AI Insights column, duplication badges, and editor panels on WP screens. |
| `aips_indexer_topics_continuous_sync` | `boolean` | `true` | Indexer / Scope | Automatically indexes Author Topics into the vector store upon creation. |
| `aips_indexer_topics_execution_timing` | `string` | `'immediate'` | Indexer / Scope | Execution mode on topic creation: `'immediate'` (sync) or `'queued'`. |
| `aips_indexer_scan_entity_scope` | `string` | `'all'` | Indexer / Scope | Default entity scope for backfill scanning (`'all'`, `'posts'`, `'topics'`). |
| `aips_embeddings_rate_limits_enabled` | `boolean` | `true` | Indexer / Limits | Enforces sliding-window API quotas to protect API budgets. |
| `aips_embeddings_daily_limit` | `integer` | `50` | Indexer / Limits | Maximum embedding API calls permitted within a rolling 24-hour window. |
| `aips_embeddings_weekly_limit` | `integer` | `200` | Indexer / Limits | Maximum embedding API calls permitted within a rolling 7-day window. |
| `aips_embeddings_monthly_limit` | `integer` | `500` | Indexer / Limits | Maximum embedding API calls permitted within a rolling 30-day window. |
| `aips_indexer_verbose_history` | `boolean` | `false` | Indexer / Scope | Logs granular step-by-step vector indexing events in the History tab. |

### 2. Author Topics & Semantic Gatekeeper

| Option Key | Type | Default | UI Location | Description |
| :--- | :--- | :--- | :--- | :--- |
| `aips_author_topic_auto_approval_mode` | `string` | `'similarity'` | Settings > Authors | Default policy: `'similarity'` (Dual-Boundary Gate), `'manual'`, or `'all'`. |
| `aips_author_topic_auto_approval_min_score` | `float` | `70.0` | Settings > Authors | Minimum niche relevance percentage against author baseline required for approval. |
| `aips_author_topic_auto_approval_max_similarity` | `float` | `0.85` | Settings > Authors | Maximum duplicate similarity ceiling against existing topics/posts. |
| `aips_author_topic_auto_approval_fallback` | `string` | `'reject'` | Settings > Authors | Sub-threshold handling: `'reject'` (auto-reject) or `'smart_split'` (hold in Pending). |

### 3. General & Editorial Workflow

| Option Key | Type | Default | UI Location | Description |
| :--- | :--- | :--- | :--- | :--- |
| `aips_default_post_status` | `string` | `'draft'` | Settings > General | Default WordPress post status (`draft`, `pending`, `publish`) for generated content. |
| `aips_default_post_author` | `integer` | `1` | Settings > General | Default WordPress user ID assigned as author to generated posts. |
| `aips_default_post_category` | `integer` | `1` | Settings > General | Default WordPress category ID assigned to newly generated posts. |
| `aips_enable_post_review` | `boolean` | `true` | Settings > General | Toggles the dedicated post-generation review and inspection queue. |

### 4. AI Generation, Providers & Token Budgets

| Option Key | Type | Default | UI Location | Description |
| :--- | :--- | :--- | :--- | :--- |
| `aips_ai_provider` | `string` | `'meow'` | Settings > AI | Content generation AI provider (`'meow'`, `'wp_ai_connector'`, `'mock'`). |
| `aips_ai_engine_model` | `string` | `""` | Settings > AI | Specific LLM model name used for content generation. |
| `aips_ai_engine_environment` | `string` | `""` | Settings > AI | Meow Apps AI Engine environment ID for text generation. |
| `aips_token_budget_title` | `integer` | `100` | Settings > AI | Max token allocation for article title generation. |
| `aips_token_budget_content` | `integer` | `4000` | Settings > AI | Max token allocation for article body generation. |
| `aips_token_budget_excerpt` | `integer` | `200` | Settings > AI | Max token allocation for post excerpt generation. |
| `aips_token_budget_seo_title` | `integer` | `100` | Settings > AI | Max token allocation for meta SEO title generation. |
| `aips_token_budget_seo_description` | `integer` | `200` | Settings > AI | Max token allocation for meta SEO description generation. |
| `aips_token_budget_tags` | `integer` | `100` | Settings > AI | Max token allocation for article tags generation. |
| `aips_conversational_generation_enabled` | `boolean` | `false` | Settings > AI | Uses multi-turn conversations to maintain context across generation turns. |
| `aips_conversational_content_turn` | `boolean` | `true` | Settings > AI | Generates content in a dedicated conversational turn. |
| `aips_conversational_metadata_turn` | `boolean` | `true` | Settings > AI | Generates all metadata in a single combined turn to save API calls. |

### 5. Frontend Related Posts Engine

| Option Key | Type | Default | UI Location | Description |
| :--- | :--- | :--- | :--- | :--- |
| `aips_related_posts_enabled` | `boolean` | `true` | Settings > AI | Enables automated semantic related posts retrieval for frontend use. |
| `aips_related_posts_auto_append` | `boolean` | `false` | Settings > AI | Automatically appends related post recommendations to post content. |
| `aips_related_posts_heading` | `string` | `'Related Articles'` | Settings > AI | Heading title displayed above the related posts section. |
| `aips_related_posts_count` | `integer` | `4` | Settings > AI | Number of semantic related posts to display (1–12). |
| `aips_related_posts_layout` | `string` | `'grid'` | Settings > AI | Visual layout for related posts (`'grid'` or `'list'`). |

### 6. Semantic Deduplication & Cannibalization Prevention

| Option Key | Type | Default | UI Location | Description |
| :--- | :--- | :--- | :--- | :--- |
| `aips_deduplication_mode` | `string` | `'warn'` | Settings > AI | Action on duplicate detected: `'warn'` (log warning) or `'block'` (abort generation). |
| `aips_deduplication_threshold` | `float` | `0.85` | Settings > AI | Minimum cosine similarity (0.50–0.99) to flag duplicate content. |
| `aips_topic_similarity_threshold` | `float` | `0.80` | Settings > Feedback | Similarity threshold for flagging duplicate topic suggestions in Feedback. |

### 7. Resilience, Retries & Circuit Breaker

| Option Key | Type | Default | UI Location | Description |
| :--- | :--- | :--- | :--- | :--- |
| `aips_enable_retry` | `boolean` | `true` | Settings > Resilience | Automatically retries failed external API requests. |
| `aips_retry_max_attempts` | `integer` | `3` | Settings > Resilience | Maximum retry attempts before recording a permanent failure. |
| `aips_retry_initial_delay` | `integer` | `2` | Settings > Resilience | Initial backoff delay in seconds between retries. |
| `aips_enable_rate_limiting` | `boolean` | `true` | Settings > Resilience | Throttles outbound AI generation requests. |
| `aips_rate_limit_requests` | `integer` | `10` | Settings > Resilience | Maximum requests permitted per rate limit window. |
| `aips_rate_limit_period` | `integer` | `60` | Settings > Resilience | Rate limit sliding window duration in seconds. |
| `aips_enable_circuit_breaker` | `boolean` | `true` | Settings > Resilience | Trips open to protect external services during consecutive outages. |
| `aips_circuit_breaker_threshold` | `integer` | `5` | Settings > Resilience | Number of consecutive failures before tripping the circuit breaker. |
| `aips_circuit_breaker_timeout` | `integer` | `300` | Settings > Resilience | Cooldown duration in seconds before testing circuit recovery. |

### 8. Site-Wide Content Strategy & Identity

| Option Key | Type | Default | UI Location | Description |
| :--- | :--- | :--- | :--- | :--- |
| `aips_site_niche` | `string` | `""` | Settings > Strategy | Primary topic / subject matter niche of the website. |
| `aips_site_target_audience` | `string` | `""` | Settings > Strategy | Description of the intended target audience persona. |
| `aips_site_content_goals` | `string` | `""` | Settings > Strategy | Primary goals of generated content (e.g. educate, convert). |
| `aips_default_article_structure_id` | `integer` | `0` | Settings > Strategy | Default article structure template ID applied to posts. |
| `aips_site_brand_voice` | `string` | `""` | Settings > Strategy | Brand voice and tone instructions passed to AI generation prompts. |
| `aips_site_content_language` | `string` | `'en'` | Settings > Strategy | Primary language code for article generation. |
| `aips_site_content_guidelines` | `string` | `""` | Settings > Strategy | Editorial rules, formatting guidelines, and stylistic constraints. |
| `aips_site_excluded_topics` | `string` | `""` | Settings > Strategy | Negative keywords and forbidden topics excluded from generation. |
| `aips_research_niches` | `array` | `[]` | Research | Stored topic research niches and search term configurations. |

### 9. Notifications, Cache & Performance

| Option Key | Type | Default | UI Location | Description |
| :--- | :--- | :--- | :--- | :--- |
| `aips_notification_email` | `string` | `""` | Settings > Notifications | Destination email for scheduler failure and quota warnings. |
| `aips_notification_preferences` | `array` | `[...]` | Settings > Notifications | Enabled notification event channels (failures, completions, pauses). |
| `aips_cache_driver` | `string` | `'transient'` | Settings > Performance | Caching driver (`'transient'`, `'object_cache'`, `'database'`). |
| `aips_cache_default_ttl` | `integer` | `3600` | Settings > Performance | Default cache time-to-live in seconds for cached entities. |
| `aips_cache_prefix` | `string` | `'aips_'` | Settings > Performance | Key prefix used for cache isolation across multi-site installations. |
| `aips_cache_monitor_enabled` | `boolean` | `false` | Settings > Developers | Records cache hit/miss statistics for performance debugging. |

## Testing

PHPUnit runs inside the `web` container of the dev stack, against a separate `wp_tests` database (never your development data). Start the stack first (`./start-dev.sh`), then:

```bash
make test                                            # whole suite
make test ARGS="tests/Test_AIPS_DB_Migrations.php"   # one file
make test-verbose                                    # verbose output
make test-coverage                                   # coverage: text summary + HTML report
```

The coverage HTML report is written to `ai-post-scheduler/coverage/` (open `index.html`); generating it takes a while after the tests finish, so let it complete before opening it. Equivalent without `make`: `bash scripts/run-docker-test.sh [phpunit args]`.

`make test-ci` runs the host-side CI runner (`scripts/run-wp-tests-docker.sh`, needs host PHP and Composer). For the full guide, flags, a baseline run, and troubleshooting, see [TESTING.md](TESTING.md).

### Performance Benchmarks

The project includes performance benchmarking to detect regressions:

```bash
cd ai-post-scheduler

# Run performance benchmark
php bin/benchmark.php --wp-core-dir=/tmp/wordpress

# Run with baseline comparison
php bin/benchmark.php \
  --wp-core-dir=/tmp/wordpress \
  --baseline-file=../.github/performance-baseline.json \
  --fail-on-regression
```

Benchmarks can be run manually; no CI workflow currently enforces them automatically.

## Documentation

- [docs/FEATURE_LIST.md](docs/FEATURE_LIST.md) — complete feature reference
- [docs/SETUP.md](docs/SETUP.md) — developer setup and environment guide
- [docs/HOOKS.md](docs/HOOKS.md) — `aips_*` action/filter reference
- [docs/MIGRATIONS.md](docs/MIGRATIONS.md)
- [docs/DEVELOPMENT_GUIDELINES.md](docs/DEVELOPMENT_GUIDELINES.md) — coding and architectural guidelines
- [docs/MCP_BRIDGE.md](docs/MCP_BRIDGE.md) — MCP bridge API reference
- [ai-post-scheduler/CHANGELOG.md](ai-post-scheduler/CHANGELOG.md)

## Contributing

1. Create a branch.
2. Make focused changes.
3. Run tests.
4. Open a pull request.

## License

GPLv2 or later.
