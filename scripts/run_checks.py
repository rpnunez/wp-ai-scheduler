import json
import re

def review_file(filename, hunks):
    comments = []

    for hunk in hunks:
        right_ln = hunk['right_start']
        left_ln = hunk['left_start']

        for i, line in enumerate(hunk['lines']):
            if line.startswith('+') and not line.startswith('+++'):
                code = line[1:]

                # Check for SQL injection in DB Manager or Repository
                if '$wpdb->' in code and not 'prepare' in code:
                    if re.search(r'query\(|get_results\(|get_row\(|get_col\(|get_var\(', code) and re.search(r'\$[a-zA-Z0-9_]+', code):
                        if not any('prepare' in x for x in hunk['lines'][max(0, i-3):i]):
                            comments.append({
                                'file': filename,
                                'line': right_ln,
                                'severity': '🔴',
                                'message': 'Potential SQL injection. Use `$wpdb->prepare()` when querying with dynamic variables.',
                                'suggestion': line.replace('query(', 'query( $wpdb->prepare(') # Naive
                            })

                # Check for XSS
                if 'echo $' in code or 'echo $_' in code or 'echo  $' in code:
                    if not any(x in code for x in ['esc_html', 'esc_attr', 'esc_url', 'esc_js', 'wp_kses', 'esc_textarea']):
                        if not re.search(r'echo\s+\$[a-zA-Z0-9_]+->[a-zA-Z0-9_]+\(', code):
                            comments.append({
                                'file': filename,
                                'line': right_ln,
                                'severity': '🔴',
                                'message': 'Potential XSS vulnerability. Ensure this variable is properly escaped (e.g., `esc_html()`, `esc_attr()`) before outputting.',
                                'suggestion': line.replace('echo $', 'echo esc_html( $')
                            })

                right_ln += 1
            elif line.startswith('-') and not line.startswith('---'):
                left_ln += 1
            elif not line.startswith('\\\\'):
                right_ln += 1
                left_ln += 1

    return comments

with open('1948.diff', 'r', encoding='utf-8') as f:
    diff_content = f.read()

files = {}
current_file = None
hunks = []

lines = diff_content.split('\n')
for line in lines:
    if line.startswith('diff --git'):
        if current_file:
            files[current_file] = hunks
            hunks = []
        current_file = line.split(' b/')[-1] if ' b/' in line else None
    elif line.startswith('@@'):
        m = re.match(r'^@@ -(\d+)(?:,\d+)? \+(\d+)(?:,\d+)? @@', line)
        if m:
            hunks.append({'header': line, 'left_start': int(m.group(1)), 'right_start': int(m.group(2)), 'lines': []})
    elif current_file and hunks:
        hunks[-1]['lines'].append(line)

if current_file:
    files[current_file] = hunks

all_comments = []
for filename, hunks in files.items():
    if filename.endswith('.php') or filename.endswith('.js'):
        if 'tests/' not in filename and 'docs/' not in filename:
             all_comments.extend(review_file(filename, hunks))

print(json.dumps(all_comments, indent=2))
