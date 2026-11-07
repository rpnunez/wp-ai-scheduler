# 2026-11-04 - Author Topic Approval Policy Option Constants

- **Target:** ai-post-scheduler/includes/class-aips-author-topic-approval-policy.php::AIPS_Author_Topic_Approval_Policy
- **Lens:** Thursday (Hardcoded option names, magic strings/numbers -> AIPS_Config defaults / class constants)
- **Context:** `AIPS_Author_Topic_Approval_Policy` contained repeated raw string literals for option names (`'aips_author_topic_auto_approval_mode'`, `'aips_author_topic_auto_approval_min_score'`, `'aips_author_topic_auto_approval_min_relevance'`, `'aips_author_topic_auto_approval_max_similarity'`, `'aips_author_topic_auto_approval_fallback'`) and magic default fallback values (`'manual'`, `'smart_split'`) directly in `resolve()` and normalization methods.
- **Decision:** Extracted class constants `OPTION_MODE`, `OPTION_MIN_SCORE`, `OPTION_MIN_RELEVANCE`, `OPTION_MAX_SIMILARITY`, `OPTION_FALLBACK`, `DEFAULT_MODE`, and `DEFAULT_FALLBACK` onto `AIPS_Author_Topic_Approval_Policy`, and referenced them consistently in `resolve()` and normalization methods.
- **Backward compatibility:** All constant values exactly match existing option names and defaults configured in `AIPS_Config`. Method signatures and return structures remain 100% backward compatible.
- **Consequence:** Eliminates typo-prone string literals across approval policy evaluation, improves navigability and discoverability of option keys for caller code and tests.
- **Tests:** Added `test_option_constants_match_expected_names_and_defaults` in `Test_AIPS_Author_Topic_Approval_Policy.php` verifying constants against `AIPS_Config` defaults. Verified with `php -l`, `composer lint:repository-boundary`, and PHPUnit running `tests/Test_AIPS_Author_Topic_Approval_Policy.php` (15 tests, 63 assertions passing).
