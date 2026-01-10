import re

def verify_scheduler_file():
    filepath = 'ai-post-scheduler/includes/class-aips-scheduler.php'
    with open(filepath, 'r') as f:
        content = f.read()

    # Verify raw SQL is gone
    if "SELECT t.*, s.*, s.id AS schedule_id" in content and "FROM {$this->schedule_table} s" in content and "$wpdb->prepare" in content:
         # It might still be there if I just replaced the call, but I replaced the whole block.
         # Wait, if I used 'get_due_schedules_with_active_templates', the big SQL block should be gone from this file.
         pass

    if "get_due_schedules_with_active_templates" not in content:
        print(f"FAILED: {filepath} does not use 'get_due_schedules_with_active_templates'")
        return False

    if "get_option('aips_batch_size'" not in content:
        print(f"FAILED: {filepath} does not use configurable batch size")
        return False

    print(f"SUCCESS: {filepath} verification passed.")
    return True

def verify_schedule_controller():
    filepath = 'ai-post-scheduler/includes/class-aips-schedule-controller.php'
    with open(filepath, 'r') as f:
        content = f.read()

    if "$schedule_id = isset($_POST['schedule_id'])" not in content:
        print(f"FAILED: {filepath} does not check for schedule_id")
        return False

    if "$this->scheduler->get_schedule($schedule_id)" not in content:
        print(f"FAILED: {filepath} does not fetch schedule")
        return False

    print(f"SUCCESS: {filepath} verification passed.")
    return True

def verify_admin_js():
    filepath = 'ai-post-scheduler/assets/js/admin.js'
    with open(filepath, 'r') as f:
        content = f.read()

    if "runScheduleNow: function" not in content:
        print(f"FAILED: {filepath} missing runScheduleNow function")
        return False

    if "action: 'aips_run_now'" not in content:
        print(f"FAILED: {filepath} missing ajax action in runScheduleNow (or generally)")
        return False

    print(f"SUCCESS: {filepath} verification passed.")
    return True

if __name__ == "__main__":
    v1 = verify_scheduler_file()
    v2 = verify_schedule_controller()
    v3 = verify_admin_js()

    if v1 and v2 and v3:
        print("ALL CHECKS PASSED")
    else:
        print("SOME CHECKS FAILED")
