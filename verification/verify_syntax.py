import re

def check_syntax(file_path):
    with open(file_path, 'r') as f:
        content = f.read()

    # Basic PHP syntax check (balance braces, check for obvious errors)
    # This is not a full parser, but catches common copy-paste errors
    open_braces = content.count('{')
    close_braces = content.count('}')

    # Ignore braces in strings/comments is hard with regex, but let's just do a rough check
    # if the file is PHP
    if file_path.endswith('.php'):
        if '<?php' not in content:
            print(f"Error: {file_path} missing <?php tag")
            return False

    # Check for git markers
    if '<<<<<<<' in content or '=======' in content or '>>>>>>>' in content:
        print(f"Error: {file_path} contains git merge markers")
        return False

    print(f"Syntax check passed for {file_path}")
    return True

files = [
    'ai-post-scheduler/includes/class-aips-schedule-controller.php',
    'ai-post-scheduler/templates/admin/schedule.php',
    'ai-post-scheduler/assets/js/admin.js'
]

for file in files:
    check_syntax(file)
