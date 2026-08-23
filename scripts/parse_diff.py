import re

def parse_diff(diff_content):
    files = {}
    current_file = None
    hunks = []

    lines = diff_content.split('\n')
    i = 0
    while i < len(lines):
        line = lines[i]

        if line.startswith('diff --git'):
            if current_file:
                files[current_file] = hunks
                hunks = []
            current_file = line.split(' b/')[1] if ' b/' in line else None
        elif line.startswith('@@'):
            # @@ -1,4 +1,4 @@
            m = re.match(r'^@@ -(\d+)(?:,\d+)? \+(\d+)(?:,\d+)? @@', line)
            if m:
                hunks.append({'header': line, 'lines': []})
        elif current_file and hunks:
            hunks[-1]['lines'].append(line)
        i += 1

    if current_file:
        files[current_file] = hunks

    return files

with open('1948.diff', 'r', encoding='utf-8') as f:
    diff_content = f.read()

parsed = parse_diff(diff_content)
for f in parsed:
    print(f)
