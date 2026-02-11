# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
- [2026-02-11] **MCP Bridge - Phase 3 Tools (v1.3.0)**: Extended MCP Bridge with 5 analytics and testing tools for performance monitoring, metadata access, and configuration management.
  - New Tools: `get_generation_stats` (success rates, performance metrics with period/template filtering), `get_post_metadata` (AI metadata for posts including tokens, model, timing), `get_ai_models` (list available AI models), `test_ai_connection` (verify AI Engine with response time), `get_plugin_settings` (categorized configuration access)
  - Features: Period-based analytics (today/week/month); Template breakdown statistics; Post metadata with token usage and generation time; AI connection testing with custom prompts; Categorized settings (ai/resilience/logging)
  - Testing: 17 new unit test cases (77+ total)
  - Documentation: `MCP_BRIDGE_PHASE3_TOOLS.md` with complete API reference, 5 workflow examples, and integration tips
  - Total MCP tools: 25 (was 20)

- [2026-02-10] **MCP Bridge - Phase 2 Tools (v1.2.0)**: Extended MCP Bridge with 6 additional tools for history management, author management, and component regeneration.
  - New Tools: `get_history` (detailed history with logs by ID or post_id), `list_authors` (author discovery), `get_author` (author details), `list_author_topics` (topics with filtering), `get_author_topic` (topic details), `regenerate_post_component` (regenerate title/excerpt/content/featured_image)
  - Features: Access detailed generation history with logs; Manage authors and their topics; Regenerate individual post components with preview mode; Support for all component types including featured images
  - Testing: 21 new unit test cases (60+ total)
  - Documentation: `MCP_BRIDGE_PHASE2_TOOLS.md` with complete API reference and workflow examples
  - Total MCP tools: 20 (was 14)

- [2026-02-10] **MCP Bridge - Content Generation Tools (v1.1.0)**: Extended MCP Bridge with 3 new tools for AI content generation and management. Phase 1 (MVP) implementation complete.
  - New Tools: `generate_post` (generate single post with overrides), `list_templates` (get all templates with filtering), `get_generation_history` (retrieve past generations with pagination)
  - Features: Generate posts from templates, author topics, or schedules; Apply custom overrides (title, categories, tags, status); Filter and search templates; Paginated history with status/template filtering
  - Testing: 19 new unit test cases (39+ total)
  - Documentation: `MCP_BRIDGE_CONTENT_TOOLS.md` with 15+ code examples and 4 workflow demonstrations
  - Total MCP tools: 14 (was 11)

- [2026-02-10] **MCP Bridge**: New Model Context Protocol bridge (`mcp-bridge.php`) that exposes plugin functionality to AI tools and GitHub Copilot via JSON-RPC 2.0 API. Includes 11 core tools for cache management, database operations, system diagnostics, data export, cron management, and more. Complete with comprehensive documentation, JSON schema, example clients (Python, Shell), unit tests, and validation script. See `MCP_BRIDGE_README.md` for details.
  - Tools: `list_tools`, `clear_cache`, `check_database`, `repair_database`, `check_upgrades`, `system_status`, `clear_history`, `export_data`, `get_cron_status`, `trigger_cron`, `get_plugin_info`
  - Security: WordPress capability-based authentication (`manage_options`)
  - Documentation: Complete API reference with request/response examples
  - Testing: 20+ unit test cases and structure validation script
  - Integration: Example clients for quick integration with MCP-compatible tools

- [2026-02-09] **AI Edit Feature**: New modal interface for regenerating individual post components (title, excerpt, content, featured image) without full post regeneration. Maintains original generation context (template, author, topic) for consistency. Available on both Generated Posts and Pending Review tabs.
  - Backend: `AIPS_AI_Edit_Controller` with 3 AJAX endpoints, `AIPS_Component_Regeneration_Service` for regeneration logic
  - Frontend: Modal UI with change tracking, loading states, and keyboard shortcuts (ESC to close, Ctrl/Cmd+S to save)
  - Security: Nonce verification, capability checks, input sanitization
  - Tests: 21 unit tests with comprehensive coverage

### Refactor
- [2026-05-27] Refactored `AIPS_Scheduler` to extract execution logic into `AIPS_Schedule_Processor`. `AIPS_Scheduler` now orchestrates while `AIPS_Schedule_Processor` handles the business logic of running schedules (locking, generation, logging).
- [2026-05-27] Updated `AIPS_Schedule_Repository` to support `LIMIT` and better joining in `get_due_schedules`.

### Added
- [2026-01-17 08:24:50] Added Developer Mode and Dev Tools page for generating template scaffolds (Voices, Structures, Templates) using AI.
- [2026-01-20 10:00:00] Added client-side search functionality to the Planner topic list, allowing users to filter brainstormed topics before scheduling.
- 2025-12-25: Added client-side search functionality to the Prompt Sections admin page and "Copy to Clipboard" button for section keys.

### Performance
- 2024-05-30: Optimized author topic generation by replacing iterative database inserts with a single bulk INSERT query, reducing database round-trips from N to 1.

### Fixed
- 2024-05-28: Fixed infinite loop in schedule processing where failed "One Time" schedules were incorrectly rescheduled for the next day. They are now deactivated upon failure.

### Added
- 2024-05-27: Added bulk deletion functionality to the Generation History page (Select All/Individual checkboxes, Delete Selected button).
- 2026-01-06: Added configurable featured image sources (AI prompt, Unsplash keywords, or Media Library selection) for templates.

### Fixed
- 2024-05-24: Fixed PHPUnit test compatibility issues by adding `: void` return type to `setUp()` and `tearDown()` methods in test classes, ensuring tests run correctly in limited mode without the WordPress test library.

### Performance
- 2024-05-25: Optimized `AIPS_History_Repository::get_history` to select only necessary columns instead of `SELECT *`, reducing memory usage and database load for large history tables.
- 2024-05-24: Implemented transient caching for History statistics (`AIPS_History_Repository::get_stats`) to reduce database load on dashboard and history pages.

### Fixed
- 2024-05-23: Improved log reading performance by replacing O(N) `SplFileObject` seek with O(1) `fseek` tail reading, preventing potential crashes on large log files.
- 2024-05-22: Removed redundant HTTP response code check in `AIPS_Generator::generate_and_upload_featured_image` to improve code quality and maintainability.
### Added
- [2025-12-21 01:48:42] Added search functionality to the Generation History page to filter posts by title.
- [2024-05-22 10:00:00] Refactored Scheduler: Extracted AJAX handlers to `AIPS_Schedule_Controller`, enhanced `AIPS_Scheduler` with better topic and next_run support, and updated `AIPS_Planner` to use the Scheduler service instead of direct SQL.
- [2024-05-22 10:00:00] Made generated topic titles editable in the Planner before scheduling.
