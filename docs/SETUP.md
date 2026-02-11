# Post-Setup Instructions

## Docker Development Environment

For a complete development environment with WordPress, use the Docker setup:

```bash
./start-dev.sh  # On Unix/Linux/Mac
start-dev.bat   # On Windows (CMD)
./start-dev.ps1 # On Windows (PowerShell)
```

See [DOCKER_README.md](../DOCKER_README.md) in the repository root for full documentation.

## Manual Setup

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
