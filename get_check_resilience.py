import sys
with open('ai-post-scheduler/includes/class-aips-system-status.php', 'r') as f:
    lines = f.readlines()

for i, line in enumerate(lines[667:742]):
    print(f"{i+668}: {line}", end='')
