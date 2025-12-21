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

---

## 2025-12-21 - Create AI Service Layer

**Context:** The `AIPS_Generator` class directly accessed the Meow AI Engine through global variables and contained all AI interaction logic mixed with content generation orchestration. This created tight coupling to the AI Engine implementation, made testing difficult (can't mock AI responses), and violated the Dependency Inversion Principle. The AI-related code (80+ lines) was embedded within the 568-line Generator class, making it hard to reuse AI functionality in other contexts or swap AI providers.

**Decision:** Applied "Separation of Concerns", "Dependency Inversion Principle", and "Service Layer Pattern". Created a dedicated `AIPS_AI_Service` class in `includes/class-aips-ai-service.php`. This new class:
* Abstracts all AI Engine interactions behind a clean interface
* Provides methods: `is_available()`, `generate_text()`, `generate_image()`, `get_call_log()`, `clear_call_log()`, `get_call_statistics()`
* Handles AI Engine initialization and availability checking
* Manages options (model, temperature, max_tokens) in one place
* Provides built-in logging and debugging capabilities
* Makes AI calls testable through dependency injection
* Includes comprehensive DocBlocks following WordPress standards

**Consequence:**
* **Pros:**
  - Generator class reduced from ~568 to ~470 lines
  - AI interaction logic is now independently testable
  - Call logging built into service layer for better debugging
  - Statistics tracking enables monitoring and optimization
  - AI provider can be swapped without changing Generator
  - New `get_call_log()` enables detailed debugging of AI interactions
  - Error handling is centralized and consistent
  - Future support for multiple AI providers is now feasible
* **Cons:**
  - Added one new file to the includes directory
  - Slightly increased memory footprint (one additional object instance)
  - Additional layer of abstraction may be overkill for simple use cases
* **Trade-offs:**
  - Chose service layer over direct AI Engine access (better abstraction)
  - Maintained backward compatibility: Generator's public API unchanged
  - AI Service owns all AI Engine interaction details
  - Extracted image generation into separate method `upload_image_from_url()` for better separation

**Tests:** Created comprehensive test suite in `tests/test-ai-service.php` with 16+ test cases covering:
* Service instantiation
* Availability checking
* Text generation (with unavailable AI Engine)
* Image generation (with unavailable AI Engine)
* Call log initialization and accumulation
* Call log clearing
* Call statistics structure and tracking
* Prompt capture in logs
* Type capture (text vs image)
* Timestamp capture
* Success/failure status tracking
* Error message capture
* Options passing and capture
* Multiple call accumulation

**Backward Compatibility:**
* `AIPS_Generator::is_available()` continues to work identically
* `AIPS_Generator::generate_content()` maintains same signature and behavior
* All existing code using Generator requires no changes
* AI Engine integration remains unchanged from external perspective
* No database schema changes
* No changes to plugin settings or options
* Generated content format and structure unchanged
* Image generation functionality preserved exactly

---

## 2025-12-21 - Split Generator and Extract Image Service

**Context:** The `AIPS_Generator` class had grown to 520+ lines and was responsible for too many concerns: content generation, title generation, excerpt generation, image generation, image downloading, image uploading, file system operations, and WordPress attachment management. The image handling code alone was ~120 lines (methods `generate_and_upload_featured_image()` and `upload_image_from_url()`). This violated the Single Responsibility Principle and made testing difficult since image operations required filesystem and network access.

**Decision:** Applied "Separation of Concerns" and "Single Responsibility Principle". Created a dedicated `AIPS_Image_Service` class in `includes/class-aips-image-service.php`. This new class:
* Handles all image generation and upload operations
* Provides methods: `generate_and_upload_featured_image()`, `upload_image_from_url()`, `upload_multiple_images()`, `validate_image_url()`
* Encapsulates file system operations for images
* Manages WordPress attachment creation
* Performs security validation (SSRF prevention, content-type checking)
* Includes comprehensive DocBlocks following WordPress standards
* Accepts AI Service via dependency injection for better testability
* Returns WP_Error objects for consistent error handling (instead of false)
* Reduced `AIPS_Generator` from ~520 to ~370 lines (29% reduction)

**Consequence:**
* **Pros:**
  - Generator is now focused on orchestrating content generation, not image handling
  - Image operations can be tested independently without generating content
  - New `validate_image_url()` method enables pre-validation before downloading
  - New `upload_multiple_images()` method enables batch operations
  - Better error handling with WP_Error instead of boolean false
  - Image service can be reused in other contexts (user uploads, galleries, etc.)
  - Cleaner separation of concerns makes code easier to understand
  - File cleanup on attachment failure prevents orphaned files
* **Cons:**
  - Added one new file to the includes directory
  - Slightly increased memory footprint (one additional object instance)
  - Need to understand two classes instead of one for image operations
* **Trade-offs:**
  - Chose composition over inheritance (Generator has-a Image Service)
  - Maintained backward compatibility: image generation behavior unchanged
  - Image Service owns all file system and attachment operations
  - Generator remains the orchestrator but delegates image work

**Tests:** Created test suite in `tests/test-image-service.php` with 9 test cases covering:
* Service instantiation
* URL validation (empty, invalid format, non-existent)
* Multiple image upload
* Invalid URL handling
* AI unavailability scenarios
* Custom AI service injection
* Empty array handling
* Filename sanitization

**Backward Compatibility:**
* Generated posts with featured images work identically
* Image generation process and results unchanged
* No database schema changes
* No changes to template configuration
* No changes to plugin settings
* Existing code using Generator requires no updates
* Error logging format preserved
* Generation log structure maintained