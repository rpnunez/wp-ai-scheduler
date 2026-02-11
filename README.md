# AI Post Scheduler for WordPress

A powerful WordPress plugin that automates blog post creation using AI. Generate high-quality content on autopilot with recurring schedules, custom templates, and trending topic research.

[![License](https://img.shields.io/badge/license-GPL--2.0%2B-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg)](https://php.net/)
[![WordPress Version](https://img.shields.io/badge/wordpress-%3E%3D5.8-21759B.svg)](https://wordpress.org/)

## 🚀 Features

- **📝 Template System** - Create reusable AI prompts with custom variables
- **⏰ Scheduling** - Automate post generation with recurring schedules
- **👥 Authors** - Topic approval workflow to prevent duplicate content
- **🎭 Voices** - Define writing styles and personas for varied content
- **📄 Article Structures** - Rotate between different post formats
- **📈 Trending Topics** - AI-powered research discovers hot topics in your niche
- **📅 Planner** - Bulk brainstorm and schedule topics in advance
- **📊 History & Activity** - Complete audit trail of all generations
- **🔧 System Tools** - Health checks, import/export, database management
- **🎨 Modern UI** - Clean, redesigned admin interface

## 📦 Installation

### Requirements

- PHP 8.2 or higher
- WordPress 5.8 or higher
- [Meow Apps AI Engine](https://wordpress.org/plugins/ai-engine/) plugin

### From Repository

```bash
# Clone the repository
git clone https://github.com/rpnunez/wp-ai-scheduler.git

# Install dependencies
cd wp-ai-scheduler/ai-post-scheduler
composer install

# Copy plugin to WordPress
cp -r ../wp-ai-scheduler/ai-post-scheduler /path/to/wordpress/wp-content/plugins/

# Activate in WordPress admin
```

### From Release

1. Download the latest release from the [Releases page](https://github.com/rpnunez/wp-ai-scheduler/releases)
2. Upload to WordPress via Plugins → Add New → Upload
3. Activate the plugin
4. Ensure Meow Apps AI Engine is installed and configured

## 🛠️ Development

### Quick Start

```bash
# Install dependencies
cd ai-post-scheduler
composer install

# Run tests
composer test

# Run tests with coverage
composer test:coverage
```

### Development Setup

See [SETUP.md](./SETUP.md) for detailed development environment setup instructions.

### Testing

See [TESTING.md](./TESTING.md) for comprehensive testing guide.

## 🤝 Contributing

We welcome contributions! Please read our guides before contributing:

- **[CONTRIBUTING.md](./CONTRIBUTING.md)** - How to contribute to the project
- **[BRANCHING_STRATEGY.md](./BRANCHING_STRATEGY.md)** - Git workflow and branching strategy
- **[BRANCH_MANAGEMENT.md](./BRANCH_MANAGEMENT.md)** - For maintainers managing releases

### Quick Contribution Guide

1. **Fork the repository**
2. **Create a feature branch from `dev`**:
   ```bash
   git checkout dev
   git pull origin dev
   git checkout -b feature/your-feature-name
   ```
3. **Make your changes** with tests and documentation
4. **Run tests**: `composer test`
5. **Create a Pull Request** targeting the `dev` branch
6. **Respond to review feedback**

### Branching Strategy

This repository uses a **dual-branch workflow**:

- **`main`** - Production-ready, stable code (protected)
- **`dev`** - Active development (protected)
- **`feature/*`** - Feature branches (merge to `dev`)
- **`hotfix/*`** - Critical fixes (merge to `main` + `dev`)

**For Contributors**: Always create feature branches from `dev` and target your PRs to `dev`.

See [BRANCHING_STRATEGY.md](./BRANCHING_STRATEGY.md) for complete details.

## 📚 Documentation

### For Users
- [Feature List](./docs/FEATURE_LIST.md) - Complete feature inventory
- [Feature Flowcharts](./docs/FEATURE_FLOWCHARTS.md) - Visual workflow diagrams
- [Authors Feature Guide](./docs/AUTHORS_FEATURE_GUIDE.md)
- [Trending Topics Guide](./docs/TRENDING_TOPICS_GUIDE.md)
- Plugin Documentation (in WordPress admin)

### For Developers
- [SETUP.md](./SETUP.md) - Development environment setup
- [TESTING.md](./TESTING.md) - Testing guide
- [CONTRIBUTING.md](./CONTRIBUTING.md) - Contribution guidelines
- [HOOKS.md](./docs/HOOKS.md) - Plugin hooks reference
- [ARCHITECTURAL_IMPROVEMENTS.md](./docs/ARCHITECTURAL_IMPROVEMENTS.md)
- [docs/](./docs/) - Additional documentation

### For Maintainers
- [BRANCHING_STRATEGY.md](./BRANCHING_STRATEGY.md) - Git workflow
- [BRANCH_MANAGEMENT.md](./BRANCH_MANAGEMENT.md) - Release management
- [Feature Analysis Reports](./docs/feature-analysis/) - Automated analysis

## 🏗️ Architecture

The plugin follows a clean architecture pattern:

```
ai-post-scheduler/
├── includes/              # PHP classes
│   ├── Repository/       # Database access layer
│   ├── Service/          # Business logic
│   └── Controller/       # Request handlers
├── templates/            # Admin UI templates
├── assets/               # CSS, JS, images
├── migrations/           # Database migrations
└── tests/                # PHPUnit tests
```

### Key Patterns

- **Repository Pattern** - All database access through repository classes
- **Service Layer** - Business logic isolated in service classes
- **Event System** - WordPress hooks with `aips_` prefix
- **Configuration** - Centralized config via singleton

## 🔒 Security

- All output is escaped (`esc_html()`, `esc_attr()`, `esc_url()`)
- All input is sanitized
- Nonces used for form submissions
- Capability checks on all admin operations
- Prepared statements for database queries

Found a security issue? Please email security@example.com (do not open a public issue).

## 📊 Project Status

- **Version**: 1.7.0
- **Status**: Production-ready
- **Completion**: ~94%
- **Test Coverage**: Good coverage on core features
- **Active Development**: Yes

See [GAP_ANALYSIS_AND_TASKS.md](./docs/GAP_ANALYSIS_AND_TASKS.md) for detailed status.

## 🗺️ Roadmap

### Current Focus (v1.8.x)
- Complete Authors feature frontend
- Improve test coverage
- Enhance documentation
- UI/UX refinements

### Future Plans
- Multi-site support
- Advanced scheduling options
- More AI model integrations
- Performance optimizations
- Mobile-responsive improvements

## 📝 Changelog

See [CHANGELOG.md](./CHANGELOG.md) for version history.

## 🧪 CI/CD

This repository uses GitHub Actions for:
- **PHPUnit Tests** - Runs on push and PRs
- **Code Quality** - Qodana analysis
- **Feature Documentation** - Automated feature reports
- **Release Management** - Automated release PRs

## 📄 License

This plugin is licensed under the GNU General Public License v2.0 or later.

See [LICENSE](./LICENSE) for details.

## 🙏 Credits

### Dependencies
- [Meow Apps AI Engine](https://meowapps.com/ai-engine/) - AI content generation
- [WordPress](https://wordpress.org/) - CMS platform
- [Composer](https://getcomposer.org/) - Dependency management
- [PHPUnit](https://phpunit.de/) - Testing framework

### Contributors

Thanks to all contributors who have helped make this plugin better!

See the [Contributors page](https://github.com/rpnunez/wp-ai-scheduler/graphs/contributors) for a complete list.

## 📞 Support

- **Documentation**: Check the [docs/](./docs/) directory
- **Issues**: [GitHub Issues](https://github.com/rpnunez/wp-ai-scheduler/issues)
- **Discussions**: [GitHub Discussions](https://github.com/rpnunez/wp-ai-scheduler/discussions)
- **Contributing**: See [CONTRIBUTING.md](./CONTRIBUTING.md)

## 🌟 Show Your Support

If you find this plugin useful, please:
- ⭐ Star this repository
- 🐛 Report bugs via Issues
- 💡 Suggest features via Discussions
- 🤝 Contribute code via Pull Requests
- 📢 Share with others who might benefit

## 🔗 Links

- [WordPress Plugin Page](https://wordpress.org/plugins/ai-post-scheduler/) (if published)
- [Documentation](./docs/)
- [Changelog](./CHANGELOG.md)
- [Meow Apps AI Engine](https://wordpress.org/plugins/ai-engine/)

---

**Made with ❤️ for the WordPress community**

*This README was updated as part of the branching strategy implementation - [BRANCHING_STRATEGY.md](./BRANCHING_STRATEGY.md)*
