#!/usr/bin/env bash
set -euo pipefail

# Prompt for required paths
read -r -p "Path to repo root (e.g. /c/Projects/MyProject/wp-ai-scheduler): " REPO_DIR
read -r -p "Path where 'wordpress-tests-lib' folder should be created (e.g. /c/Projects/MyProject): " WP_TESTS_PARENT_DIR
read -r -p "Path to full WordPress installation (e.g. /c/Projects/MyProject/wordpress): " WP_CORE_DIR_INPUT

[[ -d "$REPO_DIR" ]] || { echo "Repo directory not found: $REPO_DIR"; exit 1; }
[[ -d "$WP_TESTS_PARENT_DIR" ]] || { echo "WordPress tests parent directory not found: $WP_TESTS_PARENT_DIR"; exit 1; }
[[ -d "$WP_CORE_DIR_INPUT" ]] || { echo "WordPress core directory not found: $WP_CORE_DIR_INPUT"; exit 1; }

cd "$REPO_DIR"

export WP_TESTS_DIR="${WP_TESTS_PARENT_DIR}/wordpress-tests-lib"
export WP_CORE_DIR="$WP_CORE_DIR_INPUT"

DB_NAME='wp_ns_tests_schedule'
DB_USER='root'
DB_PASS=''
DB_HOST='localhost'

# If MySQL tools are not on PATH, uncomment this line:
# export PATH="/c/xampp/mysql/bin:$PATH"

for cmd in svn mysql mysqladmin php composer; do
  command -v "$cmd" >/dev/null 2>&1 || { echo "Missing required command: $cmd"; exit 1; }
done

echo "About to DROP and recreate test database: $DB_NAME on $DB_HOST"
echo "This should be a test-only database."
read -r -p "Type YES to continue: " CONFIRM
[[ "$CONFIRM" == "YES" ]] || { echo "Aborted."; exit 1; }

MYSQL_AUTH=(--user="$DB_USER" --host="$DB_HOST")
if [[ -n "$DB_PASS" ]]; then
  MYSQL_AUTH+=(--password="$DB_PASS")
fi

mysqladmin "${MYSQL_AUTH[@]}" drop "$DB_NAME" --force || true
mysqladmin "${MYSQL_AUTH[@]}" create "$DB_NAME"

rm -rf "$WP_TESTS_DIR"

cd scripts
./install-wp-tests.sh "$DB_NAME" "$DB_USER" "$DB_PASS" "$DB_HOST" latest

test -f "$WP_TESTS_DIR/includes/functions.php" || { echo "Missing $WP_TESTS_DIR/includes/functions.php"; exit 1; }
test -f "$WP_TESTS_DIR/includes/bootstrap.php" || { echo "Missing $WP_TESTS_DIR/includes/bootstrap.php"; exit 1; }

cd ../ai-post-scheduler
composer install
composer test -- --stop-on-error 2>&1 | tee test-results.txt
