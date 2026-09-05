import re

def extract_hunks(diff_content):
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
            current_file = line.split(' b/')[-1] if ' b/' in line else None
        elif line.startswith('@@'):
            m = re.match(r'^@@ -(\d+)(?:,\d+)? \+(\d+)(?:,\d+)? @@', line)
            if m:
                left_line = int(m.group(1))
                right_line = int(m.group(2))
                hunks.append({'header': line, 'left_start': left_line, 'right_start': right_line, 'lines': []})
        elif current_file and hunks:
            hunks[-1]['lines'].append(line)
        i += 1

    if current_file:
        files[current_file] = hunks

    return files

with open('1948.diff', 'r', encoding='utf-8') as f:
    diff_content = f.read()

files = extract_hunks(diff_content)

php_files_with_additions = []
for filename, hunks in files.items():
    if filename.endswith('.php') or filename.endswith('.js'):
        has_additions = False
        for hunk in hunks:
            for line in hunk['lines']:
                if line.startswith('+') and not line.startswith('+++'):
                    has_additions = True
                    break
        if has_additions:
            php_files_with_additions.append(filename)

print("Files to review:")
for f in php_files_with_additions:
    if 'tests/' not in f and 'docs/' not in f:
        print(f)
