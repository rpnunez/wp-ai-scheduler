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
## 2025-12-25 - [Extract Prompt Builder] **Context:** The 'AIPS_Generator' class was violating SRP by mixing AI orchestration logic with prompt string construction and formatting details. **Decision:** Extracted the prompt construction logic into a new 'AIPS_Prompt_Builder' class. **Consequence:** 'AIPS_Generator' is now cleaner and focuses on orchestration; prompt logic is centralized and testable. **Reference:** Related to 'Extract Post Creator' (2024-05-24).
## 2025-12-25 - [Extract Content Engine] **Context:** 'AIPS_Generator' was a God Class handling orchestration, content generation, and prompt building. **Decision:** Extracted content generation orchestration into 'AIPS_Content_Engine', consolidating dependencies like 'AIPS_AI_Service' and 'AIPS_Template_Processor' there. **Consequence:** 'AIPS_Generator' is now a thin client handling only lifecycle and persistence. **Reference:** Related to 'Extract Prompt Builder' (2025-12-25).
