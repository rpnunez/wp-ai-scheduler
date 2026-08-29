
## 2026-05-27 - [Extract Schedule Logic]
**Context:** The `AIPS_Schedule_Processor::execute_schedule_logic` method was a massive >400-line God method handling pre-execution setup, large-batch dispatch, resumable batch progress, and DB cleanup.
**Decision:** Extracted the batch dispatch logic into `dispatch_large_batch` and the batch progress execution logic into `execute_batch_progress`. `execute_schedule_logic` now serves strictly as an orchestrator.
**Consequence:** Increased the number of private methods, but significantly improved readability, testability, and adherence to the Single Responsibility Principle. Backwards compatibility remains intact. Added missing DocBlocks for new functions.
**Tests:** Ran the existing PHPUnit test suite to ensure no regressions were introduced.

## 2026-05-27 - [Interface Segregation for History Repository]
**Context:** The `get_partial_generations` method was implemented in `AIPS_History_Repository` but missing from `AIPS_History_Repository_Interface`, breaking interface contracts and typing for clients needing this method.
**Decision:** Added `get_partial_generations` to `AIPS_History_Repository_Interface`.
**Consequence:** Improved type hinting and strict adherence to the interface. Required that all custom implementations of this interface now support this method, which is an acceptable trade-off for core consistency.
**Tests:** Ran the PHPUnit test suite to ensure existing implementations remain compatible and no syntax errors were introduced.
