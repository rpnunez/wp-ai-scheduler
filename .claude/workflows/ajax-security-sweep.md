# Ajax Security Sweep Workflow

Fan out one agent per AJAX controller under `ai-post-scheduler/includes/` to verify nonce, capability, sanitization, and escaping compliance against the rules in `AGENTS.md`, then cross-check and consolidate findings.

## Purpose

Audit every `class-aips-*-controller.php` for the four AJAX security requirements documented in `AGENTS.md`:
1. Nonce verified via `check_ajax_referer()` with `false` as the third argument (no `wp_die()` on failure).
2. `current_user_can()` checked before any state change.
3. All `$_REQUEST`/`$_POST`/`$_GET` inputs sanitized with WordPress helpers.
4. All output escaped with `esc_html()`, `esc_attr()`, `esc_url()`, or `wp_kses_post()`.

## Phases

### Phase 1 — Discovery
Read `ai-post-scheduler/includes/class-aips-ajax-registry.php` to enumerate all registered controllers, then glob `ai-post-scheduler/includes/class-aips-*-controller.php` for the full file list.

### Phase 2 — Per-controller audit (parallel)
For each controller file, spawn a subagent that:
1. Reads the controller file.
2. Checks each of the four security requirements above.
3. Reports findings as a structured list: controller name, requirement, status (pass/fail/warn), and evidence (line number + code snippet).

### Phase 3 — Adversarial cross-check
A separate agent reads all Phase 2 findings and:
- Flags any inconsistencies (e.g., one agent marked a pattern as pass that another flagged as fail for the same pattern).
- Checks that every controller registered in `AIPS_Ajax_Registry::$map` was covered in Phase 2.
- Adds any findings missed in Phase 2 by spot-checking two high-risk controllers independently.

### Phase 4 — Consolidated report
Produce a single Markdown report:
- Summary table: controller × requirement × status.
- Findings section: one entry per fail/warn with file, line, code snippet, and remediation guidance.
- Coverage gaps: any controllers in the registry not covered.
- Recommended next steps ordered by severity.

## Pass/fail criteria (per requirement)

| Requirement | Pass | Fail | Warn |
|---|---|---|---|
| Nonce check | `check_ajax_referer(..., false)` + manual error return | `check_ajax_referer()` without `false`, or missing entirely | `wp_check_admin_referer` used instead |
| Capability check | `current_user_can('manage_options')` before state change | Missing or checked after DB write | Checked only on some paths |
| Sanitization | All inputs through `sanitize_*`, `absint`, `wp_kses_post` | Raw `$_POST`/`$_REQUEST` used directly | Partial — some inputs sanitized |
| Escaping | All output through `esc_*` or `wp_kses_post` | Raw output of user-controlled data | Template escaping inconsistent |

## Useful references
- `AGENTS.md` — canonical security rules
- `ai-post-scheduler/includes/class-aips-ajax-registry.php` — registry
- `.claude/agents/ajax-controller-reviewer.md` — per-controller review checklist
- `.codex/skills/ajax-controller-changes/SKILL.md` — implementation guidance
