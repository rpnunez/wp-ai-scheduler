
## 2026-05-27 - [Extract Schedule Logic]
**Context:** The `AIPS_Schedule_Processor::execute_schedule_logic` method was a massive >400-line God method handling pre-execution setup, large-batch dispatch, resumable batch progress, and DB cleanup.
**Decision:** Extracted the batch dispatch logic into `dispatch_large_batch` and the batch progress execution logic into `execute_batch_progress`. `execute_schedule_logic` now serves strictly as an orchestrator.
**Consequence:** Increased the number of private methods, but significantly improved readability, testability, and adherence to the Single Responsibility Principle. Backwards compatibility remains intact. Added missing DocBlocks for new functions.
**Tests:** Ran the existing PHPUnit test suite to ensure no regressions were introduced.
