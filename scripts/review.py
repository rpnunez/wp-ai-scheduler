import re

def review_diff(diff_content):
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

    comments = []

    for filename, file_hunks in files.items():
        if not filename.endswith('.php') and not filename.endswith('.js'): continue
        if filename.startswith('docs/') or filename.startswith('tests/'): continue

        for hunk in file_hunks:
            right_ln = hunk['right_start']
            left_ln = hunk['left_start']
            for line in hunk['lines']:
                if line.startswith('+') and not line.startswith('+++'):
                    # Check for issues here

                    code = line[1:]

                    # 1. SQL Injection / Prepared statements
                    if re.search(r'\$wpdb->(?:query|get_results|get_row|get_col|get_var)\(.*\$[a-zA-Z0-9_]+', code) and not 'prepare' in code:
                        comments.append({
                            'file': filename,
                            'line': right_ln,
                            'severity': '🔴',
                            'message': 'Potential SQL injection. Use `$wpdb->prepare()` when querying with dynamic variables.',
                            'code': code
                        })

                    # 2. XSS / Escaping in JS or PHP rendering (very naive)
                    if 'echo $_' in code or 'echo $' in code:
                        if not any(x in code for x in ['esc_html', 'esc_attr', 'esc_url', 'esc_js', 'wp_kses']):
                            comments.append({
                                'file': filename,
                                'line': right_ln,
                                'severity': '🔴',
                                'message': 'Potential XSS vulnerability. Ensure this variable is properly escaped (e.g., `esc_html()`, `esc_attr()`) before outputting.',
                                'code': code
                            })

                    # 3. Hardcoded values (low severity)
                    # if re.search(r'(?<!_)limit\s*=\s*\d+', code.lower()):
                    #    comments.append({
                    #        'file': filename,
                    #        'line': right_ln,
                    #        'severity': '🟢',
                    #        'message': 'Consider refactoring this hardcoded number to a constant.',
                    #        'code': code
                    #    })

                    # 4. Error logging checks
                    if 'error_log' in code and 'print_r' in code:
                         comments.append({
                                'file': filename,
                                'line': right_ln,
                                'severity': '🟡',
                                'message': 'Avoid using `print_r` in `error_log` for production code. Use structured logging or `wp_json_encode` instead.',
                                'code': code
                            })

                    right_ln += 1
                elif line.startswith('-') and not line.startswith('---'):
                    left_ln += 1
                elif not line.startswith('\\'):
                    right_ln += 1
                    left_ln += 1

    return comments

with open('1948.diff', 'r', encoding='utf-8') as f:
    diff_content = f.read()

comments = review_diff(diff_content)
for c in comments:
    print(f"{c['file']}:{c['line']} [{c['severity']}] {c['message']} - {c['code'].strip()}")
