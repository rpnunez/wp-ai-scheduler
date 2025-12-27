"""
Legacy manual dashboard verification script.

This project uses PHPUnit with the WordPress PHPUnit library for automated
testing, as documented in TESTING.md and phpunit.xml. Python/Playwright
tests are not part of the supported testing framework and MUST NOT be used
as automated tests.

If you need to test the AIPS dashboard:
- Add or update a PHPUnit test that extends WP_UnitTestCase under tests/.
- For true browser-based E2E coverage, establish a separate, documented
  E2E framework outside the core PHPUnit test suite.

This file is kept only as documentation and does not perform any tests.
"""

if __name__ == "__main__":
    raise SystemExit(
        "verify_dashboard.py is deprecated. "
        "Use the PHPUnit test suite (tests/test-*.php) instead."
    )
