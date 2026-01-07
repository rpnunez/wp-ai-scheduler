import re

def verify_refactor():
    with open('ai-post-scheduler/includes/class-aips-scheduler.php', 'r') as f:
        content = f.read()

    if "$wpdb->get_results" in content:
        # Check if it's the specific query we are targeting
        if "SELECT t.*, s.*, s.id AS schedule_id" in content:
             print("FAILED: Raw SQL query still present in process_scheduled_posts.")
        else:
             # Might be other queries, but let's assume if the specific one is gone, we are good for now.
             # Or check if get_due_schedules_with_active_templates is used.
             if "get_due_schedules_with_active_templates" in content:
                 print("PASSED: Method calls new repository method.")
             else:
                 print("WARNING: Raw SQL might be gone but new method call not found (or different name).")
    else:
         print("PASSED: No direct $wpdb->get_results calls in Scheduler.")

if __name__ == "__main__":
    verify_refactor()
