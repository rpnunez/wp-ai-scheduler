# Atlas's Journal

## 2024-05-22 - Extract Schedule Controller
**Context:** The `AIPS_Scheduler` class violates the Single Responsibility Principle by mixing domain logic (schedule processing, interval calculation), data persistence (SQL queries), and HTTP transport logic (AJAX handlers). Additionally, `AIPS_Planner` accesses the database directly because `AIPS_Scheduler` lacks the necessary flexibility (handling `topic` and explicit `next_run`), creating a Leaky Abstraction.

**Decision:** Extract the AJAX handling logic into a new `AIPS_Schedule_Controller` class. Enhance `AIPS_Scheduler` to act as a proper Service/Repository, accepting `topic` and `next_run` overrides. Refactor `AIPS_Planner` to delegate persistence to `AIPS_Scheduler`.

**Consequence:**
- **Positive:** clearer separation of concerns; `AIPS_Scheduler` becomes a pure domain/service class; `AIPS_Planner` no longer depends on DB schema details.
- **Negative:** Increased file count (1 new file); slight overhead in `AIPS_Planner` due to object instantiation (negligible).
