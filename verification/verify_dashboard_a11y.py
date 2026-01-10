import re
import sys

def verify_dashboard_accessibility():
    filepath = 'ai-post-scheduler/templates/admin/dashboard.php'
    with open(filepath, 'r') as f:
        content = f.read()

    # Find all elements with class containing 'dashicons'
    # Pattern looks for class="...dashicons..." and captures the whole tag
    # This is a simple regex, not a full HTML parser, but sufficient for this template

    # We are looking for dashicons that are NOT aria-hidden="true"
    # The current code has: <div class="aips-stat-icon dashicons dashicons-edit"></div>
    # or <span class="dashicons dashicons-plus-alt"></span>

    dashicon_pattern = re.compile(r'<[^>]*class=["\'][^"\']*dashicons[^"\']*["\'][^>]*>')

    matches = dashicon_pattern.findall(content)

    missing_aria = []
    for match in matches:
        if 'aria-hidden="true"' not in match:
            missing_aria.append(match)

    if missing_aria:
        print(f"Found {len(missing_aria)} dashicons missing aria-hidden='true':")
        for m in missing_aria:
            print(f"  - {m}")
        return False
    else:
        print("All dashicons have aria-hidden='true'.")
        return True

if __name__ == "__main__":
    if not verify_dashboard_accessibility():
        sys.exit(1)
