import re

def verify_js():
    with open('ai-post-scheduler/assets/js/admin.js', 'r') as f:
        content = f.read()

    # Check for editSchedule function and binding
    if "editSchedule: function" in content and ".aips-edit-schedule" in content:
        print("PASSED: editSchedule logic found in JS.")
    else:
        print("FAILED: editSchedule logic not found in JS.")

    with open('ai-post-scheduler/templates/admin/schedule.php', 'r') as f:
        php_content = f.read()

    if "aips-edit-schedule" in php_content and "data-next-run" in php_content:
        print("PASSED: Edit button and data-next-run attribute found in PHP.")
    else:
        print("FAILED: Edit button or data-next-run attribute missing in PHP.")

if __name__ == "__main__":
    verify_js()
