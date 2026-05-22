---
applyTo: "ai-post-scheduler/includes/class-aips-ajax-registry.php,ai-post-scheduler/includes/class-aips-*-controller.php"
---

Lane: **AJAX controllers** (`ajax-registry`, `security-sensitive`)

- Register AJAX action routing in `AIPS_Ajax_Registry` and keep handler ownership in controller classes.
- Enforce capability checks and operation-specific nonces for each action.
- Sanitize all inputs and return structured responses via existing response helpers.
- Add/update tests for unauthorized requests, nonce failures, and success paths.
- Apply `ajax-registry` and `security-sensitive` labels when this lane is touched.
