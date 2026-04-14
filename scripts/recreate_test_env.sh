#!/usr/bin/env bash
set -euo pipefail

cd /c/Projects/NunezScheduler/wp-ai-scheduler

export WP_TESTS_DIR='C:/Projects/NunezScheduler/wordpress-tests-lib'
export WP_CORE_DIR='C:/Projects/NunezScheduler/wordpress-6.9/wordpress'

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

rm -rf /c/Projects/NunezScheduler/wordpress-tests-lib

cd scripts
./install-wp-tests.sh "$DB_NAME" "$DB_USER" "$DB_PASS" "$DB_HOST" latest

test -f "$WP_TESTS_DIR/includes/functions.php" || { echo "Missing $WP_TESTS_DIR/includes/functions.php"; exit 1; }
test -f "$WP_TESTS_DIR/includes/bootstrap.php" || { echo "Missing $WP_TESTS_DIR/includes/bootstrap.php"; exit 1; }

cd ../ai-post-scheduler
composer install
composer test -- --stop-on-error 2>&1 | tee test-results.txt
