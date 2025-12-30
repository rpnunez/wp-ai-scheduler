## 2024-05-25 - Schedule Drift Prevention
**Learning:** Simply resetting missed schedules to `current_time` causes phase drift (e.g. :15 -> :42) and eventually random execution times.
**Action:** Implement "catch-up" loops that iteratively add intervals to the original base time until it is in the future. Always add a safety limit (e.g., 100 iterations) to these loops to prevent timeouts.
