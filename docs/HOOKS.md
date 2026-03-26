# AI Post Scheduler Hooks Documentation

This document lists all the actions and filters available in the AI Post Scheduler plugin. Developers can use these hooks to extend the plugin's functionality.

## Action Hooks

### Post Generation

#### `aips_post_generation_started`
Fires when the post generation process begins.

*   **Arguments:**
    *   `int $template_id`: The ID of the template being used.
    *   `string $topic`: The topic of the post (if applicable).

#### `aips_post_generation_failed`
Fires when the post generation process fails.

*   **Arguments:**
    *   `int $template_id`: The ID of the template being used.
    *   `string $error_message`: The error message describing the failure.
    *   `string $topic`: The topic of the post (if applicable).

#### `aips_post_generated`
Fires after a post has been successfully generated and saved.

*   **Arguments:**
    *   `int $post_id`: The ID of the newly created WordPress post.
    *   `object $template`: The template object used for generation.
    *   `int $history_id`: The ID of the history record associated with this generation.

#### `aips_post_generation_incomplete`
Fires after a post has been created but one or more requested components failed to generate.

*   **Arguments:**
    *   `int $post_id`: The ID of the created WordPress post.
    *   `array $component_statuses`: Per-component success map keyed by `post_title`, `post_excerpt`, `post_content`, and `featured_image`.
    *   `AIPS_Generation_Context $context`: The generation context used for the request.
    *   `int $history_id`: The ID of the history record associated with this generation.

#### `aips_post_components_updated`
Fires after AI Edit saves updated post components.

*   **Arguments:**
    *   `int $post_id`: The updated post ID.
    *   `array $updated_components`: Updated component keys (`title`, `excerpt`, `content`, `featured_image`).
    *   `array $components`: Raw component payload submitted by AI Edit.

#### `aips_generation_failed`
Fires when a non-scheduled/manual generation flow fails and should create a high-priority notification.

*   **Arguments:**
    *   `array $payload`: Associative payload containing keys such as `resource_label`, `error_code`, `error_message`, `context_type`, `context_id`, `history_id`, `creation_method`, `url`, `dedupe_key`, and `dedupe_window`.

#### `aips_quota_alert`
Fires when the AI service detects quota/rate-limit/circuit-breaker conditions that should notify administrators.

*   **Arguments:**
    *   `array $payload`: Associative payload containing keys such as `request_type`, `error_code`, `error_message`, `url`, `dedupe_key`, and `dedupe_window`.

#### `aips_integration_error`
Fires when the AI Engine integration is unavailable or misconfigured.

*   **Arguments:**
    *   `array $payload`: Associative payload containing keys such as `request_type`, `error_code`, `error_message`, `url`, `dedupe_key`, and `dedupe_window`.

#### `aips_partial_generation_state_reconciled`
Fires after partial-generation metadata is reconciled from current post values.

*   **Arguments:**
    *   `int $post_id`: The reconciled post ID.
    *   `array $component_statuses`: Normalized component status map.
    *   `string $source`: Reconciliation source (`save_post` or `aips_post_components_updated`).

### Schedule Execution

#### `aips_schedule_execution_started`
Fires when a specific schedule item begins execution.

*   **Arguments:**
    *   `int $schedule_id`: The ID of the schedule being executed.

#### `aips_schedule_execution_failed`
Fires when a schedule item fails to execute.

*   **Arguments:**
    *   `int $schedule_id`: The ID of the schedule.
    *   `string $error_message`: The error message.

#### `aips_scheduler_error`
Fires when a scheduled automation run fails or cannot obtain its execution lock and should create a high-priority notification.

*   **Arguments:**
    *   `array $payload`: Associative payload containing keys such as `schedule_id`, `template_id`, `schedule_name`, `error_code`, `error_message`, `frequency`, `history_id`, `url`, `dedupe_key`, and `dedupe_window`.

#### `aips_schedule_execution_completed`
Fires when a schedule item is successfully executed.

*   **Arguments:**
    *   `int $schedule_id`: The ID of the schedule.
    *   `int $result`: The result of the execution (post ID).

### Research & Trending Topics

#### `aips_trending_topic_scheduled`
Fires when a trending topic is converted into a schedule.

*   **Arguments:**
    *   `int $schedule_id`: The ID of the newly created schedule.
    *   `string $topic`: The topic text.
    *   `int $template_id`: The ID of the template assigned to the schedule.

#### `aips_scheduled_research_completed`
Fires when the automated research background process completes.

*   **Arguments:**
    *   `string $niche`: The niche that was researched.
    *   `int $saved_count`: The number of new topics saved.
    *   `array $topics`: The array of raw topic data retrieved.

### System / Operational Errors

#### `aips_system_error`
Fires when a plugin-level operational error occurs during activation, upgrade, or cron execution and should create a high-priority notification.

*   **Arguments:**
    *   `array $payload`: Associative payload containing keys such as `title`, `error_code`, `error_message`, `schedule_id`, `template_id`, `url`, `dedupe_key`, and `dedupe_window`.

### Planner

#### `aips_planner_topics_generated`
Fires after topics have been successfully generated by AI in the Planner.

*   **Arguments:**
    *   `array $topics`: Array of generated topic strings.
    *   `string $niche`: The niche/topic prompt used for generation.

#### `aips_planner_bulk_scheduled`
Fires after a bulk scheduling operation in the Planner is completed.

*   **Arguments:**
    *   `int $count`: The number of items scheduled.
    *   `int $template_id`: The ID of the template used.

### Article Structures

#### `aips_structure_created`
Fires after a new article structure is created.

*   **Arguments:**
    *   `int $id`: The ID of the new structure.
    *   `string $structure_data`: JSON encoded structure data.

#### `aips_structure_updated`
Fires after an article structure is updated.

*   **Arguments:**
    *   `int $structure_id`: The ID of the updated structure.
    *   `string $structure_data`: JSON encoded structure data.

#### `aips_structure_deleted`
Fires after an article structure is deleted.

*   **Arguments:**
    *   `int $structure_id`: The ID of the deleted structure.

### Prompt Builder

#### `aips_before_build_content_prompt`
Fires immediately before the content prompt is constructed.

*   **Arguments:**
    *   `object $template`: The template object.
    *   `string $topic`: The topic being processed.

---

## Filter Hooks

### Prompt Builder

#### `aips_content_prompt`
Filters the final content prompt before it is sent to the AI service.

*   **Arguments:**
    *   `string $content_prompt`: The constructed prompt string.
    *   `object $template`: The template object.
    *   `string $topic`: The topic being processed.

---

## Deprecated/Removed Hooks

The following hooks have been removed or deprecated in recent versions:

*   `aips_post_generation_completed` (Removed in v1.7.0) - Use `aips_post_generated` instead.
