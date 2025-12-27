## 2024-05-25 - Prevent PHP Timeouts in Synchronous Loops
**Learning:** Allowing user configuration (like `post_quantity`) to directly control the number of iterations in a synchronous process (like `ajax_run_now`) causes timeouts when the value is high (e.g., 50).
**Action:** Always implement a hard limit (cap) on synchronous loops and inform the user if their request was truncated to preserve server stability.
