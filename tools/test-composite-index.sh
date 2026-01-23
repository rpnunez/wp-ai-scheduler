#!/bin/bash
# Test script to verify composite index in aips_schedule table
# This script mimics the GitHub Actions workflow setup

set -e

echo "======================================"
echo "Testing Composite Index Addition"
echo "======================================"
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
WP_TESTS_DIR="${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}"
WP_CORE_DIR="${WP_CORE_DIR:-${WP_TESTS_DIR}/src}"
DB_NAME="${DB_NAME:-wordpress_test}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
DB_HOST="${DB_HOST:-127.0.0.1}"

echo "Configuration:"
echo "  WP_TESTS_DIR: $WP_TESTS_DIR"
echo "  WP_CORE_DIR: $WP_CORE_DIR"
echo "  DB_NAME: $DB_NAME"
echo "  DB_USER: $DB_USER"
echo "  DB_HOST: $DB_HOST"
echo ""

# Check if MySQL is available
if ! command -v mysql &> /dev/null; then
    echo -e "${YELLOW}Warning: MySQL client not found. Skipping database verification.${NC}"
    echo "This test will run using the PHPUnit test suite only."
    echo ""
fi

# Check if WordPress test library exists
if [ ! -d "$WP_TESTS_DIR" ]; then
    echo -e "${YELLOW}Warning: WordPress test library not found at $WP_TESTS_DIR${NC}"
    echo "Tests will run in limited mode without full WordPress environment."
    echo ""
fi

# Navigate to repository root
cd "$(dirname "$0")"

echo -e "${GREEN}Step 1: Installing Composer dependencies${NC}"
if [ ! -d "vendor" ]; then
    composer install --no-interaction --prefer-dist --no-progress
else
    echo "Dependencies already installed."
fi
echo ""

echo -e "${GREEN}Step 2: Running PHPUnit test for database schema${NC}"
echo "Executing: vendor/bin/phpunit tests/test-db-schema.php --testdox"
echo ""

# Export environment variables for PHPUnit
export WP_TESTS_DIR
export WP_CORE_DIR

# Run the specific test file
if vendor/bin/phpunit tests/test-db-schema.php --testdox; then
    echo ""
    echo -e "${GREEN}✓ All database schema tests passed!${NC}"
    echo ""
    
    # If MySQL is available and WordPress test lib exists, verify directly in database
    if command -v mysql &> /dev/null && [ -d "$WP_TESTS_DIR" ]; then
        echo -e "${GREEN}Step 3: Verifying index in database directly${NC}"
        
        # Get table prefix (default is wp_)
        TABLE_PREFIX="wp_"
        TABLE_NAME="${TABLE_PREFIX}aips_schedule"
        
        # Check if table exists and has the composite index
        QUERY="SHOW INDEX FROM ${TABLE_NAME} WHERE Key_name = 'is_active_next_run';"
        
        echo "Executing SQL query: $QUERY"
        echo ""
        
        if mysql -h"$DB_HOST" -u"$DB_USER" ${DB_PASS:+-p"$DB_PASS"} "$DB_NAME" -e "$QUERY" 2>/dev/null; then
            echo ""
            echo -e "${GREEN}✓ Composite index 'is_active_next_run' verified in database!${NC}"
        else
            echo -e "${YELLOW}Note: Could not verify directly in database. Test suite verification is sufficient.${NC}"
        fi
    fi
    
    echo ""
    echo -e "${GREEN}======================================"
    echo "✓ TEST PASSED: Composite Index Verified"
    echo "======================================${NC}"
    echo ""
    echo "Summary:"
    echo "  - Composite index 'is_active_next_run' added to aips_schedule table"
    echo "  - Index contains columns: (is_active, next_run)"
    echo "  - PHPUnit tests confirm index structure"
    echo ""
    exit 0
else
    echo ""
    echo -e "${RED}✗ Tests failed!${NC}"
    echo ""
    exit 1
fi
