# Copilot Instructions for AI Post Scheduler

## Repository Overview

This is a WordPress plugin that schedules AI-generated posts using Meow Apps AI Engine. The plugin provides a complete admin interface for creating templates, managing schedules, and automatically generating blog content with AI.

## Technology Stack

- **Language**: PHP 7.4+
- **Platform**: WordPress 5.8+
- **Framework**: WordPress Plugin API
- **Testing**: PHPUnit 9.6 with WordPress PHPUnit library
- **Package Manager**: Composer
- **AI Integration**: Meow Apps AI Engine plugin (required dependency)

## Project Structure

```
ai-post-scheduler/           # Main plugin directory
├── ai-post-scheduler.php    # Main plugin file (entry point)
├── includes/                # Core PHP classes
│   ├── class-aips-*.php    # All classes use AIPS_ prefix
│   ├── Repository classes  # Database layer (History, Schedule, Template)
│   ├── Service classes     # AI Service, Image Service, Logger
│   └── Controller classes  # Schedule Controller, Settings
├── templates/               # Admin UI templates
│   └── admin/              # WordPress admin interface templates
├── migrations/              # Database migration files
├── assets/                  # CSS, JS files
├── tests/                   # PHPUnit tests
│   ├── bootstrap.php       # Test environment setup
│   └── test-*.php          # Test files
├── composer.json            # PHP dependencies
└── phpunit.xml              # PHPUnit configuration
```

## Development Setup

### Prerequisites
- PHP 7.4 or higher
- Composer installed
- WordPress development environment (optional for full integration testing)

### Installation
```bash
# From the repository root
composer install
```

### Running Tests
```bash
# From the repository root
composer test                # Run all tests
composer test:verbose        # Run with verbose output
composer test:coverage       # Generate HTML coverage report
vendor/bin/phpunit ai-post-scheduler/tests/test-specific.php  # Run specific test file
```

### Important Notes
- Tests can run without a full WordPress installation (bootstrap provides mocks)
- The plugin requires Meow Apps AI Engine to be installed and activated
- All commands should be run from the repository root directory

## Coding Standards

### Class Naming
- All classes use the `AIPS_` prefix (AI Post Scheduler)
- Use underscores for class names: `class AIPS_Generator`
- File names match class names: `class-aips-generator.php`

### Code Style
- Use tabs for indentation (WordPress standard)
- Opening braces on same line for methods and functions
- Array syntax: Use `array()` notation (WordPress PHP 7.4 compatibility)
- Always check `!defined('ABSPATH')` at the top of PHP files
- Use WordPress coding standards and naming conventions

### Architecture Patterns
- **Repository Pattern**: Database access through repository classes
  - `AIPS_History_Repository`, `AIPS_Schedule_Repository`, `AIPS_Template_Repository`
  - Use repositories instead of direct `$wpdb` access
- **Service Classes**: Business logic in service classes
  - `AIPS_AI_Service`, `AIPS_Image_Service`, `AIPS_Logger`
- **Event System**: Use native WordPress hooks with `aips_` prefix
  - `do_action('aips_post_generation_completed', $data, $context)`
- **Configuration**: Centralized config in `AIPS_Config` singleton

### Security
- Always escape output: `esc_html()`, `esc_attr()`, `esc_url()`
- Use nonces for form submissions: `wp_verify_nonce()`
- Sanitize input: `sanitize_text_field()`, `sanitize_textarea_field()`
- Use prepared statements in database queries (through repositories)

## Key Files and Their Purposes

- **class-aips-generator.php**: Core content generation logic
- **class-aips-scheduler.php**: WordPress cron scheduling
- **class-aips-templates.php**: Template management UI and logic
- **class-aips-history.php**: Generation history tracking
- **class-aips-ai-service.php**: Interface with Meow Apps AI Engine
- **class-aips-template-processor.php**: Template variable processing
- **class-aips-*-repository.php**: Database operations (use these!)
- **class-aips-config.php**: Configuration and feature flags

## Testing Guidelines

### Test Structure
- Test files: `tests/test-*.php`
- Test classes extend `WP_UnitTestCase`
- Use `setUp()` and `tearDown()` for test fixtures
- One test class per feature/class being tested

### Test Naming
- Test methods: `test_feature_behavior()`
- Use descriptive names that explain what is being tested
- Example: `test_template_processor_replaces_date_variable()`

### Best Practices
- Test both success and failure cases
- Mock external dependencies (AI Engine, file system)
- Use data providers for testing multiple inputs
- Clean up after tests in `tearDown()`
- Aim for clear, focused assertions

## Common Tasks

### Adding a New Feature
1. Create class file in `includes/` with `AIPS_` prefix
2. Add class loading in `ai-post-scheduler.php`
3. Write tests in `tests/test-yourfeature.php`
4. Use repositories for database access
5. Dispatch events for extensibility
6. Update relevant documentation

### Adding a Database Table
1. Create migration file in `migrations/` directory
2. Follow naming convention: `migration-X.Y-feature-name.php`
3. Update `class-aips-upgrades.php` to run migration on version update
4. Create corresponding repository class in `includes/`
5. Add tests for the new repository

### Adding Template Variables
1. Update `class-aips-template-processor.php`
2. Add variable to `process_template()` method
3. Add test in `test-template-processor.php`
4. Document in readme.txt and user docs

## CI/CD

### GitHub Actions Workflows
- **phpunit-tests.yml**: Runs tests on PHP 7.4, 8.0, 8.1, 8.2
- **ci-pr.yml**: Quick checks on pull requests
- **qodana_code_quality.yml**: Code quality analysis

### Test Artifacts
- Coverage reports generated on PHP 8.1
- Test results uploaded as artifacts
- View in Actions tab → Workflow run → Artifacts

## Dependencies

### Required
- **Meow Apps AI Engine**: Core dependency for AI content generation
- PHP ≥7.4 with standard extensions

### Development
- PHPUnit 9.6: Testing framework
- Yoast PHPUnit Polyfills: Backward compatibility
- wp-phpunit/wp-phpunit: WordPress testing library

## Event System

The plugin fires WordPress actions for extensibility:

```php
// Post generation events
do_action('aips_post_generation_started', $data, $context);
do_action('aips_post_generation_completed', $data, $context);
do_action('aips_post_generation_failed', $error, $context);

// Schedule execution events
do_action('aips_schedule_execution_started', $schedule_id);
do_action('aips_schedule_execution_completed', $schedule_id, $result);
do_action('aips_schedule_execution_failed', $schedule_id, $error);
```

Third-party developers can hook into these using standard `add_action()`.

## Important Conventions

1. **Use repositories for database access**: Never use `$wpdb` directly
2. **Dispatch events**: Fire appropriate `aips_*` actions for extensibility
3. **Use config singleton**: `AIPS_Config::get_instance()` for settings
4. **Escape all output**: Use WordPress escaping functions
5. **WordPress coding standards**: Follow WordPress PHP coding standards
6. **Test coverage**: Add tests for new features
7. **Backward compatibility**: Plugin should work on PHP 7.4 and WordPress 5.8+

## Documentation

- **TESTING.md**: Comprehensive testing guide
- **SETUP.md**: Post-clone setup instructions
- **ARCHITECTURAL_IMPROVEMENTS.md**: Architectural decisions and patterns
- **readme.txt**: WordPress plugin documentation
- **CHANGELOG.md**: Version history

## Support and Resources

- WordPress Plugin Handbook: https://developer.wordpress.org/plugins/
- WordPress Coding Standards: https://developer.wordpress.org/coding-standards/
- PHPUnit Documentation: https://phpunit.de/documentation.html
- WordPress Plugin Unit Tests: https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/
