## 2024-05-25 - Schedule Initialization Logic Flaw
**Learning:** Using `calculate_next_run(interval, start_time)` for *initial* schedule creation is incorrect because it calculates `start_time + interval`, effectively skipping the first run.
**Action:** When creating schedules, always treat `start_time` as the explicit *first* execution time (`next_run`), rather than a base for calculation. Only use `calculate_next_run` for subsequent recurring executions.

## 2024-05-25 - Time-Dependent Test Failures
**Learning:** Unit tests for time-dependent logic (like scheduling calculators) that use hardcoded dates become "time bombs" when those dates pass. Catch-up logic for past dates can further obscure the interval calculation logic being tested.
**Action:** Use relative future dates (e.g., `strtotime('+1 year')`) in tests to ensure they remain valid regardless of when they are run and to isolate the interval logic from catch-up mechanisms.

## 2026-03-15 - Unintended Form Submissions in WordPress Admin
**Learning:** WordPress admin pages are often wrapped in a `<form>` tag by default. Any `<button>` element inside these pages without an explicit `type` attribute defaults to `type="submit"`, causing unintended page reloads and form submissions when users interact with UI components meant to be handled by JavaScript (e.g., modals, AJAX actions).
**Action:** Always specify `type="button"` for `<button>` elements in WordPress admin interfaces and forms to prevent unintended form submissions, unless the button is explicitly intended to submit a traditional form.
