
## 2024-03-27 - Fix Unsafe File Access Patterns in Logger
**Learning:** File operations like `glob()`, `filesize()`, and `ftell()` can return `false` on failure, which causes silent TypeErrors or incorrect logic (like proceeding with 0 filesize). Unchecked `error_log` inside a logger can spam PHP warnings if the log file is inaccessible, while test suites fail if dependencies (like log directories) are assumed to exist.
**Action:** Always wrap file system reads/writes in explicit `false` guards. In loggers, verify writability or catch `error_log` failures and disable the logger for the request cycle to prevent recursive warning spam. In tests, explicitly construct mock directories rather than assuming the host framework will do it.
