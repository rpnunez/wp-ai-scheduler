# Observability Standardization Skill

Use this skill when implementing or reviewing multi-step generation, scheduling, cron, AJAX, or admin workflows that require consistent tracing, lifecycle logging, and performance visibility.

## Objectives

- Standardize observability across major operations.
- Ensure failures are diagnosable end-to-end.
- Make performance tradeoffs explicit for sensitive paths.

## Required observability rules

1. **Lifecycle logging is required** for each major operation and must include:
   - `start`
   - `retry` (when applicable)
   - `fail`
   - `success`

2. **Use `AIPS_Logger` and `AIPS_Correlation_Id`** for multi-step flows:
   - Create or retrieve a correlation id at flow entry.
   - Propagate the same id through all nested/subsequent steps.
   - Include correlation id in all log contexts.

3. **Record generation events via `AIPS_Generation_Logger` + history container**:
   - Use `AIPS_History_Container` for structured history continuity.
   - Emit meaningful generation events at component/step boundaries.
   - Ensure generated-content workflows capture enough context for later diagnostics.

4. **For performance-sensitive features, include telemetry considerations**:
   - Reference slow query threshold: `AIPS_TELEMETRY_SLOW_QUERY_MS` (100 ms).
   - Reference slow request threshold: `AIPS_TELEMETRY_SLOW_REQUEST_MS` (1500 ms).
   - Respect telemetry opt-in (`aips_enable_telemetry`) and avoid assuming telemetry is always enabled.

## Implementation checklist (must pass)

- [ ] Correlation ID is created/retrieved once and propagated through the full flow.
- [ ] Event names are meaningful, consistent, and scoped to operation/step.
- [ ] Failure logs include actionable metadata (error type/code, step, retries, affected entity ids when safe).
- [ ] No sensitive data in logs/events (no secrets, raw auth tokens, unnecessary PII, or full prompt payloads unless explicitly safe).
- [ ] Lifecycle coverage exists for start/retry/fail/success at the operation level.
- [ ] Generation workflows write structured entries through `AIPS_Generation_Logger` and history container.
- [ ] Telemetry behavior is opt-in aware and thresholds are considered for performance-sensitive paths.

## Suggested event naming pattern

- `operation.start`
- `operation.step_name.retry`
- `operation.step_name.fail`
- `operation.success`

Keep names stable for filtering and incident review.

## Review output expectations

When using this skill in PR prep/review, produce:

- A lifecycle coverage matrix (operation → start/retry/fail/success).
- Correlation id propagation notes.
- Telemetry impact summary (threshold relevance + opt-in handling).
- A brief risk section listing any observability gaps and follow-up tasks.
