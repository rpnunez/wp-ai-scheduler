# AI Post Scheduler - WordPress Plugin

Schedule AI-generated posts using Meow Apps AI Engine. Build reusable templates, research topics, bulk-schedule content, and monitor every AI call from one dashboard.

## 🚀 Quick Start for Development

### Using Docker (Recommended)

Get up and running in minutes with our Docker development environment:

```bash
# One command to rule them all
./start-dev.sh
```

That's it! In 2-5 minutes you'll have:
- ✅ WordPress 6.4 with PHP 8.2
- ✅ MariaDB 10.6 database
- ✅ Plugin installed and activated
- ✅ Xdebug ready for debugging
- ✅ phpMyAdmin for database management

**Access your development environment:**
- **WordPress**: http://localhost:8080
- **Admin**: http://localhost:8080/wp-admin (admin/admin)
- **phpMyAdmin**: http://localhost:8082

**Start debugging in VS Code:**
1. Open project in VS Code
2. Press `F5`
3. Select "Listen for Xdebug (Docker)"
4. Set breakpoints and start coding!

📚 **Full Documentation:**
- [Docker Development Guide](DOCKER_DEV_README.md) - Complete setup and usage
- [Quick Reference](DOCKER_QUICKREF.md) - Command cheatsheet
- [Troubleshooting](DOCKER_TROUBLESHOOTING.md) - Common issues and solutions
- [Docker vs XAMPP](DOCKER_VS_XAMPP.md) - Why Docker is better

### Alternative Setup

If you prefer traditional development or can't use Docker:
1. See [COPILOT_SETUP_STEPS.md](COPILOT_SETUP_STEPS.md) for manual setup
2. See plugin's [readme.txt](ai-post-scheduler/readme.txt) for installation instructions

## 📦 Features

- **Template Builder**: Create reusable prompt templates with dynamic variables
- **Voices & Structures**: Define writing personas and article outlines
- **AI-Powered Research**: Discover and score trending topics automatically
- **Bulk Scheduling**: Schedule multiple posts at once with the Planner
- **Flexible Scheduling**: Hourly, daily, weekly, custom frequencies
- **Generation History**: Track all AI calls with detailed logs
- **Featured Images**: Automated AI-generated featured images
- **System Monitoring**: Health checks for environment, database, and cron

## 🛠️ Development

### Daily Workflow with Docker

```bash
# Start environment
make up

# View logs
make logs

# Run tests
make test

# Enter container shell
make shell

# Stop environment
make down
```

### Testing

```bash
# Run all tests
composer test

# Run with coverage
composer test:coverage

# Run specific test file
vendor/bin/phpunit tests/test-specific.php
```

### Code Structure

```
ai-post-scheduler/
├── includes/                # Core PHP classes
│   ├── class-aips-*.php    # All classes use AIPS_ prefix
│   ├── Repositories/       # Database layer
│   ├── Services/           # Business logic
│   └── Controllers/        # Request handlers
├── templates/              # Admin UI templates
├── assets/                 # CSS, JS files
├── tests/                  # PHPUnit tests
└── migrations/             # Database migrations
```

## 📋 Requirements

- **WordPress**: 5.8 or higher
- **PHP**: 8.2 or higher
- **Dependencies**: Meow Apps AI Engine plugin (required)

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Run tests: `make test`
5. Submit a pull request

## 📖 Documentation

- [Architectural Improvements](docs/ARCHITECTURAL_IMPROVEMENTS.md)
- [PSR-4 Refactoring Plan](docs/PSR4_REFACTORING_PLAN.md)
- [Testing Guide](docs/TESTING.md)
- [Changelog](CHANGELOG.md)

## 🐛 Troubleshooting

**Docker Issues?**
- See [Docker Troubleshooting Guide](DOCKER_TROUBLESHOOTING.md)
- Run `make help` for available commands

**Xdebug Not Working?**
- Ensure you're using the Docker setup
- Press `F5` in VS Code to start listening
- Check [Xdebug Troubleshooting](DOCKER_TROUBLESHOOTING.md#xdebug-issues)

**Plugin Issues?**
- Check [System Status](http://localhost:8080/wp-admin/admin.php?page=aips-system-status)
- View logs: `make logs-web`
- Ensure AI Engine is installed and configured

## 📄 License

GPLv2 or later

## 🙏 Credits

Built with ❤️ for WordPress developers who want to automate content creation with AI.

---

**Ready to start? Run `./start-dev.sh` and start coding in minutes! 🚀**

## Links

[![Hypercommit](https://img.shields.io/badge/Hypercommit-DB2475)](https://hypercommit.com/wp-ai-scheduler)
