import re

def check_for_limit_in_scheduler():
    filepath = 'ai-post-scheduler/includes/class-aips-scheduler.php'
    with open(filepath, 'r') as f:
        content = f.read()

    # Extract the process_scheduled_posts method
    method_match = re.search(r'public function process_scheduled_posts\(\) \{(.*?)\}', content, re.DOTALL)
    if not method_match:
        print("Could not find process_scheduled_posts method.")
        return False

    method_content = method_match.group(1)

    # Check for SQL query with LIMIT
    # Look for $wpdb->prepare and SELECT
    sql_match = re.search(r'SELECT.*FROM.*LIMIT', method_content, re.DOTALL)

    if sql_match:
        print("Found LIMIT in SQL query.")
        return True
    else:
        print("Did NOT find LIMIT in SQL query.")
        return False

if __name__ == "__main__":
    if check_for_limit_in_scheduler():
        exit(0)
    else:
        exit(1)
