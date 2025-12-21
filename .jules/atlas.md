# Atlas's Journal

## 2024-05-22 - Extract Schedule Controller
**Context:** The `AIPS_Scheduler` class violates the Single Responsibility Principle by mixing domain logic (schedule processing, interval calculation), data persistence (SQL queries), and HTTP transport logic (AJAX handlers). Additionally, `AIPS_Planner` accesses the database directly because `AIPS_Scheduler` lacks the necessary flexibility (handling `topic` and explicit `next_run`), creating a Leaky Abstraction.

**Decision:** Extract the AJAX handling logic into a new `AIPS_Schedule_Controller` class. Enhance `AIPS_Scheduler` to act as a proper Service/Repository, accepting `topic` and `next_run` overrides. Refactor `AIPS_Planner` to delegate persistence to `AIPS_Scheduler`.

**Consequence:**
- **Positive:** clearer separation of concerns; `AIPS_Scheduler` becomes a pure domain/service class; `AIPS_Planner` no longer depends on DB schema details.
- **Negative:** Increased file count (1 new file); slight overhead in `AIPS_Planner` due to object instantiation (negligible).
# Atlas Journal - Architectural Decision Records

## 2024-05-23 - [JS Modularization] **Context:** The 'admin.js' file was a 'God Object' (1100+ lines) handling distinct domains like Planner, DB Management, and core UI, making maintenance difficult. **Decision:** Split 'admin.js' into feature-specific files ('admin-planner.js', 'admin-db.js') using 'window.AIPS' as a shared namespace and 'Object.assign' for extension. **Consequence:** Improved separation of concerns and file readability, but introduced a dependency on load order (admin.js must load before modules), managed via 'wp_enqueue_script' dependencies.

## 2024-05-24 - [Extract Image Service] **Context:** The `AIPS_Generator` class was violating the Single Responsibility Principle by mixing content generation orchestration, prompt engineering, logging, and complex image handling logic (downloading, verifying, saving to filesystem, WP Media attachment). The image handling logic alone accounted for ~100 lines and involved external dependencies (HTTP, Filesystem). **Decision:** Extract the image generation and attachment logic into a dedicated `AIPS_Image_Service` class. **Consequence:** Improved separation of concerns; `AIPS_Generator` is now focused on orchestration. `AIPS_Image_Service` encapsulates all WP Media and HTTP intricacies, making it easier to test and reuse. Added one file to the codebase.
