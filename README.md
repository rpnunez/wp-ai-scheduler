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

This provisions WordPress, database services, plugin activation, and debugging support.

Local URLs:
- WordPress: http://localhost:8080
- Admin: http://localhost:8080/wp-admin (admin/admin)
- phpMyAdmin: http://localhost:8082

See [docs/DEV.md](docs/DEV.md) for full setup details and [docs/DEV_HANDBOOK.md](docs/DEV_HANDBOOK.md) for a quick-reference card.

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

- See [docs/DEV.md](docs/DEV.md) for full PHPUnit / WordPress test library setup without Docker.
- See [ai-post-scheduler/readme.txt](ai-post-scheduler/readme.txt) for plugin installation details.

### Debugging (VS Code)

1. Start the Docker environment.
2. Press `F5` in VS Code.
3. Select `Listen for Xdebug (Docker)`.

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

Performance benchmarks run automatically in CI on pull requests and fail PRs when thresholds are exceeded. See [docs/PERFORMANCE.md](docs/PERFORMANCE.md) for details.

## Documentation

- [docs/FEATURES.MD](docs/FEATURES.MD) — complete feature reference
- [docs/DEV.md](docs/DEV.md) — developer setup and environment guide
- [docs/DEV_HANDBOOK.md](docs/DEV_HANDBOOK.md) — quick-reference cheat sheet
- [docs/HOOKS.md](docs/HOOKS.md) — `aips_*` action/filter reference
- [docs/MIGRATIONS.md](docs/MIGRATIONS.md)
- [docs/SETUP.md](docs/SETUP.md)
- [docs/PERFORMANCE.md](docs/PERFORMANCE.md) — performance benchmarking and CI integration
- [docs/DEVELOPMENT_GUIDELINES.md](docs/DEVELOPMENT_GUIDELINES.md) — coding and architectural guidelines
- [ai-post-scheduler/CHANGELOG.md](ai-post-scheduler/CHANGELOG.md)

## Contributing

1. Create a branch.
2. Make focused changes.
3. Run tests.
4. Open a pull request.

## License

GPLv2 or later.
