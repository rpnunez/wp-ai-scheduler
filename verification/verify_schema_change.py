import re

def verify_schema():
    file_path = 'ai-post-scheduler/includes/class-aips-db-manager.php'
    with open(file_path, 'r') as f:
        content = f.read()

    # Search for the schedule table definition
    schedule_table_pattern = re.compile(r"CREATE TABLE \$table_schedule \((.*?)\) \$charset_collate;", re.DOTALL)
    match = schedule_table_pattern.search(content)

    if not match:
        print("Could not find table_schedule definition")
        return False

    table_def = match.group(1)

    # Check for the new index
    if "KEY is_active_next_run (is_active, next_run)" in table_def:
        print("SUCCESS: Found 'KEY is_active_next_run (is_active, next_run)'")
        return True
    else:
        print("FAILURE: Did not find 'KEY is_active_next_run (is_active, next_run)'")
        print("Current definition content:")
        print(table_def)
        return False

if __name__ == "__main__":
    verify_schema()
