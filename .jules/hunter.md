## 2024-05-25 - Schedule Initialization Logic Flaw
**Learning:** Using `calculate_next_run(interval, start_time)` for *initial* schedule creation is incorrect because it calculates `start_time + interval`, effectively skipping the first run.
**Action:** When creating schedules, always treat `start_time` as the explicit *first* execution time (`next_run`), rather than a base for calculation. Only use `calculate_next_run` for subsequent recurring executions.

## 2026-01-25 - Infinite Loop Protection Trade-offs
**Learning:** A conservative loop limit (e.g., 100) in catch-up logic can cause data corruption (schedule drift) when the system is inactive for periods longer than the limit allows (e.g., 4 days for hourly schedules).
**Action:** Set safety limits based on realistic maximum downtime scenarios (e.g., 50,000 for ~5 years of hourly runs) rather than arbitrary small numbers.
