## 2026-06-15 - Planner Optimization
Target Feature: Planner
Improvement: Stagger next_run datetimes by 10 minutes for bulk scheduled topics with 'once' frequency to avoid overwhelming the system, while ensuring recurring frequencies share the exact same next_run to properly utilize the background queuing system.
Files Modified: ai-post-scheduler/includes/class-aips-planner.php, ai-post-scheduler/tests/Test_Bulk_Schedule.php
Outcome: Improves queue efficiency and prevents one-off bulk schedules from unintentionally becoming infinite recurring schedules.
