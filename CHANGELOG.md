
### Changed
- Optimized the bulk scheduling logic in `AIPS_Planner` by staggering the `next_run` start times via `AIPS_Interval_Calculator` to alleviate immediate API and server load, and added safety boundaries to the `bulk_generate_now` action limiting executions to avoid PHP timeouts.
- Admin History: Replaced full page reload with AJAX table reload when retrying failed generations to improve user flow.
- Refactored multiple admin UI actions to update DOM tables dynamically without a full page reload for a smoother user experience.
