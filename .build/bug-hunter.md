## 2024-05-18 - [Fix PHPUnit Warnings and SEO Metadata Logic]
**Learning:** PHPUnit output parsing needs robust expectation matchers rather than trying to catch standard stdout directly. Missing variables in test data arrays and loosely-typed $wpdb returns cause cascading errors.
**Action:** Replaced loose assertions with expectOutputRegex, safely handled undefined variable in template views, mocked esc_js(), added safe property checks for DB mock rows, and enabled safe fallback to Post Content for SEO meta descriptions when excerpt and custom meta description are missing.
