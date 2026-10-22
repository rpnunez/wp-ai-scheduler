#!/bin/bash
set -e

# Lightweight healthcheck for the WordPress container.
# Requests wp-login.php (served by PHP, no redirect, 200 once WP is installed).
# -f: fail on HTTP >= 400, -sS: quiet but show errors, -o /dev/null: discard body.
if ! curl -fsS -o /dev/null --max-time 8 http://localhost:80/wp-login.php; then
    echo "Apache/WordPress is not responding or returning an error."
    exit 1
fi

echo "WordPress responded."
exit 0
