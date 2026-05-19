#!/usr/bin/env bash
set -euo pipefail
added_lines="$(git diff --unified=0 -- templates/admin/*.php | rg '^\+[^+].*style="' || true)"
if [ -n "$added_lines" ]; then
  echo "New inline style attributes were introduced in templates/admin/*.php:" 
  echo "$added_lines"
  exit 1
fi

echo "No new inline style attributes introduced in templates/admin/*.php"
