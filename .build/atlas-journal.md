# Atlas Architectural Decision Records (ADR)

**DO NOT DELETE THIS FILE.**
This document serves as the persistent memory for architectural decisions, structural patterns, and refactoring strategies.

## Usage Rules
1. **APPEND ONLY:** When "Atlas" (the Architect Agent) makes a structural change, a new entry MUST be appended to the bottom of this file. Never overwrite existing history.
2. **Context is King:** Do not just say *what* changed. Explain *why* (the context) and *what trade-offs* were accepted (the consequences).
3. **Reference the Past:** Before making a new decision, read the history below to ensure consistency with previous architectural patterns.

---

## Current System Architecture (Snapshot)
*Update this section only when major architectural shifts occur.*
- **Pattern:** [e.g., Modular Monolith, Microservices, Atomic Design]
- **State Management:** [e.g., Redux, Context API, XState]
- **Key Constraints:** [e.g., "No direct DB access from UI components", "Mobile-first CSS"]

---

## Decision Log

### 2024-01-01 - [Example] Extract Authentication Logic
**Context:** Authentication logic was scattered across `NavBar.js`, `Login.js`, and `apiUtils.js`, creating circular dependencies and making it hard to update token logic.
**Decision:** Applied "Separation of Concerns". Created a dedicated `AuthService` class in `src/services/auth/`. Implemented the "Singleton Pattern" for the token manager.
**Consequence:**
* Centralized logic makes security updates easier.
* Decoupled UI from API logic.
* Trade-off: `AuthService` must now be injected or imported into all protected routes, increasing boilerplate slightly.

### 2024-03-15 - [Example] Split Global Utils
**Context:** `utils.js` reached 4,000 lines. Loading it on the marketing landing page cost 150kb of unused JS execution.
**Decision:** Applied "Code Splitting". Sliced `utils.js` into domain-specific files: `dateUtils.js`, `formattingUtils.js`, and `validationUtils.js`.
**Consequence:**
* Reduced bundle size on landing pages by 40%.
* Easier to test individual domains.
* Trade-off: Required updating import paths in 50+ files (automated via codemod).

---

## 2025-12-21 - Extract Template Variable Processor

**Context:** The `AIPS_Generator` class had grown to 568 lines, violating the Single Responsibility Principle. It was responsible for AI content generation, title generation, excerpt generation, image handling, AND template variable processing. The `process_template_variables()` method (19 lines) was tightly coupled within the generator, making it difficult to test independently and reuse in other contexts.

**Decision:** Applied "Separation of Concerns" and "Single Responsibility Principle". Created a dedicated `AIPS_Template_Processor` class in `includes/class-aips-template-processor.php`. This new class:
* Handles all template variable replacements ({{date}}, {{topic}}, {{site_name}}, etc.)
* Provides a clean public API: `process()`, `get_variables()`, `get_variable_names()`, `validate_template()`
* Includes comprehensive DocBlocks following WordPress standards
* Maintains backward compatibility through the existing `aips_template_variables` filter
* Adds new validation capabilities to detect malformed templates

**Consequence:**
* **Pros:**
  - Reduced `AIPS_Generator` from 568 to ~549 lines
  - Template processing is now testable in isolation
  - New validation capabilities prevent runtime errors from malformed templates
  - Variable processing can be reused in other contexts (e.g., email templates, notifications)
  - Clear separation makes the codebase easier to understand and maintain
* **Cons:**
  - Added one new file to the includes directory
  - Slightly increased memory footprint (one additional object instance)
  - Developers must now understand two classes instead of one for template processing
* **Trade-offs:**
  - Chose composition over inheritance (Generator has-a Processor rather than is-a Processor)
  - Maintained backward compatibility: existing filter hooks work unchanged
  - Template validation is opt-in (doesn't break existing templates)

**Tests:** Created comprehensive test suite in `tests/test-template-processor.php` with 17 test cases covering:
* Basic variable replacement
* Topic and title alias handling
* Empty topic scenarios
* All date/time variables
* Random number generation
* Variable retrieval methods
* Template validation (valid templates, unclosed braces, invalid variables)
* Custom variable filters
* Multiple occurrences of same variable
* Templates without variables

**Backward Compatibility:**
* All existing code calling template variable processing continues to work
* The `aips_template_variables` filter is preserved and functional
* No changes to database schema or stored data
* No changes to public plugin APIs or hooks
* Generator class maintains same public interface

---

## 2025-12-21 - Extract Interval Calculator Service

**Context:** The `AIPS_Scheduler` class was responsible for both schedule orchestration AND interval calculation logic. The `get_intervals()` method (77 lines) and `calculate_next_run()` method (60 lines) contained complex date/time math that should be independently testable. The scheduler's responsibility should be coordinating schedules, not performing interval calculations.

**Decision:** Applied "Separation of Concerns" and "Single Responsibility Principle". Created a dedicated `AIPS_Interval_Calculator` class in `includes/class-aips-interval-calculator.php`. This new class:
* Handles all interval definitions and calculations
* Provides methods: `get_intervals()`, `calculate_next_run()`, `get_interval_duration()`, `get_interval_display()`, `is_valid_frequency()`, `merge_with_wp_schedules()`
* Encapsulates complex day-specific interval logic (e.g., "every_monday")
* Includes comprehensive DocBlocks following WordPress standards
* Makes scheduling math testable in isolation
* Reduces `AIPS_Scheduler` from 298 to ~165 lines

**Consequence:**
* **Pros:**
  - Scheduler class is now focused solely on orchestrating scheduled tasks
  - Interval calculation logic can be tested independently of WordPress hooks
  - New validation method `is_valid_frequency()` enables input validation before processing
  - Helper methods `get_interval_duration()` and `get_interval_display()` make UI development easier
  - Date/time edge cases (preserving time for day-specific intervals) are isolated and testable
  - Clearer separation makes the codebase easier to understand and debug
* **Cons:**
  - Added one new file to the includes directory
  - Slightly increased memory footprint (one additional object instance)
  - Developers must now understand two classes instead of one for scheduling
* **Trade-offs:**
  - Chose composition over inheritance (Scheduler has-a Calculator)
  - Maintained backward compatibility: Scheduler's public API unchanged
  - Extracted private calculation logic into new class; public interface preserved

**Tests:** Created comprehensive test suite in `tests/test-interval-calculator.php` with 20+ test cases covering:
* Interval structure validation
* Day-specific interval generation
* All frequency calculations (hourly, daily, weekly, monthly, bi-weekly, every N hours)
* Day-specific frequency calculations (every_monday, etc.)
* Time preservation for day-specific intervals
* Current time handling when no start time provided
* Past time handling (defaults to current time)
* Interval duration retrieval
* Display name retrieval
* Frequency validation
* WordPress schedule merging
* Invalid frequency handling (defaults to daily)

**Backward Compatibility:**
* `AIPS_Scheduler::get_intervals()` continues to work identically
* `AIPS_Scheduler::calculate_next_run()` maintains same signature and behavior
* `AIPS_Scheduler::add_cron_intervals()` hook callback unchanged
* All existing schedules and frequencies work without modification
* No database schema changes
* No changes to cron job configurations
* Existing code calling scheduler methods requires no updates