# 2026-10-12 - prompt-builder-post-title

- **Target:** `ai-post-scheduler/includes/class-aips-prompt-builder-post-title.php::AIPS_Prompt_Builder_Post_Title`
- **Goldens added:**
  - `ai-post-scheduler/tests/golden/prompt-builder-post-title/template_standard.golden.txt`
  - `ai-post-scheduler/tests/golden/prompt-builder-post-title/voice_override.golden.txt`
  - `ai-post-scheduler/tests/golden/prompt-builder-post-title/followup_conversation.golden.txt`
- **Matrix cells covered / remaining:**
  - *Covered:* `build()` with `AIPS_Template_Context` and template title instructions; `build()` with voice title prompt override; `build_followup()` multi-turn conversation title prompt generation.
  - *Remaining:* `AIPS_Prompt_Builder_Post_Content`, `AIPS_Prompt_Builder_Post_Excerpt`, `AIPS_Prompt_Builder_Post_Featured_Image`.
- **Shared fake:** `ai-post-scheduler/tests/helpers/class-aips-fake-ai-provider.php` (created and shared).
- **Verified by:**
  - `php -l` syntax validation on all new PHP files.
  - Snapshot generation with `.update` marker and verification without marker.
  - `cd /app/ai-post-scheduler && WP_TESTS_DIR=/tmp/wordpress-tests-lib WP_CORE_DIR=/tmp/wordpress AIPS_WP_TEST_SKIP_DB_CREATE=true php vendor/bin/phpunit --filter=Test_AIPS_Prompt_Builder_Post_Title_Golden` passed 3/3 tests (3 assertions).
  - Determinism verified across 3 standard runs and 1 randomized run (`--order-by=random`).
  - Mutation check confirmed test failure on prompt modification.
  - Secret scan on `tests/golden/` returned 0 findings.
- **Blockers / follow-ups:** None.
