## 2026-01-10 - Consolidated structure permission checks
**Context:** Repeated nonce and capability verification logic was duplicated across all structure AJAX handlers, increasing maintenance cost and risk of inconsistent authorization behavior.
**Decision:** Extracted the shared authorization and nonce validation into a single private helper within `AIPS_Structures_Controller` and added DocBlocks to the affected handlers to clarify their responsibilities while preserving existing hooks and messages.
**Consequence:** Introduces a small layer of indirection for request validation but centralizes the logic for easier future updates and consistent responses; all public method signatures remain unchanged to maintain backward compatibility.
**Tests:** Relied on existing `AIPS_Structures_Controller` AJAX tests that cover permission and nonce flows; test suite not rerun in this iteration due to a known pre-existing parse error in `tests/test-generator-hooks.php`.
