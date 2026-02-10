# Post-Setup Instructions

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
