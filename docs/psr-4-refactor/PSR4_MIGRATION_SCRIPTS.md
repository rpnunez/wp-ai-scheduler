# PSR-4 Migration Scripts

This document provides automation scripts to assist with the PSR-4 migration.

## Script 1: Directory Structure Creator

Create the complete `src/` directory structure:

```bash
#!/bin/bash
# create-structure.sh
# Creates the PSR-4 directory structure

set -e

echo "Creating PSR-4 directory structure..."

# Navigate to plugin directory
cd ai-post-scheduler

# Create main src directory
mkdir -p src

# Create subdirectories
mkdir -p src/Controllers/Admin
mkdir -p src/Services/AI
mkdir -p src/Services/Content
mkdir -p src/Services/Generation
mkdir -p src/Services/Research
mkdir -p src/Repositories
mkdir -p src/Generators
mkdir -p src/Models
mkdir -p src/Interfaces
mkdir -p src/Admin
mkdir -p src/Utilities
mkdir -p src/DataManagement/Export
mkdir -p src/DataManagement/Import
mkdir -p src/Notifications

echo "Directory structure created successfully!"
echo ""
echo "Created directories:"
find src -type d | sort

echo ""
echo "Next step: Update composer.json files"
```

**Usage:**
```bash
chmod +x create-structure.sh
./create-structure.sh
```

---

## Script 2: Class Name Converter

Helper script to convert old class names to new PascalCase names:

```bash
#!/bin/bash
# convert-class-name.sh
# Converts AIPS_Class_Name to ClassName format

convert_name() {
    local old_name="$1"
    
    # Remove AIPS_ prefix
    local name_without_prefix="${old_name#AIPS_}"
    
    # Convert to PascalCase
    local pascal_case=$(echo "$name_without_prefix" | sed -E 's/(^|_)([a-z])/\U\2/g')
    
    echo "$pascal_case"
}

# Test with command line argument
if [ $# -eq 1 ]; then
    convert_name "$1"
else
    echo "Usage: $0 AIPS_Class_Name"
    echo "Example: $0 AIPS_Template_Repository"
    echo "Output: TemplateRepository"
fi
```

**Usage:**
```bash
chmod +x convert-class-name.sh
./convert-class-name.sh AIPS_Template_Repository
# Output: TemplateRepository
```

---

## Script 3: File Migration Helper

Semi-automated file migration (review changes before committing):

```bash
#!/bin/bash
# migrate-class.sh
# Helps migrate a single class file to PSR-4 structure

set -e

if [ $# -ne 3 ]; then
    echo "Usage: $0 <old-file> <new-namespace> <new-directory>"
    echo "Example: $0 class-aips-logger.php AIPS\\\\Services src/Services"
    exit 1
fi

OLD_FILE="$1"
NEW_NAMESPACE="$2"
NEW_DIR="$3"

# Extract old class name from file
OLD_CLASS=$(grep -m1 "^class " "includes/$OLD_FILE" | sed 's/class //' | sed 's/ .*//')

# Convert to new class name
NEW_CLASS=$(echo "$OLD_CLASS" | sed 's/AIPS_//' | sed -E 's/(^|_)([a-z])/\U\2/g')

# New file name
NEW_FILE="${NEW_CLASS}.php"

echo "Migrating $OLD_FILE..."
echo "  Old class: $OLD_CLASS"
echo "  New class: $NEW_CLASS"
echo "  Namespace: $NEW_NAMESPACE"
echo "  New location: $NEW_DIR/$NEW_FILE"
echo ""

# Copy file to new location
cp "includes/$OLD_FILE" "$NEW_DIR/$NEW_FILE"

# Add namespace (after opening PHP tag and before ABSPATH check)
sed -i '/^<?php/a\
namespace '"$NEW_NAMESPACE"';' "$NEW_DIR/$NEW_FILE"

# Update class name
sed -i "s/class $OLD_CLASS/class $NEW_CLASS/" "$NEW_DIR/$NEW_FILE"

echo "✓ File migrated to $NEW_DIR/$NEW_FILE"
echo ""
echo "TODO: Manual steps required:"
echo "  1. Add use statements for dependencies"
echo "  2. Update internal class references"
echo "  3. Test the migrated class"
echo "  4. Add alias to includes/compatibility-loader.php:"
echo "     class_alias('${NEW_NAMESPACE}\\\\${NEW_CLASS}', '${OLD_CLASS}');"
```

**Usage:**
```bash
chmod +x migrate-class.sh
./migrate-class.sh class-aips-logger.php "AIPS\\Services" src/Services
```

---

## Script 4: Dependency Scanner

Scans a file to find all AIPS_ class dependencies:

```bash
#!/bin/bash
# scan-dependencies.sh
# Finds all AIPS_ class references in a file

if [ $# -ne 1 ]; then
    echo "Usage: $0 <file>"
    exit 1
fi

FILE="$1"

echo "Scanning $FILE for AIPS_ class dependencies..."
echo ""

# Find all AIPS_ class references
grep -oE 'AIPS_[A-Za-z_]+' "$FILE" | sort -u | while read class; do
    echo "  - $class"
done

echo ""
echo "Add corresponding 'use' statements for these classes"
```

**Usage:**
```bash
chmod +x scan-dependencies.sh
./scan-dependencies.sh src/Services/TemplateProcessor.php
```

---

## Script 5: Compatibility Alias Generator

Generates class_alias statements for compatibility loader:

```bash
#!/bin/bash
# generate-aliases.sh
# Generates class_alias statements for all migrated classes

echo "<?php"
echo "/**"
echo " * Backward Compatibility Layer"
echo " * Auto-generated aliases"
echo " */"
echo ""

# Repositories
echo "// Repositories"
for file in src/Repositories/*.php; do
    if [ -f "$file" ]; then
        new_class=$(basename "$file" .php)
        old_class=$(echo "$new_class" | sed -E 's/([A-Z])/_\1/g' | sed 's/^_//' | tr '[:lower:]' '[:upper:]')
        echo "class_alias('AIPS\\\\Repositories\\\\${new_class}', 'AIPS_${old_class}');"
    fi
done
echo ""

# Services
echo "// Services"
for file in src/Services/*.php; do
    if [ -f "$file" ]; then
        new_class=$(basename "$file" .php)
        old_class=$(echo "$new_class" | sed -E 's/([A-Z])/_\1/g' | sed 's/^_//' | tr '[:lower:]' '[:upper:]')
        echo "class_alias('AIPS\\\\Services\\\\${new_class}', 'AIPS_${old_class}');"
    fi
done

# Add other directories as needed...
```

**Usage:**
```bash
chmod +x generate-aliases.sh
./generate-aliases.sh > includes/compatibility-loader-auto.php
```

---

## Script 6: Test Runner for Migrated Classes

Test each migrated class individually:

```bash
#!/bin/bash
# test-migrated-class.sh
# Tests if a migrated class works with both old and new names

if [ $# -ne 2 ]; then
    echo "Usage: $0 <old_class_name> <new_namespace\\ClassName>"
    exit 1
fi

OLD_CLASS="$1"
NEW_CLASS="$2"

# Create temporary test file
TEST_FILE="/tmp/test-migration-$$.php"

cat > "$TEST_FILE" << 'EOF'
<?php
require_once 'vendor/autoload.php';
require_once 'includes/compatibility-loader.php';

$old_class = getenv('OLD_CLASS');
$new_class = getenv('NEW_CLASS');

echo "Testing class migration...\n";
echo "Old class: $old_class\n";
echo "New class: $new_class\n\n";

// Test old name
if (class_exists($old_class)) {
    echo "✓ Old class name works: $old_class\n";
} else {
    echo "✗ Old class name NOT found: $old_class\n";
    exit(1);
}

// Test new name
if (class_exists($new_class)) {
    echo "✓ New class name works: $new_class\n";
} else {
    echo "✗ New class name NOT found: $new_class\n";
    exit(1);
}

// Test they're the same class
$obj1 = new $old_class();
$obj2 = new $new_class();

if (get_class($obj1) === get_class($obj2)) {
    echo "✓ Both names reference the same class\n";
} else {
    echo "✗ Names reference different classes!\n";
    exit(1);
}

echo "\n✓ Migration test passed!\n";
EOF

OLD_CLASS="$OLD_CLASS" NEW_CLASS="$NEW_CLASS" php "$TEST_FILE"
rm "$TEST_FILE"
```

**Usage:**
```bash
chmod +x test-migrated-class.sh
./test-migrated-class.sh "AIPS_Logger" "AIPS\\Services\\Logger"
```

---

## Script 7: Batch Migration Validator

Validates all migrations at once:

```bash
#!/bin/bash
# validate-all-migrations.sh
# Validates that all expected classes are accessible

set -e

echo "Validating PSR-4 migrations..."
echo ""

FAILED=0
PASSED=0

# Read class mappings from file
while IFS='|' read -r old_class new_class; do
    # Skip header and empty lines
    if [[ "$old_class" =~ ^(Old|---|\|).*$ ]] || [ -z "$old_class" ]; then
        continue
    fi
    
    # Trim whitespace
    old_class=$(echo "$old_class" | xargs)
    new_class=$(echo "$new_class" | xargs)
    
    if php -r "require 'vendor/autoload.php'; require 'includes/compatibility-loader.php'; class_exists('$old_class') or exit(1);" 2>/dev/null; then
        echo "✓ $old_class"
        ((PASSED++))
    else
        echo "✗ $old_class (NOT FOUND)"
        ((FAILED++))
    fi
done < docs/psr-4-refactor/PSR4_CLASS_MAPPING.md

echo ""
echo "Results: $PASSED passed, $FAILED failed"

if [ $FAILED -gt 0 ]; then
    exit 1
fi
```

**Usage:**
```bash
chmod +x validate-all-migrations.sh
./validate-all-migrations.sh
```

---

## Script 8: Update composer.json

Automate composer.json updates:

```bash
#!/bin/bash
# update-composer.sh
# Updates composer.json with PSR-4 autoloading

set -e

echo "Updating composer.json files..."

# Update root composer.json
ROOT_COMPOSER="composer.json"
PLUGIN_COMPOSER="ai-post-scheduler/composer.json"

# Backup files
cp "$ROOT_COMPOSER" "${ROOT_COMPOSER}.backup"
cp "$PLUGIN_COMPOSER" "${PLUGIN_COMPOSER}.backup"

echo "Backups created"

# Update root composer.json
cat > /tmp/composer-patch.json << 'EOF'
{
  "autoload": {
    "psr-4": {
      "AIPS\\": "ai-post-scheduler/src/"
    },
    "classmap": [
      "ai-post-scheduler/includes/"
    ]
  }
}
EOF

# Merge with existing composer.json (requires jq)
if command -v jq &> /dev/null; then
    jq -s '.[0] * .[1]' "$ROOT_COMPOSER" /tmp/composer-patch.json > /tmp/composer-merged.json
    mv /tmp/composer-merged.json "$ROOT_COMPOSER"
    echo "✓ Updated $ROOT_COMPOSER"
else
    echo "⚠ jq not found. Please manually update $ROOT_COMPOSER"
    cat /tmp/composer-patch.json
fi

# Update plugin composer.json
cat > /tmp/plugin-composer-patch.json << 'EOF'
{
  "autoload": {
    "psr-4": {
      "AIPS\\": "src/"
    },
    "classmap": [
      "includes/"
    ]
  }
}
EOF

if command -v jq &> /dev/null; then
    jq -s '.[0] * .[1]' "$PLUGIN_COMPOSER" /tmp/plugin-composer-patch.json > /tmp/plugin-composer-merged.json
    mv /tmp/plugin-composer-merged.json "$PLUGIN_COMPOSER"
    echo "✓ Updated $PLUGIN_COMPOSER"
else
    echo "⚠ jq not found. Please manually update $PLUGIN_COMPOSER"
    cat /tmp/plugin-composer-patch.json
fi

echo ""
echo "Next step: Run 'composer dump-autoload'"
```

**Usage:**
```bash
chmod +x update-composer.sh
./update-composer.sh
composer dump-autoload
```

---

## Script 9: Git Workflow Helper

Helps manage git commits during migration:

```bash
#!/bin/bash
# git-migration-helper.sh
# Helps manage git commits for each phase

PHASE="$1"

if [ -z "$PHASE" ]; then
    echo "Usage: $0 <phase-number>"
    echo "Example: $0 1"
    exit 1
fi

BRANCH="psr4-migration-phase-$PHASE"

# Create and switch to phase branch
git checkout -b "$BRANCH"

echo "Created branch: $BRANCH"
echo ""
echo "Complete the migration for Phase $PHASE, then run:"
echo "  git add ."
echo "  git commit -m 'PSR-4 Migration: Phase $PHASE'"
echo "  git tag v2.0.0-alpha-phase$PHASE"
echo "  git push origin $BRANCH"
echo "  git push --tags"
```

**Usage:**
```bash
chmod +x git-migration-helper.sh
./git-migration-helper.sh 1
```

---

## Script 10: Post-Migration Checklist

Automated verification after migration:

```bash
#!/bin/bash
# post-migration-check.sh
# Runs automated checks after migration

set -e

echo "Running post-migration checks..."
echo ""

# Check 1: Composer autoload
echo "1. Checking Composer autoload..."
if composer dump-autoload -o &> /dev/null; then
    echo "   ✓ Composer autoload works"
else
    echo "   ✗ Composer autoload FAILED"
    exit 1
fi

# Check 2: Directory structure
echo "2. Checking directory structure..."
REQUIRED_DIRS=(
    "src/Controllers"
    "src/Services"
    "src/Repositories"
    "src/Generators"
    "src/Models"
    "src/Interfaces"
    "src/Admin"
    "src/Utilities"
)

for dir in "${REQUIRED_DIRS[@]}"; do
    if [ -d "ai-post-scheduler/$dir" ]; then
        echo "   ✓ $dir exists"
    else
        echo "   ✗ $dir NOT FOUND"
        exit 1
    fi
done

# Check 3: Compatibility loader exists
echo "3. Checking compatibility loader..."
if [ -f "ai-post-scheduler/includes/compatibility-loader.php" ]; then
    echo "   ✓ Compatibility loader exists"
else
    echo "   ✗ Compatibility loader NOT FOUND"
    exit 1
fi

# Check 4: Run tests
echo "4. Running tests..."
if composer test &> /dev/null; then
    echo "   ✓ Tests pass"
else
    echo "   ⚠ Tests have failures (review manually)"
fi

# Check 5: Check for syntax errors
echo "5. Checking for PHP syntax errors..."
SYNTAX_ERRORS=0
find ai-post-scheduler/src -name "*.php" | while read file; do
    if ! php -l "$file" &> /dev/null; then
        echo "   ✗ Syntax error in: $file"
        ((SYNTAX_ERRORS++))
    fi
done

if [ $SYNTAX_ERRORS -eq 0 ]; then
    echo "   ✓ No syntax errors found"
fi

echo ""
echo "✓ Post-migration checks complete!"
```

**Usage:**
```bash
chmod +x post-migration-check.sh
./post-migration-check.sh
```

---

## Complete Migration Workflow

Here's the recommended order for using these scripts:

```bash
# Phase 0: Preparation
./create-structure.sh
./update-composer.sh
composer dump-autoload

# Create compatibility loader manually
touch ai-post-scheduler/includes/compatibility-loader.php

# Start Git workflow
./git-migration-helper.sh 0
git add .
git commit -m "PSR-4 Migration: Phase 0 - Preparation"

# For each class migration (Phases 1-7):
# 1. Scan dependencies
./scan-dependencies.sh includes/class-aips-logger.php

# 2. Migrate class
./migrate-class.sh class-aips-logger.php "AIPS\\Services" src/Services

# 3. Manually add use statements and update internal references

# 4. Test migrated class
./test-migrated-class.sh "AIPS_Logger" "AIPS\\Services\\Logger"

# 5. Add to compatibility loader
# (or generate all at once with generate-aliases.sh)

# After each phase:
./post-migration-check.sh
git add .
git commit -m "PSR-4 Migration: Phase X"
git tag v2.0.0-alpha-phaseX

# Final validation
./validate-all-migrations.sh
composer test
```

---

## Troubleshooting

### Script Permission Issues
```bash
chmod +x *.sh
```

### jq Not Installed
```bash
# Ubuntu/Debian
sudo apt-get install jq

# macOS
brew install jq
```

### Syntax Errors
```bash
# Check specific file
php -l src/Services/Logger.php

# Check all files
find src -name "*.php" -exec php -l {} \;
```

---

## Notes

- Always review changes before committing
- Test after each class migration
- Keep backups of original files
- Run full test suite after each phase
- These scripts are helpers, not fully automated - manual review required

---

**Last Updated:** 2026-02-10
**Status:** Helper Scripts
