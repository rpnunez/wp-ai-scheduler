
## 2026-05-27 - [Extract Schedule Logic]
**Context:** The `AIPS_Schedule_Processor::execute_schedule_logic` method was a massive >400-line God method handling pre-execution setup, large-batch dispatch, resumable batch progress, and DB cleanup.
**Decision:** Extracted the batch dispatch logic into `dispatch_large_batch` and the batch progress execution logic into `execute_batch_progress`. `execute_schedule_logic` now serves strictly as an orchestrator.
**Consequence:** Increased the number of private methods, but significantly improved readability, testability, and adherence to the Single Responsibility Principle. Backwards compatibility remains intact. Added missing DocBlocks for new functions.
**Tests:** Ran the existing PHPUnit test suite to ensure no regressions were introduced.
## 2025-02-12 - Extracted methods from generate_post_from_context God Function
**Context:** `AIPS_Generator::generate_post_from_context` was over 400 lines and handled multiple disparate responsibilities.
**Decision:** Extracted content generation and metadata resolution logic into separate private methods `generate_and_normalize_content` and `generate_and_resolve_metadata` following the Extract Method pattern.
**Consequence:** Reduced function size significantly, improving readability and Separation of Concerns while retaining behavior.
**Tests:** Executed full PHPUnit test suite to ensure no regressions.
## 2026-05-27 - [Extract Single Topic Embedding Processing Logic]
**Context:** The `AIPS_Topic_Expansion_Service::process_approved_embeddings_batch` method was a massive >120-line God method handling batch orchestration, existing embedding validation, API computation, history recording, and statistics updating.
**Decision:** Extracted the single topic processing logic into a new private method `process_single_topic_embedding`. `process_approved_embeddings_batch` now serves strictly as an orchestrator.
**Consequence:** Increased the number of private methods, but significantly improved readability, testability, and adherence to the Single Responsibility Principle. Backwards compatibility remains intact. Added missing DocBlocks for the new function.
**Tests:** Ran the existing PHPUnit test suite to ensure no regressions were introduced.
