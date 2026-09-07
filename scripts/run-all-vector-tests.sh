#!/usr/bin/env bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

TEST_FILES=(
  "tests/Test_AIPS_Embeddings_Repository.php"
  "tests/Test_AIPS_Relationships_Repository.php"
  "tests/Test_AIPS_Content_Indexer_Service.php"
  "tests/Test_AIPS_Related_Posts_Service.php"
  "tests/Test_AIPS_Deduplication_Service.php"
  "tests/Test_AIPS_Content_Indexer_Controller.php"
  "tests/Test_AIPS_Internal_Links.php"
  "tests/Test_AIPS_DB_Migrations.php"
)

echo "========================================================"
echo "Running full vector & migration test verification suite"
echo "========================================================"

for test_file in "${TEST_FILES[@]}"; do
  echo "--------------------------------------------------------"
  echo "Running: $test_file"
  echo "--------------------------------------------------------"
  bash "$SCRIPT_DIR/run-docker-test.sh" "$test_file"
done

echo "========================================================"
echo "ALL 8 TEST SUITES COMPLETED SUCCESSFULLY!"
echo "========================================================"
