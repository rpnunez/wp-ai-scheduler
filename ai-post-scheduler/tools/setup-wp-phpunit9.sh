#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TOOLCHAIN_DIR="$SCRIPT_DIR/phpunit9"

if ! command -v composer >/dev/null 2>&1; then
	echo "Composer is required to install the PHPUnit 9 toolchain." >&2
	exit 1
fi

cd "$TOOLCHAIN_DIR"
composer install
