=== AI Post Scheduler ===
Contributors: yourname
Tags: ai, content, automation, scheduling, meow apps, ai engine
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Schedule AI-generated posts using Meow Apps AI Engine. Create templates, set schedules, and automatically generate blog content.

== Description ==

AI Post Scheduler integrates with Meow Apps AI Engine to provide a powerful admin interface for scheduling AI-generated blog posts.

= Features =

* **Template System**: Create reusable prompt templates with dynamic variables
* **Flexible Scheduling**: Schedule posts hourly, every 6 hours, every 12 hours, daily, or weekly
* **Post Configuration**: Set post status, category, tags, and author for generated content
* **Generation History**: Track all generated posts with success/failure status
* **Test Generation**: Preview AI output before scheduling
* **Error Handling**: Automatic logging and retry capabilities

= Requirements =

* WordPress 5.8 or higher
* PHP 7.4 or higher
* Meow Apps AI Engine plugin (required)

= Template Variables =

Use these variables in your prompts:

* `{{date}}` - Current date (e.g., December 17, 2025)
* `{{year}}` - Current year (e.g., 2025)
* `{{month}}` - Current month (e.g., December)
* `{{day}}` - Current day of week (e.g., Wednesday)
* `{{time}}` - Current time (e.g., 14:30)
* `{{site_name}}` - Your site's name
* `{{site_description}}` - Your site's tagline
* `{{random_number}}` - Random number between 1-1000

== Installation ==

1. Upload the `ai-post-scheduler` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure Meow Apps AI Engine is installed and configured
4. Navigate to AI Post Scheduler in your admin menu
5. Create your first template and schedule

== Frequently Asked Questions ==

= Does this plugin require Meow Apps AI Engine? =

Yes, this plugin requires Meow Apps AI Engine to be installed and activated. It uses AI Engine's API to generate content.

= Can I preview generated content before publishing? =

Yes! Use the "Test Generate" feature when creating templates to preview the AI output. You can also set posts to "Draft" status for review before publishing.

= How often does the scheduler run? =

The scheduler runs every hour via WordPress Cron. Posts are generated when their scheduled time arrives.

= Can I use my own AI model? =

Yes, you can specify a custom AI model in the Settings page. Leave empty to use AI Engine's default model.

== Changelog ==

= 1.0.0 =
* Initial release
* Template management system
* Schedule management with multiple frequencies
* Generation history with retry capability
* Logging system for debugging
* Dynamic template variables

== Upgrade Notice ==

= 1.0.0 =
Initial release of AI Post Scheduler.

== Development & Testing ==

For developers contributing to this plugin:

= Running Tests =

1. Navigate to plugin directory:
   `cd ai-post-scheduler`

2. Install Composer dependencies:
   `composer install`

3. Run PHPUnit tests:
   `composer test`

4. Generate coverage report:
   `composer test:coverage`

See TESTING.md in the repository for detailed testing documentation.

= Test Infrastructure =

* PHPUnit 9.6 for unit testing
* 62+ test cases covering core functionality
* Multi-PHP version testing (7.4, 8.0, 8.1, 8.2)
* Code coverage reporting
* GitHub Actions CI/CD integration
