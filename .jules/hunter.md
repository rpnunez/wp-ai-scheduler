## 2024-05-24 - SSRF Prevention with `wp_safe_remote_get`
**Learning:** `wp_remote_get` allows requests to local/private IPs, making it a vector for SSRF attacks when handling user or AI-generated URLs.
**Action:** Always use `wp_safe_remote_get` for fetching external resources, which includes built-in DNS rebinding protection and private IP blocking.

## 2024-05-25 - Schedule Query Collision Fix
**Learning:**  in  caused template properties to overwrite schedule properties (like ) when column names collided.
**Action:** Changed query to  to ensure schedule properties take precedence, preserving the integrity of the schedule object.
## 2024-05-25 - Schedule Query Collision Fix
**Learning:** SQL JOINs can overwrite columns if using wildcard selects.
**Action:** Changed query order to ensure schedule properties take precedence.
