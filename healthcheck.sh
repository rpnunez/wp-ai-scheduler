#!/bin/bash
set -e

# Healthcheck script for WordPress Docker container

# 1. Check if Apache is responding on localhost (port 80)
# -f: Fail silently (no output at all) on server errors (404 is fine, but 500 is not? Actually -f fails on 400+).
#     For a healthcheck, we usually want to know if the server is up.
#     If WP is installed, / should return 200.
# -s: Silent mode
# -o /dev/null: Discard output
if ! curl -f -s -o /dev/null http://localhost:80; then
    echo "Apache is not responding or returning an error."
    exit 1
  else
    echo "Apache responded."
fi

# 2. Optional: Check if WordPress is installed using WP-CLI
# This ensures the database connection is working and WP is ready.
# We use --allow-root because we are likely running as root in the container.
if ! wp core is-installed --path=/var/www/html --allow-root; then
    echo "WordPress is not installed or database is unreachable."
    exit 1
fi

exit 0
