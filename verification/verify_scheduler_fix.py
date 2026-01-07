import re

def verify_fix():
    # Verify fallback removal in Controller
    with open('ai-post-scheduler/includes/class-aips-schedule-controller.php', 'r') as f:
        controller_content = f.read()

    if "global $wpdb;" in controller_content and "fallback" in controller_content.lower():
        print("FAILED: Fallback SQL logic still present in AIPS_Schedule_Controller.")
    else:
        print("PASSED: Fallback SQL logic removed from AIPS_Schedule_Controller.")

    # Verify method existence in Scheduler
    with open('ai-post-scheduler/includes/class-aips-scheduler.php', 'r') as f:
        scheduler_content = f.read()

    if "public function toggle_active" in scheduler_content:
         print("PASSED: toggle_active exists in AIPS_Scheduler.")
    else:
         print("FAILED: toggle_active missing in AIPS_Scheduler.")

if __name__ == "__main__":
    verify_fix()
