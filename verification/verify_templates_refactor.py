
import os
import re

def verify_templates_refactor():
    file_path = 'ai-post-scheduler/includes/class-aips-templates.php'
    with open(file_path, 'r') as f:
        content = f.read()

    # Check 1: calculate_next_run private method should be gone
    # Note: I added a deprecated public wrapper, but the PRIVATE switch-case logic should be gone
    if "private function calculate_next_run" in content:
        print("FAIL: Private calculate_next_run method still exists.")
        return False

    # Check 2: Should use AIPS_Interval_Calculator
    if "private $interval_calculator;" not in content:
         print("FAIL: AIPS_Interval_Calculator property missing.")
         return False

    if "new AIPS_Interval_Calculator()" not in content:
        print("FAIL: AIPS_Interval_Calculator instantiation missing.")
        return False

    # Check 3: Should use schedule_repository->get_active_by_template
    if "$this->schedule_repository->get_active_by_template" not in content:
        print("FAIL: get_active_by_template usage missing.")
        return False

    # Check 4: Should use schedule_repository->get_all_active_for_stats
    if "$this->schedule_repository->get_all_active_for_stats" not in content:
        print("FAIL: get_all_active_for_stats usage missing.")
        return False

    # Check 5: No direct $wpdb usage for querying schedules (except potentially in constructor for table name)
    # The get_pending_stats method used $wpdb->get_results directly. It should now use repo.
    # Let's check specifically inside get_pending_stats

    match = re.search(r'public function get_pending_stats.*?{.*?}', content, re.DOTALL)
    if match:
        func_body = match.group(0)
        if "$wpdb->get_results" in func_body:
             print("FAIL: Direct $wpdb usage found in get_pending_stats.")
             return False

    print("SUCCESS: AIPS_Templates refactor verified.")
    return True

if __name__ == "__main__":
    verify_templates_refactor()
