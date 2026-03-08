---
applyTo: "includes/*.php,ai-post-scheduler/includes/*.php"
---

Use this file for PHP changes in `includes/*.php`.

- This is a WordPress plugin. Always follow WordPress best practices and recommended patterns.
- Use native WordPress functionality for extensibility and security: Actions, Filters, capabilities checks, nonces, and core sanitization/escaping helpers.

- Respect the plugin architecture and separation of concerns:
- Controllers: handle request/response flow (especially AJAX), permissions, nonce checks, input sanitization, and response formatting.
- Repositories: own persistence logic and SQL/database interactions.
- Services: hold business logic, orchestration, and reusable domain operations.
- Supporting patterns in this plugin include Containers, Managers, Processors, and Factory classes; keep each class focused on one responsibility.
- Architecture examples in this codebase:
- Controller example: `ai-post-scheduler/includes/class-aips-author-topics-controller.php`.
- Repository example: `ai-post-scheduler/includes/class-aips-template-repository.php`.
- Service examples: `ai-post-scheduler/includes/class-aips-ai-service.php`, `ai-post-scheduler/includes/class-aips-history-service.php`.
- Container example: `ai-post-scheduler/includes/class-aips-history-container.php`.

- Keep naming conventions consistent with the existing codebase:
- Use `AIPS_`-prefixed, underscore-separated class names.
- Keep file naming consistent with class names (`class-aips-*.php`).

- AJAX hook ownership rule (strict):
- Register `wp_ajax_*` hooks in the respective Controller class, typically in the controller constructor.
- Keep callback methods on the same controller class (for example `ajax_*` handlers).
- Do not scatter AJAX hook registration across repositories/services or unrelated files.

- SQL ownership rule (strict):
- All SQL and database query composition belongs in Repository classes.
- Do not place raw SQL in Controllers, Services, cron/scheduler classes, or templates.
- When interacting with persistence from non-repository code, call repository methods instead.

- History and observability expectations:
- This plugin is designed to keep detailed records of important actions through `AIPS_History_Service` and `AIPS_History_Container`.
- For significant user actions, automation runs, AI requests/responses, and failures, create or reuse a history container and record structured events.
- Prefer explicit lifecycle logging (activity, warning/error, success/failure completion) so troubleshooting and audit trails remain complete.
- Follow existing examples:
- `AIPS_Author_Topics_Controller` registers AJAX hooks and logs actions via `$this->history_service->create(...)->record(...)`.
- `AIPS_Author_Post_Generator` and scheduler flows use history containers for success/failure tracking.
- `AIPS_History_Service` provides a unified `create()` API and activity feed access.

- Baseline coding standards:
- Follow WordPress coding standards and plugin conventions used in this repository.
- Start plugin PHP files with the ABSPATH guard: `if (!defined('ABSPATH')) { exit; }`.
- Use tabs for indentation and `array()` syntax for PHP 7.4 compatibility.
- Sanitize all inputs and escape all output with context-appropriate WordPress helpers.
- Verify nonces for state-changing requests.
- Preserve backward compatibility with PHP 7.4 and WordPress 5.8+.
- Prefer small, focused changes and avoid broad refactors unless requested.
- Add or update PHPUnit tests in `ai-post-scheduler/tests/` when behavior changes.
