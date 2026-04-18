# Notifications V2 Implementation Plan

## Scope
Implement the following non-test improvements for the notifications system:
1. Hook naming/semantic debt cleanup.
2. Settings UX coverage across all notification types.
3. Email delivery observability improvements.
4. Tiny notifications diagnostics panel in System Status.
5. One-time notifications data hygiene command in System Status.

## Objectives
- Move rollup execution semantics from legacy review hook naming to notification-specific naming.
- Ensure channel preferences are configurable and honored for all notification types.
- Track both success and failure outcomes for outbound email delivery.
- Provide operational visibility in System Status for notification health.
- Provide a safe one-time cleanup command for stale/legacy notification state.

## Design

### 1) Hook Naming / Semantic Debt
- Introduce rollup hook: `aips_notification_rollups`.
- Bind rollup handler to new hook in notifications service.
- Keep backward compatibility by aliasing old hook (`aips_send_review_notifications`) to the new hook.
- Schedule new hook for fresh installs via plugin cron definitions.

### 2) Settings UX Coverage
- Render notification channel selectors for all entries in `AIPS_Notifications::get_notification_type_registry()`.
- Sanitize preferences against full registry and allowed modes.
- Normalize fallback behavior to each type's `default_mode`.
- Update section copy from high-priority-only to all notifications.

### 3) Email Delivery Observability
- Make `send_email_notification()` return boolean delivery status.
- Record history events for both success and failure.
- In `dispatch_notification()`, only mark email channel as sent if at least one email actually succeeds.

### 4) System Status Diagnostics Panel
- Extend system status checks with a `notifications` section:
  - recipient configuration status
  - rollup markers (daily/weekly/monthly)
  - new/legacy rollup cron state
  - unread DB notification count
  - last 24h notification type volume (top types)

### 5) One-Time Data Hygiene Command
- Add button in System Status page to run notification hygiene.
- Add AJAX handler to:
  - remove obsolete option `aips_review_notifications_enabled`
  - unschedule legacy hook `aips_send_review_notifications`
  - ensure new hook `aips_notification_rollups` is scheduled
  - normalize `aips_notification_preferences` to current registry
- Return structured summary for admin feedback.

## Related Files
- `ai-post-scheduler/ai-post-scheduler.php`
- `ai-post-scheduler/includes/class-aips-notifications.php`
- `ai-post-scheduler/includes/class-aips-settings.php`
- `ai-post-scheduler/includes/class-aips-system-status.php`
- `ai-post-scheduler/templates/admin/system-status.php`
- `ai-post-scheduler/assets/js/admin-db.js`

## Data/State Touched
- Cron hooks:
  - `aips_notification_rollups` (new canonical rollup hook)
  - `aips_send_review_notifications` (legacy hook alias + optional cleanup)
- Options:
  - `aips_notification_preferences` (normalized)
  - `aips_review_notifications_enabled` (legacy, removed by hygiene command)
  - `aips_notif_daily_digest_last_sent`
  - `aips_notif_weekly_summary_last_sent`
  - `aips_notif_monthly_report_last_sent`
- DB table (read-only for diagnostics):
  - `{$wpdb->prefix}aips_notifications`

## Backward Compatibility Notes
- Legacy hook remains callable and is mapped to new rollup hook.
- Existing installs can migrate via one-time hygiene action from System Status.

## Verification Checklist
- PHP lint for changed PHP files.
- Confirm System Status shows Notifications section.
- Confirm hygiene button triggers AJAX success path.
- Confirm rollup hook binding references `aips_notification_rollups`.
- Confirm settings page renders all notification types.
