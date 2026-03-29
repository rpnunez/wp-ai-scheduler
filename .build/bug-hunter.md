## 2025-02-18 - JSON Edit URL encoding fix
**Learning:** `get_edit_post_link` requires the `'raw'` parameter when output in a JSON context to prevent double-encoding ampersands in URLs.
**Action:** Fixed missing `'raw'` parameters across `AIPS_Generated_Posts_Controller`, `AIPS_Post_Review`, `AIPS_Notifications`, and `AIPS_Onboarding_Wizard`.

## 2025-02-18 - wp_unslash recursion bug fix
**Learning:** `wp_unslash` processes arrays recursively. Unslashing specific sub-elements of an already unslashed array strips legitimate backslashes and corrupts content data.
**Action:** Applied `wp_unslash` once at the top level of `$_POST['components']` in `AIPS_AI_Edit_Controller` and removed it from individual `sanitize_text_field` / `wp_kses_post` calls inside the handler.
