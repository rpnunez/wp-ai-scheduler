import sys
with open('ai-post-scheduler/includes/class-aips-system-status.php', 'r') as f:
    lines = f.readlines()
print(f"Total lines: {len(lines)}")
import re

def get_function_blocks():
    funcs = []
    current_func = None
    start_line = 0
    brace_count = 0
    in_func = False

    for i, line in enumerate(lines):
        if not in_func:
            match = re.search(r'(public|private|protected)\s+(static\s+)?function\s+([a-zA-Z0-9_]+)\s*\(', line)
            if match:
                current_func = match.group(3)
                start_line = i + 1
                in_func = True
                brace_count = line.count('{') - line.count('}')
        else:
            brace_count += line.count('{') - line.count('}')
            if brace_count <= 0:
                funcs.append((current_func, start_line, i + 1, i + 1 - start_line + 1))
                in_func = False
                current_func = None

    funcs.sort(key=lambda x: x[3], reverse=True)
    for f in funcs[:15]:
        print(f"{f[0]}: {f[3]} lines ({f[1]}-{f[2]})")

get_function_blocks()
