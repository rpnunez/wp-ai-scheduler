# AI Post Scheduler

**Plan, generate, review, and publish WordPress content on your schedule.**

AI Post Scheduler brings repeatable editorial workflows into WordPress. Build reusable content templates, organize topic ideas, automate generation, and keep a clear review path from first draft to publication. Connect an AI provider that fits your site and workflow.

## Make content production repeatable

- **Create with structure.** Combine reusable prompts, writing voices, and article structures to guide each post.
- **Plan ahead.** Organize topics and authors, then schedule recurring or on-demand generation.
- **Keep editorial control.** Review generated posts, refine individual components, and choose whether content is saved as drafts or published.
- **Understand what happened.** Follow generation history, provider activity, and system status from the WordPress admin.
- **Build a connected library.** Use content indexing and related-content tools to find useful connections across your site.

## A typical workflow

1. Set your site’s editorial direction and choose an AI provider.
2. Create templates, voices, and article structures for the content you publish.
3. Add topics or author workflows and decide when generation should run.
4. Review the resulting posts, make changes, and publish when they are ready.

## Requirements

- WordPress 5.8 or later
- PHP 8.2 or later
- MySQL or MariaDB
- A configured AI provider for AI-powered generation

The plugin uses a provider abstraction and supports the integrations available in your installation. See [AI provider setup](docs/SETUP.md#ai-provider-setup) for current options and configuration.

## Installation

Install the plugin in WordPress, activate it, and configure an available AI provider. Then use the AI Post Scheduler menu to set up your editorial workflow.

For local development, environment setup, and Docker instructions, see the [development setup guide](docs/SETUP.md).

## Documentation

- [Feature overview](docs/FEATURE_LIST.md) — product capabilities and workflows
- [Settings reference](docs/SETTINGS.md) — plugin options and their defaults
- [Development setup](docs/SETUP.md) — local environment, services, and troubleshooting
- [Testing guide](TESTING.md) — PHPUnit and the supported test workflow
- [Hooks reference](docs/HOOKS.md) — actions and filters
- [Migration guide](docs/MIGRATIONS.md) — database and version migrations
- [Development guidelines](docs/DEVELOPMENT_GUIDELINES.md) — architecture and contribution conventions
- [Changelog](ai-post-scheduler/CHANGELOG.md) — release history

## Contributing

Contributions are welcome. Please review the [development guidelines](docs/DEVELOPMENT_GUIDELINES.md), make focused changes, and include tests for behavior changes. Open a pull request with a clear summary of the change.

## License

GPL-2.0-or-later.
