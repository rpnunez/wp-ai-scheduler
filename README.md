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

> **Requires Bash** — run from Git Bash, WSL2, or a Mac/Linux terminal.

```bash
./start-dev.sh
```

This provisions WordPress, database services, the official WordPress **AI plugin**, the **Google AI Connector**, API key credentials from `.env`, and AI Post Scheduler activation.

On first run, `start-dev.sh` automatically creates a `.env` file from `.env.example`. You can edit `.env` to update your `GOOGLE_API_KEY` or select a different default connector.

Local URLs:
- WordPress: http://localhost:8080
- Admin: http://localhost:8080/wp-admin (admin/admin)
- phpMyAdmin: http://localhost:8082
- AI Connectors: http://localhost:8080/wp-admin/options-general.php?page=connectors


See [docs/SETUP.md](docs/SETUP.md) for full setup details.

### Daily Workflow

```bash
# Start services
make up

# Follow logs
make logs

# Open a shell in the app container
make shell

# Stop services
make down
```

### Manual/Non-Docker Setup

- See [ai-post-scheduler/readme.txt](ai-post-scheduler/readme.txt) for plugin installation details.
- PHPUnit is maintained around the Docker-backed WordPress test environment. See [docs/SETUP.md](docs/SETUP.md) for the supported workflow.

### Debugging (VS Code)

Xdebug is **off by default** for performance. Enable it only when actively debugging:

1. `make xdebug-on` (sets `XDEBUG_MODE=develop,debug` with `trigger`-based startup, rebuilds & restarts `web`).
2. Press `F5` in VS Code and select `Listen for Xdebug (Docker)`.
3. Trigger the request (with the Xdebug browser helper, or append `?XDEBUG_TRIGGER=1`).
4. When finished: `make xdebug-off`.

See [docs/SETUP.md](docs/SETUP.md#xdebug--vs-code-debugging) for the full env-var reference and mode explanations.

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

Run test commands from [ai-post-scheduler/](ai-post-scheduler/):

```bash
cd ai-post-scheduler

# Full test suite
composer test

# Verbose output
composer test:verbose

# Coverage
composer test:coverage

# Single test file
vendor/bin/phpunit tests/test-template-processor.php
```

Canonical Docker-backed workflow:

```bash
bash scripts/run-wp-tests-docker.sh
bash scripts/run-wp-tests-docker.sh coverage
```

For agent-session PHPUnit bootstrap behavior and troubleshooting, see [TESTING.md](TESTING.md).

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
