## 2024-05-24 - Schedule Drift Prevention
**Learning:** Calculating schedule intervals based on execution time (`now`) causes drift (e.g., 9:00 -> 9:05 -> 9:10). Calculating based on scheduled time (`prev_next_run`) preserves phase but risks "catch-up storms" after downtime.
**Action:** Use "Catch-Up Preserving Phase" logic: Calculate next run based on `prev_next_run`, but iteratively add intervals until `> now` to skip missed runs while maintaining the original time-of-day.
