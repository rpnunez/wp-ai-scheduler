# Contributing to AI Post Scheduler

Thank you for your interest in contributing to the AI Post Scheduler WordPress plugin! This guide will help you get started.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Workflow](#development-workflow)
- [Branching Strategy](#branching-strategy)
- [Pull Request Process](#pull-request-process)
- [Coding Standards](#coding-standards)
- [Testing](#testing)
- [Documentation](#documentation)

## Code of Conduct

### Our Pledge

We are committed to providing a welcoming and inspiring community for all. Please be respectful and constructive in all interactions.

### Expected Behavior

- Use welcoming and inclusive language
- Be respectful of differing viewpoints
- Accept constructive criticism gracefully
- Focus on what is best for the community
- Show empathy towards other community members

## Getting Started

### Prerequisites

- PHP 8.2 or higher
- Composer
- Git
- WordPress development environment (optional for full testing)

### Initial Setup

1. **Fork the repository**
   ```bash
   # Visit https://github.com/rpnunez/wp-ai-scheduler and click "Fork"
   ```

2. **Clone your fork**
   ```bash
   git clone https://github.com/YOUR-USERNAME/wp-ai-scheduler.git
   cd wp-ai-scheduler
   ```

3. **Add upstream remote**
   ```bash
   git remote add upstream https://github.com/rpnunez/wp-ai-scheduler.git
   ```

4. **Install dependencies**
   ```bash
   cd ai-post-scheduler
   composer install
   ```

5. **Verify setup**
   ```bash
   composer test
   ```

For detailed setup instructions, see [SETUP.md](./SETUP.md).

## Development Workflow

### 1. Create a Feature Branch

Always branch from `dev` (not `main`):

```bash
# Update your local dev branch
git checkout dev
git pull upstream dev

# Create your feature branch
git checkout -b feature/your-feature-name
```

### 2. Make Your Changes

- Write clean, well-documented code
- Follow WordPress and plugin coding standards
- Add tests for new functionality
- Update documentation as needed

### 3. Test Your Changes

```bash
# Run tests
composer test

# Run with verbose output
composer test:verbose

# Generate coverage report
composer test:coverage
```

### 4. Commit Your Changes

Use conventional commit messages:

```bash
# Format: <type>: <description>
git commit -m "feat: Add new template variable processor"
git commit -m "fix: Resolve scheduler memory leak"
git commit -m "docs: Update API documentation"
```

**Commit Types**:
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, no logic change)
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

### 5. Push and Create Pull Request

```bash
# Push to your fork
git push origin feature/your-feature-name

# Create PR on GitHub targeting the dev branch
```

## Branching Strategy

We use a **dual-branch workflow**:

### Branches

- **`main`**: Production-ready code (protected)
- **`dev`**: Active development (protected)
- **`feature/*`**: Feature development branches
- **`hotfix/*`**: Emergency fixes for production

### Standard Flow

```
feature/your-feature → dev → (testing) → main
```

### Where to Branch From

| Type | Branch From | Target PR To |
|------|------------|--------------|
| New feature | `dev` | `dev` |
| Bug fix | `dev` | `dev` |
| Critical hotfix | `main` | `main` + `dev` |
| Release | N/A | `dev` → `main` |

For complete details, see [BRANCHING_STRATEGY.md](./BRANCHING_STRATEGY.md).

## Pull Request Process

### Before Creating a PR

- [ ] All tests pass locally
- [ ] Code follows WordPress coding standards
- [ ] Documentation is updated
- [ ] Commits are clean and well-organized
- [ ] Branch is up-to-date with `dev`

### Creating the PR

1. **Use a clear title**:
   - `feat: Add template search functionality`
   - `fix: Resolve duplicate post generation`
   - `docs: Update testing guide`

2. **Fill out the PR template** (when available)
   - Describe what changed and why
   - Link related issues
   - Add testing notes
   - Include breaking changes if any

3. **Target the correct branch**:
   - Most PRs → `dev`
   - Critical fixes → `main` (with justification)

4. **Request reviews**:
   - Add relevant reviewers
   - Respond to feedback promptly
   - Make requested changes

### During Review

- Be open to feedback
- Respond to comments promptly
- Make requested changes in new commits
- Don't force-push after review starts (unless requested)
- Resolve conversations when addressed

### After Approval

- Squash commits if requested
- Wait for maintainer to merge
- Delete your feature branch after merge

## Coding Standards

### PHP Standards

We follow **WordPress PHP Coding Standards**:

```php
// Good
function aips_process_template( $template_id, $data ) {
    if ( ! $template_id ) {
        return false;
    }
    
    $processor = new AIPS_Template_Processor();
    return $processor->process( $template_id, $data );
}

// Class naming
class AIPS_Feature_Name {
    // Use AIPS_ prefix for all classes
}

// File naming: class-aips-feature-name.php
```

### Key Conventions

1. **Naming**:
   - Classes: `AIPS_Class_Name`
   - Functions: `aips_function_name()`
   - Files: `class-aips-class-name.php`

2. **Indentation**: Tabs (not spaces)

3. **Braces**: Opening brace on same line

4. **Arrays**: Use `array()` notation (PHP 7.4 compatibility)

5. **Security**:
   - Always escape output: `esc_html()`, `esc_attr()`, `esc_url()`
   - Use nonces: `wp_verify_nonce()`
   - Sanitize input: `sanitize_text_field()`
   - Use prepared statements for database queries

6. **Database Access**: Use repository classes, never direct `$wpdb`

### Code Organization

```php
<?php
/**
 * Feature description
 *
 * @package AI_Post_Scheduler
 * @since 1.7.0
 */

// Security check
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class description.
 *
 * @since 1.7.0
 */
class AIPS_Feature {
    
    /**
     * Constructor.
     */
    public function __construct() {
        // Initialization
    }
    
    /**
     * Method description.
     *
     * @param string $param Parameter description.
     * @return bool Success status.
     */
    public function method_name( $param ) {
        // Implementation
    }
}
```

## Testing

### Running Tests

```bash
# Run all tests
composer test

# Run specific test file
vendor/bin/phpunit ai-post-scheduler/tests/test-specific.php

# Run with coverage
composer test:coverage
```

### Writing Tests

```php
<?php
/**
 * Test for Feature
 */
class Test_AIPS_Feature extends WP_UnitTestCase {
    
    protected $feature;
    
    public function setUp(): void {
        parent::setUp();
        $this->feature = new AIPS_Feature();
    }
    
    public function tearDown(): void {
        parent::tearDown();
    }
    
    public function test_feature_does_something() {
        $result = $this->feature->do_something();
        $this->assertTrue( $result );
    }
}
```

### Test Requirements

- All new features must have tests
- Bug fixes should include regression tests
- Aim for high coverage on critical paths
- Test both success and failure cases

For complete testing guide, see [TESTING.md](./TESTING.md).

## Documentation

### Code Documentation

Use PHPDoc format:

```php
/**
 * Brief description of function.
 *
 * Longer description with more details if needed.
 *
 * @since 1.7.0
 *
 * @param string $param1 Description of first parameter.
 * @param int    $param2 Description of second parameter.
 * @return bool True on success, false on failure.
 */
function aips_example_function( $param1, $param2 ) {
    // Implementation
}
```

### Inline Comments

- Explain **why**, not **what**
- Use comments sparingly, prefer self-documenting code
- Update comments when code changes

```php
// Good: Explains reasoning
// Use batch processing to avoid memory limits with large datasets
foreach ( array_chunk( $items, 100 ) as $batch ) {
    process_batch( $batch );
}

// Bad: States the obvious
// Loop through items
foreach ( $items as $item ) {
    process_item( $item );
}
```

### Documentation Files

When adding major features:
- Update README.md if user-facing
- Update CHANGELOG.md
- Add to documentation in `docs/` if complex
- Update plugin header version and tested-up-to

## Types of Contributions

### Bug Reports

- Use issue template (if available)
- Include steps to reproduce
- Provide error messages/logs
- Specify environment (PHP, WordPress, plugin versions)

### Feature Requests

- Describe the use case
- Explain expected behavior
- Consider backward compatibility
- Be open to discussion

### Code Contributions

- Follow this guide
- Start with small contributions
- Ask questions if unsure
- Be patient with review process

### Documentation

- Fix typos and clarify wording
- Add examples
- Update outdated information
- Improve organization

## Getting Help

### Questions

- Check existing documentation
- Search closed issues
- Open a discussion (not an issue)
- Be specific about what you need help with

### Common Issues

See [SETUP.md](./SETUP.md) for troubleshooting common setup problems.

## Recognition

Contributors are recognized in:
- GitHub contributors page
- CHANGELOG.md (for significant contributions)
- Plugin credits (for major features)

## License

By contributing, you agree that your contributions will be licensed under the same license as the project (see LICENSE file).

## Additional Resources

- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Conventional Commits](https://www.conventionalcommits.org/)

## Questions?

If you have questions about contributing:
1. Check this guide and related documentation
2. Search existing issues and discussions
3. Open a discussion (not an issue) for questions
4. Be patient - maintainers are volunteers

Thank you for contributing! 🎉
