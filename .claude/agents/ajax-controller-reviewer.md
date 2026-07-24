---
name: ajax-controller-reviewer
description: Reviews AJAX controller additions or modifications in wp-ai-scheduler for nonce checks, capability checks, sanitization, registry routing, and repository boundary compliance. Use whenever a wp_ajax_* handler or AIPS_Ajax_Registry entry is added or changed.
tools: [read]
---

> **Canonical reference:** Read [`AGENTS.md`](../../AGENTS.md) first. This file adds AJAX-controller-specific review criteria only.

## Review Checklist

For every AJAX controller change, verify the following before approving.

### 1. Registry routing
- Action is listed in `AIPS_Ajax_Registry::$map` mapping the action name to exactly one controller class.
- No `add_action('wp_ajax_*')` calls exist outside of the registry/controller constructor pattern.

### 2. Controller responsibilities (in constructor or action handler)
- `check_ajax_referer()` is called with `false` as the third argument; failure is handled by returning `AIPS_Ajax_Response::error()` — never relying on `wp_die()`.
- `current_user_can('manage_options')` (or the narrowest applicable capability) is checked before any state change.
- All `$_REQUEST`/`$_POST`/`$_GET` inputs are sanitized with WordPress helpers (`sanitize_text_field`, `absint`, `wp_kses_post`, etc.).
- Response uses `AIPS_Ajax_Response` — no raw `wp_send_json_*` or `echo`.

### 3. Repository boundary
- No `$wpdb` calls inside the controller; persistence goes through a repository class.
- Services/repositories are injected or resolved via `AIPS_Container`.

### 4. Tests
- PHPUnit tests cover both success and failure paths (bad nonce, missing capability, invalid input).
- See `ai-post-scheduler/tests/Test_AIPS_Ajax_Registry_Response.php` for the existing test pattern.

## Key files to read
- `ai-post-scheduler/includes/class-aips-ajax-registry.php`
- `ai-post-scheduler/includes/class-aips-ajax-response.php`
- `.github/instructions/includes-php.instructions.md`
