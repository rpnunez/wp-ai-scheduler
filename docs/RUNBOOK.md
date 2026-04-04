# Operator Runbook — Queue & Generation Incident Handling

This runbook covers repeatable investigation and recovery procedures for queue-backed scheduling and AI generation incidents in the AI Post Scheduler WordPress plugin.

**Audience**: WordPress administrators and developers responsible for operating the plugin.  
**Companion UI**: Admin → AI Post Scheduler → System Status → *Operator Runbook* panel.

---

## Quick-Reference: System Status Indicators

| Indicator        | Location                         | Meaning                                                     |
|------------------|----------------------------------|-------------------------------------------------------------|
| Queue Backlog    | System Status → Queue Health     | Jobs waiting to start (pending) or partially complete       |
| Stuck Jobs       | System Status → Queue Health     | Jobs in pending/partial status for > 30 minutes             |
| Retry Saturation | System Status → Queue Health     | % of recent jobs that failed — proxy for retry pressure     |
| Circuit Breaker  | System Status → Queue Health     | Whether AI requests are currently blocked                   |

---

## RB-1 — Stuck or Missing Generations

**Symptoms**: Posts are not being published/drafted on schedule. Pending or partial count in Queue Health is growing.

1. Open **System Status → Queue Health**. Note the stuck-job count and age.
2. Open **System Status → Scheduler Health**. If any cron hook shows 0 or duplicate instances, click **Flush WP-Cron Events**.
3. Open **History**, filter by `status = pending` or `status = partial`. Copy one or more correlation IDs.
4. Click into a history record. Examine the log entries to identify where the run stopped (`ai_request`, `error`, `partial_completion`).
5. Confirm AI Engine is reachable:
   - Check AI Engine settings for a valid API key.
   - Confirm the API quota has not been exhausted (check the provider dashboard).
6. If the post is partially complete, use **Partial Generation Recovery** in the History detail view to regenerate missing components.
7. If cron was the problem, monitor the next scheduled run to confirm it executes.

**Expected resolution time**: 5–15 minutes.

---

## RB-2 — High Failure Rate / Retry Saturation

**Symptoms**: Retry Saturation is above 20 %. Many recent generation records show `failed` status.

1. Check **Queue Health → Retry Saturation**. Values above 50 % indicate a systemic upstream issue.
2. Open **Generation Metrics → Recent Outcomes** and look for repeated error messages.
3. Common causes and fixes:
   | Error pattern | Fix |
   |---|---|
   | Rate limit exceeded | Reduce schedule frequency, wait for quota reset |
   | Model unavailable | Switch model in AI Engine settings or wait for provider maintenance window to end |
   | Invalid prompt / content policy | Simplify prompt in the offending template |
   | Timeout / network error | Check server outbound connectivity; consider increasing PHP `max_execution_time` |
4. Review AI Engine logs (AI Engine → Logs) for raw API error responses.
5. If the issue is transient API congestion, pause active schedules temporarily:
   - Go to **Schedule** and deactivate high-frequency schedules.
   - Re-enable once the failure rate returns to normal.

**Expected resolution time**: 10–30 minutes depending on cause.

---

## RB-3 — Circuit Breaker is Open

**Symptoms**: Circuit Breaker shows `OPEN — AI requests are blocked`. No new posts are being generated.

The circuit breaker opens after a configured number of consecutive AI failures (default: 5) to prevent runaway retry storms. It must be manually reset once the root cause is resolved.

1. Identify the root cause first — resetting without fixing the underlying issue will immediately re-open the breaker.
2. Review **Generation Metrics → Recent Outcomes** for the repeated error.
3. Verify the API key in AI Engine settings is valid and not expired.
4. Confirm API quota is available on the provider dashboard.
5. Once the root cause is resolved, click **Reset Circuit Breaker** in System Status → Operator Runbook.
6. Alternatively, navigate to **Settings → AI Integration** and save settings (this also resets the in-memory state).
7. After resetting, watch **Queue Health** for 5 minutes. If Retry Saturation stays below 20 %, the incident is resolved.
8. Re-enable any schedules that were paused during investigation.

**Expected resolution time**: 5–20 minutes after API issue is fixed.

---

## RB-4 — Backlog Not Draining

**Symptoms**: Queue Backlog pending count keeps growing. Posts are delayed by hours.

1. Check **Queue Health → Queue Backlog** to confirm the trend (refresh page after a few minutes).
2. Verify WP-Cron is running:
   ```bash
   # Server-side check (SSH access required):
   wp cron event list --format=table
   ```
   Many managed hosts disable WP-Cron for busy sites. Consider adding a real cron job:
   ```
   */5 * * * * php /path/to/wordpress/wp-cron.php
   ```
3. Check the active schedule count and frequency in **System Status → Scheduler Health**. If you have many high-frequency schedules, the queue may fill faster than it drains.
4. Consider increasing post-generation concurrency if your server and API plan support it, or reducing `post_quantity` per template.
5. Audit and disable schedules that are no longer needed: **Schedule → [select schedules] → Deactivate**.

**Expected resolution time**: 15–60 minutes depending on server configuration.

---

## RB-5 — High Image Generation Failure Rate

**Symptoms**: Image Generation Failure Rate in Generation Metrics is above 30 %.

1. Open **Generation Metrics → Image Generation Failure Rate**.
2. Verify the image generation model is enabled in AI Engine settings:
   - AI Engine → Settings → Image Generation → confirm model is set and API key has image permissions (e.g. `dall-e-3` requires appropriate OpenAI tier).
3. Review image prompts in templates for content that might be rejected by the provider moderation layer (violence, copyright, etc.).
4. Use **Partial Generation Recovery** in History to regenerate featured images for posts where image generation failed:
   - Filter History by posts with `image_success: false` in their logs.
   - Click **Regenerate** → **Featured Image Only**.
5. If the failure rate is structural (model discontinued, plan downgrade), update template settings to disable image generation or switch to Unsplash/Media Library sources.

**Expected resolution time**: 10–30 minutes.

---

## Escalation

If none of the above procedures resolve the incident:

1. Collect the following information:
   - PHP error log excerpt (System Status → Logs)
   - Relevant History log entries with correlation IDs
   - System Status page screenshot
   - WordPress version and PHP version (System Status → Environment)
2. File a support issue at: https://github.com/rpnunez/wp-ai-scheduler/issues
3. Include the version number from **System Status → Plugin** in the issue title.

---

## Preventive Maintenance (monthly)

- [ ] Review Generation Metrics → Success Rate trend.
- [ ] Check Schedule Run Success Rate is ≥ 90 %.
- [ ] Confirm no stuck jobs have been accumulating silently.
- [ ] Prune History records older than your retention policy (Data Management → Export then wipe).
- [ ] Verify API key expiry dates with your AI provider.
