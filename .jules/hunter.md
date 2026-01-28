## 2024-05-25 - Schedule Initialization Logic Flaw
**Learning:** Using `calculate_next_run(interval, start_time)` for *initial* schedule creation is incorrect because it calculates `start_time + interval`, effectively skipping the first run.
**Action:** When creating schedules, always treat `start_time` as the explicit *first* execution time (`next_run`), rather than a base for calculation. Only use `calculate_next_run` for subsequent recurring executions.

## 2024-05-25 - Interval Catch-up Loop Limit
**Learning:** The schedule catch-up logic had a loop limit of 100, which is too low for intervals like 'hourly' if the site is inactive for more than a few days (4+ days). This causes phase drift.
**Action:** Increased the loop limit in `AIPS_Interval_Calculator::calculate_next_run` to 50,000 to handle prolonged inactivity while maintaining phase preservation.

## 2024-05-25 - Fail-Fast Validation
**Learning:** Validating input length (e.g., topic length) before attempting expensive operations prevents DB errors and saves resources.
**Action:** Added 255-char limit check for `topic` in `AIPS_Schedule_Controller::ajax_run_now`.
