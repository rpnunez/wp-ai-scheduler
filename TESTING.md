# Testing Guide for AI Post Scheduler

This document describes how to run tests for the AI Post Scheduler plugin.

## Prerequisites

- PHP 8.2 or higher
- Composer (https://getcomposer.org/)

## Installation

Install development dependencies using Composer from the repository root:

```bash
composer install
```

This will install:
- PHPUnit 9.6 (testing framework)
- Yoast PHPUnit Polyfills (backward compatibility)
- WordPress PHPUnit library (WordPress testing framework)

## Running Tests

All commands should be run from the repository root:

```bash
pwd
```

### Run all tests
```bash
composer test
```

### Run tests with verbose output
```bash
composer test:verbose
```

### Run tests with code coverage report
```bash
composer test:coverage
```

This generates an HTML coverage report in the `coverage/` directory. Open `coverage/index.html` in your browser to view it.

### Run specific test files
```bash
vendor/bin/phpunit tests/test-template-processor.php
```

### Run PHPUnit directly with options
```bash
vendor/bin/phpunit --testdox --colors=always
```

## Test Structure

Tests are located in `tests/`:

- `test-template-processor.php` - Tests for template variable processing
- `test-interval-calculator.php` - Tests for scheduling interval calculations
- `test-ai-service.php` - Tests for AI service integration
- `test-image-service.php` - Tests for image generation and upload
- `test-security-history.php` - Security-related tests
- `test-logger-performance.php` - Performance tests for logging

## Configuration

### PHPUnit Configuration (`phpunit.xml`)

The `phpunit.xml` file configures:
- Test suite location
- Code coverage settings
- Bootstrap file
- Error reporting
- Memory limits

### Bootstrap File (`tests/bootstrap.php`)

The bootstrap file:
- Loads Composer autoloader
- Initializes WordPress test environment (if available)
- Provides fallback mocks for WordPress functions
- Loads plugin classes

## Running Tests in Different Environments

### Local Development (without WordPress)

Tests can run without a full WordPress installation. The bootstrap file provides mock implementations of WordPress functions and classes.

```bash
composer test
```

### With WordPress Test Library

For full integration testing with WordPress:

1. Install the WordPress test library:
```bash
bash tools/install-wp-tests.sh wordpress_test root '' localhost latest
```

2. Set the `WP_TESTS_DIR` environment variable:
```bash
export WP_TESTS_DIR=/tmp/wordpress-tests-lib
```

3. Run tests:
```bash
composer test
```

### In CI/CD (GitHub Actions)

Tests run automatically on:
- Push to `main` or `develop` branches
- Pull requests to `main` or `develop` branches
- Manual workflow dispatch

The GitHub Actions workflow:
- Tests against PHP 8.2
- Caches Composer dependencies
- Generates code coverage reports (PHP 8.2)
- Uploads test results as artifacts

## Writing New Tests

### Test Class Template

```php
<?php
/**
 * Test case for [Feature Name]
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Feature extends WP_UnitTestCase {

    private $instance;

    public function setUp(): void {
        parent::setUp();
        $this->instance = new AIPS_Feature();
    }

    public function tearDown(): void {
        parent::tearDown();
    }

    /**
     * Test description
     */
    public function test_feature_behavior() {
        $result = $this->instance->method();
        $this->assertEquals('expected', $result);
    }
}
```

### Best Practices

1. **One assertion per test** (when possible)
2. **Use descriptive test names** that explain what is being tested
3. **Test both success and failure cases**
4. **Mock external dependencies** (AI Engine, database, file system)
5. **Clean up after tests** in `tearDown()`
6. **Use data providers** for testing multiple inputs
7. **Add DocBlocks** to test methods explaining what they test

## Troubleshooting

### "Class not found" errors

Make sure all plugin classes are loaded in `bootstrap.php`:

```php
require_once $includes_dir . 'class-aips-feature.php';
```

### Memory limit errors

Increase the memory limit in `phpunit.xml`:

```xml
<ini name="memory_limit" value="1G"/>
```

### Slow tests

Run specific test files instead of the entire suite:

```bash
vendor/bin/phpunit tests/test-specific.php
```

### Coverage report issues

Make sure Xdebug is installed and enabled:

```bash
php -m | grep xdebug
```

## Code Coverage

Current code coverage: ~60% (estimated)

Coverage reports show which lines of code are executed during tests. To view:

1. Generate coverage report:
```bash
composer test:coverage
```

2. Open `coverage/index.html` in your browser

3. Click on files to see line-by-line coverage

### Improving Coverage

Focus on testing:
- Edge cases and error conditions
- All code paths (if/else branches)
- Public methods and APIs
- Complex logic and calculations

## CI/CD Integration

### GitHub Actions Workflows

1. **PHPUnit Tests** (`.github/workflows/phpunit-tests.yml`)
   - Comprehensive testing across PHP versions
   - Coverage report generation
   - Test result artifacts

2. **PR CI** (`.github/workflows/ci-pr.yml`)
   - Quick feedback on pull requests
   - Linting and basic tests
   - Plugin build artifact

### Viewing Test Results

1. Go to the "Actions" tab in GitHub
2. Select a workflow run
3. View test output in the job logs
4. Download artifacts (coverage reports, test results)

## Additional Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WordPress Plugin Unit Tests](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)
- [Yoast PHPUnit Polyfills](https://github.com/Yoast/PHPUnit-Polyfills)

## Support

For issues or questions about testing:
1. Check existing test files for examples
2. Review PHPUnit documentation
3. Create an issue in the repository
