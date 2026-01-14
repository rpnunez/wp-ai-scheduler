import re
import os

def verify_schema_change():
    filepath = 'ai-post-scheduler/includes/class-aips-db-manager.php'
    if not os.path.exists(filepath):
        print(f"Error: {filepath} not found.")
        return False

    with open(filepath, 'r') as f:
        content = f.read()

    # Check for history table index
    history_pattern = re.compile(r"KEY\s+created_at\s+\(created_at\)")
    if not history_pattern.search(content):
        print("Error: 'KEY created_at (created_at)' not found in history table definition.")
    else:
        print("Success: 'KEY created_at (created_at)' found in history table definition.")

    # Check for schedule table index
    schedule_pattern = re.compile(r"KEY\s+is_active_next_run\s+\(is_active,\s*next_run\)")
    if not schedule_pattern.search(content):
        print("Error: 'KEY is_active_next_run (is_active, next_run)' not found in schedule table definition.")
    else:
        print("Success: 'KEY is_active_next_run (is_active, next_run)' found in schedule table definition.")

    return history_pattern.search(content) and schedule_pattern.search(content)

if __name__ == "__main__":
    if verify_schema_change():
        print("Verification passed!")
    else:
        print("Verification failed!")
        exit(1)
