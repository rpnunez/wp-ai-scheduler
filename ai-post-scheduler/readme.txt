=== AI Post Scheduler ===
Contributors: yourname
Tags: ai, content, automation, scheduling, meow apps, ai engine
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 8.2
Stable tag: 1.7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Schedule AI-generated posts using Meow Apps AI Engine. Build reusable templates, research topics, bulk-schedule content, and monitor every AI call from one dashboard.

== Description ==

AI Post Scheduler integrates with Meow Apps AI Engine to automate your content calendar end-to-end. It combines prompt templates, reusable writing voices, AI-powered topic research, bulk scheduling, and detailed history so you can generate, review, and publish posts on autopilot.

= Features =

* **Template Builder**: Create reusable prompt templates with dynamic variables and preview them with "Test Generate" before saving.
* **Voices & Batch Runs**: Attach optional writing voices (personas with title/content/excerpt guidance) and batch-generate between 1 and 20 posts per run.
* **Article Structures & Prompt Sections**: Compose long-form outlines from reusable prompt sections, and rotate structures per schedule (sequential, random, weighted, alternating) so recurring posts stay varied.
* **Scheduling Frequencies**: Schedule posts hourly, every 4 hours (plugin-added cron interval), every 6 hours, every 12 hours, daily, weekly, bi-weekly, monthly, once, or every specific weekday.
* **Schedule Management**: Set start times, toggle activation, run one-off or recurring schedules, and bulk-insert schedules for many topics at once.
* **Planner (Bulk Topic Scheduling)**: Brainstorm topics with AI, paste your own list, edit inline, and bulk schedule them with a chosen template, start date, and frequency. Uses the `{{topic}}` variable automatically.
* **Trending Topics Research**: Discover and score trending topics (1-100, higher = more timely/relevant), capture keywords/reasons, filter the library (niche, score, freshness), and bulk schedule selected topics. Includes daily automated research via cron.
* **AI Generation Pipeline**: Builds content, title, excerpt, and optional featured image prompts; processes template variables; attaches category/tags/author/status; supports featured image generation with safety checks.
* **Reliability & History**: Retry/backoff, circuit breaker, and structured generation sessions logged to the History table. View per-template generation counts, run-now actions, and generated posts.
* **Seeder for Demo Data**: Generate sample voices, templates, schedules, and planner entries via the Seeder admin page to demo the UI quickly.
* **System Status & Hooks**: Status page checks environment, DB tables, cron, and AI Engine dependency. Hooks documented in HOOKS.md let you extend generation, scheduling, research, and planner events.

= Trending Topics Research =

The Trending Topics feature uses AI to automatically discover what's trending in your niche:

* **AI-Powered Discovery**: Enter your niche and let AI find trending topics
* **Relevance Scoring**: Each topic gets a score (1-100) based on current trends
* **Keyword Analysis**: See related keywords for each topic
* **Freshness Analysis**: Topics are scored based on timeliness and seasonal relevance
* **Research Library**: All discovered topics stored for future reference
* **Smart Filtering**: Filter by niche, score, or freshness (last 7 days)
* **Bulk Scheduling**: Select multiple topics and schedule them all at once
* **Automated Research**: Configure niches to research automatically on schedule

Use this to "automate the automation" - let AI handle content strategy and topic discovery!

= Requirements =

* WordPress 5.8 or higher
* PHP 8.2 or higher
* Meow Apps AI Engine plugin (required)

= What You Can Do (and How) =

1. **Set up voices and structures**: Define voices (writing personas) and article structures/prompt sections to control tone and outline. Structures can rotate automatically on recurring schedules.
2. **Build templates**: Create templates with content/title/image prompts, choose voice, post status/category/tags/author, and enable featured image generation. Use **Test Generate** to preview and adjust.
3. **Plan topics**: Use the Planner to have AI brainstorm 1-50 topics (kept within this range for stable AI responses), or paste your own list. Select topics and bulk schedule them with a template, start date, and frequency.
4. **Research trends**: Open **AI Post Scheduler → Trending Topics**, research a niche, review scored topics with keywords/reasons, filter the library, and bulk schedule selected items. For automated research, configure niches and the cron job (`aips_scheduled_research`) will run daily.
5. **Schedule & run**: Create one-off or recurring schedules (hourly, 4h, 6h, 12h, daily, weekly, bi-weekly, monthly, or specific weekdays). Use "Run Now" to trigger a template immediately.
6. **Review & monitor**: Check **History** for successes/failures with generation logs, and **System Status** for environment/DB/cron health. Use hooks (`aips_*`) to integrate with your workflows.
7. **Demo fast**: Open the **Seeder** page to generate sample voices, templates, schedules, and planner entries for quick demos.

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
* `{{topic}}` / `{{title}}` - Topic/title passed from Planner, schedules, or research

Developers can extend variables via the `aips_template_variables` filter.

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

= How does Trending Topics Research work? =

The Trending Topics feature uses AI to analyze what's currently trending in your specified niche. It considers current events, seasonal relevance, search trends, and provides scored topics with keywords. You can manually research topics or configure automatic daily research.

= Can I automatically schedule trending topics? =

Yes! After researching topics, you can select multiple topics from your library and bulk schedule them with a template and frequency. The system will create schedules for each topic automatically.

== Changelog ==

= 1.7.1 =
* Refactor: moved admin page rendering into namespaced controllers with PSR-4 autoloading.
* Refactor: introduced an AdminRouter to delegate admin menu page rendering.

= 1.6.0 =
* NEW: Trending Topics Research feature - AI-powered trend discovery
* NEW: Automated topic research with scheduled cron jobs
* NEW: Trending Topics admin page with filterable library
* NEW: Bulk scheduling for trending topics
* NEW: Topic relevance scoring (1-100) and freshness analysis
* NEW: Keyword extraction and display for each topic
* NEW: Research statistics dashboard
* Added: AIPS_Research_Service for trend analysis
* Added: AIPS_Trending_Topics_Repository for data persistence
* Added: AIPS_Research_Controller for workflow orchestration
* Added: Database table for trending topics storage
* Added: 41 new test cases for research functionality
* Enhanced: "Automate the automation" - let AI handle content strategy

= 1.0.0 =
* Initial release
* Template management system
* Schedule management with multiple frequencies
* Generation history with retry capability
* Logging system for debugging
* Dynamic template variables

== Upgrade Notice ==

= 1.6.0 =
Major new feature: Trending Topics Research! Automatically discover and schedule trending topics in your niche using AI-powered analysis.

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
