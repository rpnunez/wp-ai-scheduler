import os
import re

def process_file(filepath):
    with open(filepath, 'r') as f:
        content = f.read()

    # Find all <button ...> tags
    # regex to match <button followed by attributes until >

    def replacer(match):
        tag = match.group(0)
        # Check if type= is in the tag (case insensitive)
        if re.search(r'\btype\s*=', tag, re.IGNORECASE):
            return tag

        # Replace <button with <button type="button"
        # We need to make sure we don't match <button-group etc.
        if tag.startswith('<button ') or tag == '<button>':
            return tag.replace('<button', '<button type="button"', 1)
        return tag

    new_content = re.sub(r'<button(?:\s+[^>]*?)?>', replacer, content, flags=re.IGNORECASE)

    if new_content != content:
        with open(filepath, 'w') as f:
            f.write(new_content)
        print(f"Fixed buttons in {filepath}")

for root, dirs, files in os.walk('ai-post-scheduler'):
    if 'vendor' in root or 'node_modules' in root or '.git' in root:
        continue
    for file in files:
        if file.endswith('.php') or file.endswith('.js'):
            filepath = os.path.join(root, file)
            process_file(filepath)
