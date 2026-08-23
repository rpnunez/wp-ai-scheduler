import re

def manual_review(diff_content):
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

    for filename, file_hunks in files.items():
        if not filename.endswith('.php') and not filename.endswith('.js'): continue
        if filename.startswith('docs/') or filename.startswith('tests/'): continue

        for hunk in file_hunks:
            right_ln = hunk['right_start']
            left_ln = hunk['left_start']
            for line in hunk['lines']:
                if line.startswith('+') and not line.startswith('+++'):
                    code = line[1:]

                    # Look for things to comment on
                    if 'wp_localize_script' in code and 'array' not in code and '[' not in code:
                        pass

                    if filename.endswith('.js') and 'console.log' in code:
                        print(f"{filename}:{right_ln} - console.log found")

                    if 'error_log' in code:
                        print(f"{filename}:{right_ln} - error_log found")

                    if 'SELECT *' in code:
                        print(f"{filename}:{right_ln} - SELECT * found")

                    if 'get_option' in code and not 'get_option(' in code.replace('get_option', 'get_option('):
                        pass

                    if 'var ' in code and filename.endswith('.js'):
                        print(f"{filename}:{right_ln} - 'var' instead of let/const in JS")

                    if '<label' in code and 'aria-label' not in code and 'screen-reader-text' not in code:
                        print(f"{filename}:{right_ln} - <label> possibly missing screen-reader-text or aria-label (need manual check)")

                    # Check aria-hidden on dashicons
                    if 'dashicons' in code and 'aria-hidden="true"' not in code:
                         print(f"{filename}:{right_ln} - dashicon possibly missing aria-hidden='true'")

                    right_ln += 1
                elif line.startswith('-') and not line.startswith('---'):
                    left_ln += 1
                elif not line.startswith('\\'):
                    right_ln += 1
                    left_ln += 1

with open('1948.diff', 'r', encoding='utf-8') as f:
    diff_content = f.read()

manual_review(diff_content)
