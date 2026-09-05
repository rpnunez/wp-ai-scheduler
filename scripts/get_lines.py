import sys

filename = sys.argv[1]
line_num = int(sys.argv[2])

with open('1948.diff', 'r', encoding='utf-8') as f:
    diff_content = f.read()

lines = diff_content.split('\n')
current_file = None
hunks = []
i = 0

while i < len(lines):
    line = lines[i]
    if line.startswith('diff --git'):
        current_file = line.split(' b/')[-1] if ' b/' in line else None
    elif line.startswith('@@') and current_file == filename:
        import re
        m = re.match(r'^@@ -(\d+)(?:,\d+)? \+(\d+)(?:,\d+)? @@', line)
        if m:
            right_ln = int(m.group(2))

            # fast forward through hunk
            j = i + 1
            while j < len(lines) and not lines[j].startswith('diff --git') and not lines[j].startswith('@@'):
                if right_ln >= line_num - 5 and right_ln <= line_num + 5:
                    print(f"{right_ln}: {lines[j]}")

                if not lines[j].startswith('-') and not lines[j].startswith('\\'):
                    right_ln += 1
                j += 1
    i += 1
