import sys
with open('ai-post-scheduler/includes/class-aips-system-status.php', 'r') as f:
    lines = f.readlines()

for i, line in enumerate(lines[552:658]):
    print(f"{i+553}: {line}", end='')
