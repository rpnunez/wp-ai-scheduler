import re

def verify_js():
    with open('ai-post-scheduler/assets/js/admin.js', 'r') as f:
        content = f.read()

    # Check for schedule search binding
    if "$('#aips-schedule-search').on" in content or "filterSchedules" in content:
        print("PASSED: Schedule search logic found in JS.")
    else:
        print("FAILED: Schedule search logic not found in JS.")

if __name__ == "__main__":
    verify_js()
