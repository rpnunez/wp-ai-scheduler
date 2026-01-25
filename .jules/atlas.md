# Atlas's Journal

## 2024-05-22 - Extract Schedule Controller
**Context:** The `AIPS_Scheduler` class violates the Single Responsibility Principle by mixing domain logic (schedule processing, interval calculation), data persistence (SQL queries), and HTTP transport logic (AJAX handlers). Additionally, `AIPS_Planner` accesses the database directly because `AIPS_Scheduler` lacks the necessary flexibility (handling `topic` and explicit `next_run`), creating a Leaky Abstraction.

**Decision:** Extract the AJAX handling logic into a new `AIPS_Schedule_Controller` class. Enhance `AIPS_Scheduler` to act as a proper Service/Repository, accepting `topic` and `next_run` overrides. Refactor `AIPS_Planner` to delegate persistence to `AIPS_Scheduler`.

**Consequence:**
- **Positive:** clearer separation of concerns; `AIPS_Scheduler` becomes a pure domain/service class; `AIPS_Planner` no longer depends on DB schema details.
- **Negative:** Increased file count (1 new file); slight overhead in `AIPS_Planner` due to object instantiation (negligible).
# Atlas Journal - Architectural Decision Records

## 2024-05-23 - [JS Modularization] **Context:** The 'admin.js' file was a 'God Object' (1100+ lines) handling distinct domains like Planner, DB Management, and core UI, making maintenance difficult. **Decision:** Split 'admin.js' into feature-specific files ('admin-planner.js', 'admin-db.js') using 'window.AIPS' as a shared namespace and 'Object.assign' for extension. **Consequence:** Improved separation of concerns and file readability, but introduced a dependency on load order (admin.js must load before modules), managed via 'wp_enqueue_script' dependencies.

## 2024-05-24 - [Extract Post Creator & Use History Repository] **Context:** `AIPS_Generator` was a 'God Class' violating SRP by mixing AI orchestration, direct database queries for history, and WordPress post creation logic. **Decision:** Extracted post creation logic into `AIPS_Post_Creator` service. Refactored `AIPS_Generator` to use `AIPS_Post_Creator` and the existing `AIPS_History_Repository`, removing direct `$wpdb` and `wp_insert_post` dependencies. **Consequence:** Improved testability and separation of concerns. `AIPS_Generator` now focuses solely on orchestration. Increased file count by 1.

## 2024-05-25 - [Extract Resilience Service] **Context:** `AIPS_AI_Service` was violating SRP by handling core AI orchestration alongside resilience concerns (Circuit Breaker, Rate Limiting, Retry Logic). **Decision:** Extracted resilience logic into a dedicated `AIPS_Resilience_Service` class. Refactored `AIPS_AI_Service` to delegate resilience checks to this new service. **Consequence:** Improved separation of concerns; `AIPS_AI_Service` is now cleaner and focused on AI interactions. Increased file count by 1.

## 2024-05-25 - Bulk Insert Architecture
**Context:** Creating hundreds of schedule items via a loop of INSERT statements was inefficient.
**Decision:** Implemented create_bulk to accept an array of schedules and generate a single SQL INSERT statement.
**Consequence:** Reduced database round-trips from O(N) to O(1) for bulk scheduling operations.

## 2024-05-26 - [Extract Admin Assets Management] **Context:** `AIPS_Settings` was a 'God Class' handling menu registration, settings, page rendering, AND asset enqueueing, contributing to global script bloat. **Decision:** Extracted asset management logic into `AIPS_Admin_Assets`. **Consequence:** Improved separation of concerns; `AIPS_Settings` is now more focused on routing/settings. Increased file count by 1.
