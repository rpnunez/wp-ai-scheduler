import re
import os

def verify_dashboard_a11y():
    file_path = 'ai-post-scheduler/templates/admin/dashboard.php'
    if not os.path.exists(file_path):
        print(f"❌ File not found: {file_path}")
        return

    with open(file_path, 'r') as f:
        content = f.read()

    errors = []

    # 1. Check stat icons
    # Pattern: <div class="aips-stat-icon ...">
    # We want to ensure it has aria-hidden="true"
    stat_icon_pattern = re.compile(r'<div class="aips-stat-icon[^>]*>')
    stat_icons = stat_icon_pattern.findall(content)

    for icon in stat_icons:
        if 'aria-hidden="true"' not in icon:
            errors.append(f"Stat icon missing aria-hidden: {icon}")

    # 2. Check quick action icons
    # Specific dashicons we identified
    dashicons_to_check = [
        'dashicons-plus-alt',
        'dashicons-calendar-alt',
        'dashicons-admin-generic'
    ]

    # Simple string check for the lines containing these classes inside spans
    for dashicon in dashicons_to_check:
        # Find the full span tag containing this class
        pattern = re.compile(f'<span class="dashicons {dashicon}[^"]*".*?>')
        matches = pattern.findall(content)
        for match in matches:
            if 'aria-hidden="true"' not in match:
                errors.append(f"Quick action icon missing aria-hidden: {match}")

    # 3. Check table headers
    # We want to ensure <th> has scope="col"
    # This regex looks for <th> tags that DO NOT have scope="col"
    # It's a bit naive but should work for this file's structure
    th_pattern = re.compile(r'<th(?:\s+(?!scope="col")[^>]*)?>')
    th_tags = th_pattern.findall(content)

    if len(th_tags) > 0:
        # Filter out false positives if any (though the negative lookahead should handle it)
        # Let's simple check counts
        total_ths = len(re.findall(r'<th[\s>]', content))
        scoped_ths = len(re.findall(r'scope="col"', content))
        if total_ths > scoped_ths:
            errors.append(f"Found {total_ths - scoped_ths} <th> tags likely missing scope='col'")

    if errors:
        print("❌ Verification Failed:")
        for error in errors:
            print(f"  - {error}")
    else:
        print("✅ Verification Passed: All accessibility checks passed.")

if __name__ == "__main__":
    verify_dashboard_a11y()
