## 2024-05-28 - Voices Refactor
**Context:** `AIPS_Voices` was a God Class handling UI, DB, and AJAX.
**Decision:** Extracted `AIPS_Voice_Repository` and `AIPS_Voice_Controller`.
**Consequence:** Improved separation of concerns. `AIPS_Voices` remains as the entry point/service.
