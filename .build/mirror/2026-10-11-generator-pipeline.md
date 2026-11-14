# 2026-10-11 - Generator Pipeline Golden Regression Tests

- **Target:** `ai-post-scheduler/includes/class-aips-generator.php::AIPS_Generator::generate_post`
- **Goldens added:**
  - `ai-post-scheduler/tests/golden/generator-pipeline/template_basic.golden.json` (Standard AIPS_Template_Context generation flow)
  - `ai-post-scheduler/tests/golden/generator-pipeline/topic_basic.golden.json` (Standard AIPS_Topic_Context generation flow)
  - `ai-post-scheduler/tests/golden/generator-pipeline/template_sources.golden.json` (AIPS_Template_Context generation flow with trusted sources enabled)
- **Matrix cells covered / remaining:**
  - Covered: 3/3 pipeline scenarios for main generation flow (template context, topic context, sources block).
  - Remaining: Conversational metadata turn, component regeneration, AI failure/retry paths.
- **Shared fake:**
  - Created `ai-post-scheduler/tests/helpers/class-aips-fake-ai-provider.php` (`AIPS_Fake_AI_Provider` implementing `AIPS_AI_Provider_Interface`).
- **Verified by:**
  - `php -l` on all added PHP files.
  - PHPUnit runs (3 passes + 1 `--order-by=random` pass) producing byte-identical assertion matches.
  - Prompt mutation check (altering prompt instructions caused golden test failures as expected).
  - Secret scan (`rg -i "sk-|api[_-]?key" ai-post-scheduler/tests/golden/`) returned 0 findings.
- **Blockers / follow-ups:** None.
