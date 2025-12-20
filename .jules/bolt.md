## 2024-05-23 - [Optimized Log Reading]
**Learning:** `SplFileObject::seek(PHP_INT_MAX)` iterates through the entire file to count lines, causing O(N) performance issues on large files.
**Action:** Use `fseek` to read the last N bytes (tail reading) for large files, which is O(1) and significantly faster for retrieving recent logs.
