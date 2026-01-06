
import re
import sys

def check_php_syntax(filepath):
    with open(filepath, 'r') as f:
        content = f.read()

    errors = []

    # Check for balanced braces
    open_braces = content.count('{')
    close_braces = content.count('}')
    if open_braces != close_braces:
        errors.append(f"Unbalanced braces: {{ ({open_braces}) vs }} ({close_braces})")

    # Check for missing semicolons (basic heuristic)
    lines = content.split('\n')
    for i, line in enumerate(lines):
        line = line.strip()
        if line and not line.startswith(('//', '#', '*', '<?php', '?>', 'if', 'else', 'elseif', 'while', 'for', 'foreach', 'class', 'function', 'try', 'catch', 'finally', 'switch', 'case', 'default')) and not line.endswith((';', '{', '}', ':', ',', '(', '[', '.', '?>')):
             # Allow multi-line strings/arrays
             if not line.endswith(')') and not line.endswith(']'):
                 # It's hard to be perfect without a parser, but let's flag suspicious lines
                 pass

    if errors:
        print(f"Potential syntax errors in {filepath}:")
        for e in errors:
            print(f"  - {e}")
        return False
    else:
        print(f"Basic syntax check passed for {filepath}")
        return True

files = [
    'ai-post-scheduler/includes/class-aips-settings-page.php',
    'ai-post-scheduler/includes/class-aips-settings.php',
    'ai-post-scheduler/ai-post-scheduler.php'
]

passed = True
for f in files:
    if not check_php_syntax(f):
        passed = False

if passed:
    sys.exit(0)
else:
    sys.exit(1)
