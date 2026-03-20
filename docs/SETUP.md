# Post-Setup Instructions

## Required Plugins

**Action Scheduler** is a required dependency of AI Post Scheduler. The plugin will refuse to activate if Action Scheduler is not present and will display an admin notice explaining the requirement.

You can obtain Action Scheduler in two ways:

1. **Install the standalone plugin** — search for "Action Scheduler" on WordPress.org or install it from [https://actionscheduler.org](https://actionscheduler.org).
2. **Install WooCommerce** — WooCommerce bundles Action Scheduler and the library will be picked up automatically.

Activate Action Scheduler **before** activating AI Post Scheduler.

## WP-Cron to Action Scheduler Migration

On the first upgrade after Action Scheduler becomes available, the plugin automatically migrates any existing WP-Cron scheduled events (hooks prefixed with `aips_`) to Action Scheduler actions grouped under `'aips'`. The migration is idempotent and tracked via the `aips_migrated_to_action_scheduler` option so it runs only once.

If you need to re-run the migration (for example after manually clearing scheduled actions), delete that option from the database and trigger an upgrade by temporarily downgrading the stored `aips_db_version` option.

After cloning this repository, please update the `.gitignore` file with the following content:

```
# Composer
ai-post-scheduler/vendor/
ai-post-scheduler/composer.lock

# PHPUnit
ai-post-scheduler/coverage/
ai-post-scheduler/.phpunit.result.cache

# IDE
.idea/
.vscode/
*.swp
*.swo
*~

# OS
.DS_Store
Thumbs.db

# Temporary files
*.log
*.tmp
/tmp/
```

Or simply run:
```bash
cp .gitignore.new .gitignore
```

This ensures that Composer dependencies, test coverage reports, and other development artifacts are not committed to the repository.

## Running Tests

To run the tests:

```bash
cd ai-post-scheduler
composer install
composer test
```

