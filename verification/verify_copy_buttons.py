import re

def verify_copy_buttons():
    file_path = 'ai-post-scheduler/templates/admin/settings.php'
    try:
        with open(file_path, 'r') as f:
            content = f.read()
    except FileNotFoundError:
        print(f"Error: File {file_path} not found.")
        return False

    variables = [
        '{{date}}',
        '{{year}}',
        '{{month}}',
        '{{day}}',
        '{{time}}',
        '{{site_name}}',
        '{{site_description}}',
        '{{random_number}}'
    ]

    missing_buttons = []

    for var in variables:
        # Regex to find the button for this specific variable
        # It looks for data-clipboard-text="VAR" and class="...aips-copy-btn..."
        # We need to be less strict about order of attributes in regex or just check presence of substrings

        escaped_var = re.escape(var)

        # Check if the line exists that contains both the class and the data attribute for this var
        # Note: In my implementation, they are on the same line.

        lines = content.split('\n')
        found = False
        for line in lines:
            if f'data-clipboard-text="{var}"' in line and 'aips-copy-btn' in line:
                found = True
                break

        if not found:
            missing_buttons.append(var)

    if missing_buttons:
        print(f"FAIL: Missing copy buttons for: {', '.join(missing_buttons)}")
        return False

    print("PASS: All variables have copy buttons with correct class and data attribute.")

    # Check for aria-labels generically
    # The php string is: <?php esc_attr_e('Copy variable', 'ai-post-scheduler'); ?>
    # We should search for 'aria-label="' followed by that php block

    expected_aria = "aria-label=\"<?php esc_attr_e('Copy variable', 'ai-post-scheduler'); ?>\""
    aria_count = content.count(expected_aria)

    if aria_count != len(variables):
        print(f"WARNING: Expected {len(variables)} aria-labels, found {aria_count}")
    else:
        print(f"PASS: Found {aria_count} aria-labels.")

    return True

if __name__ == "__main__":
    verify_copy_buttons()
