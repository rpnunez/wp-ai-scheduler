import re
import sys

def verify_icons(filepath):
    with open(filepath, 'r') as f:
        content = f.read()

    # Regex to find dashicons spans
    # Matches <span class="dashicons ..."> (and checks if aria-hidden is present)
    # We want to find ones that DO NOT have aria-hidden="true"

    # Simple strategy: find all dashicons spans, then check if they contain aria-hidden="true"

    # Regex to capture the whole span tag
    span_regex = re.compile(r'<span[^>]*class=["\'][^"\']*dashicons[^"\']*["\'][^>]*>', re.IGNORECASE)

    matches = span_regex.finditer(content)

    failed_count = 0
    for match in matches:
        span_tag = match.group(0)
        print(f"Checking: {span_tag}")

        if 'aria-hidden="true"' not in span_tag and "aria-hidden='true'" not in span_tag:
            print(f"❌ FAILED: Missing aria-hidden='true' in {span_tag}")
            failed_count += 1
        else:
            print(f"✅ PASSED: {span_tag}")

    if failed_count > 0:
        print(f"\nFound {failed_count} inaccessible icons.")
        sys.exit(1)
    else:
        print("\nAll icons are accessible!")
        sys.exit(0)

if __name__ == "__main__":
    verify_icons('ai-post-scheduler/assets/js/admin.js')
