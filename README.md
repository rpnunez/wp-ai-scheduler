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

```bash
./start-dev.sh
```

This provisions WordPress, database services, plugin activation, and debugging support.

Local URLs:
- WordPress: http://localhost:8080
- Admin: http://localhost:8080/wp-admin (admin/admin)
- phpMyAdmin: http://localhost:8082

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

- See [COPILOT_SETUP_STEPS.md](COPILOT_SETUP_STEPS.md) for local setup options.
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

## Documentation

- [docs/FEATURE_LIST.md](docs/FEATURE_LIST.md)
- [docs/HOOKS.md](docs/HOOKS.md)
- [docs/MIGRATIONS.md](docs/MIGRATIONS.md)
- [docs/SETUP.md](docs/SETUP.md)
- [docs/DEVELOPMENT_GUIDELINES.md](docs/DEVELOPMENT_GUIDELINES.md) — project-specific coding and architectural guidelines for developers and AI agents
- [ai-post-scheduler/CHANGELOG.md](ai-post-scheduler/CHANGELOG.md)

## Contributing

1. Create a branch.
2. Make focused changes.
3. Run tests.
4. Open a pull request.

## License

GPLv2 or later.
