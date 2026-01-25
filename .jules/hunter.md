## 2024-05-25 - Schedule Initialization Logic Flaw
**Learning:** Using `calculate_next_run(interval, start_time)` for *initial* schedule creation is incorrect because it calculates `start_time + interval`, effectively skipping the first run.
**Action:** When creating schedules, always treat `start_time` as the explicit *first* execution time (`next_run`), rather than a base for calculation. Only use `calculate_next_run` for subsequent recurring executions.

## 2024-05-27 - Modal Close Button Type
**Learning:** Modal close buttons implemented as `<button>` inside a `<form>` context default to `type="submit"`, causing accidental form submissions when users intend to close the modal.
**Action:** Always explicitly define `type="button"` for any button that performs a UI action (like closing a modal) without submitting data.
