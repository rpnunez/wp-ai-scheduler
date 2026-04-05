import re

file_path = "ai-post-scheduler/templates/admin/templates.php"

with open(file_path, "r") as f:
    content = f.read()

# 1. Update data-wizard-steps attribute on the modal
content = re.sub(r'data-wizard-steps="5"', 'data-wizard-steps="4"', content)

# 2. Update wizard progress indicator
# Find and remove Step 2
# It looks like:
#                 <div class="aips-wizard-step" data-step="2">
#                     <div class="aips-step-number">2</div>
#                     <div class="aips-step-label"><?php esc_html_e('Title & Excerpt', 'ai-post-scheduler'); ?></div>
#                 </div>
content = re.sub(
    r'[ \t]*<div class="aips-wizard-step" data-step="2">\s*<div class="aips-step-number">2</div>\s*<div class="aips-step-label"><\?php esc_html_e\(\'Title & Excerpt\', \'ai-post-scheduler\'\); \?></div>\s*</div>\n',
    '',
    content
)

# Replace remaining step numbers in the progress indicator
content = re.sub(
    r'<div class="aips-wizard-step" data-step="3">\s*<div class="aips-step-number">3</div>',
    '<div class="aips-wizard-step" data-step="2">\n                    <div class="aips-step-number">2</div>',
    content
)
content = re.sub(
    r'<div class="aips-wizard-step" data-step="4">\s*<div class="aips-step-number">4</div>',
    '<div class="aips-wizard-step" data-step="3">\n                    <div class="aips-step-number">3</div>',
    content
)
content = re.sub(
    r'<div class="aips-wizard-step" data-step="5">\s*<div class="aips-step-number">5</div>',
    '<div class="aips-wizard-step" data-step="4">\n                    <div class="aips-step-number">4</div>',
    content
)


# 3. Combine Step 2 into Step 3
# Step 2 Content:
step2_start = content.find('<!-- Step 2: Title & Excerpt -->')
step2_end = content.find('<!-- Step 3: Content -->', step2_start)

# Step 3 Content:
step3_start = content.find('<!-- Step 3: Content -->')
step3_end = content.find('<!-- Step 4: Featured Image -->', step3_start)

step2_html = content[step2_start:step2_end]
step3_html = content[step3_start:step3_end]

# Modify Step 2 HTML to remove the outer step wrapper
step2_inner = re.search(r'<div class="aips-wizard-step-content" data-step="2" style="display: none;">(.*?)</div>\s*$', step2_html, re.DOTALL)
if step2_inner:
    # Just grab the inner fields we want to move
    # The Title & Excerpt fields
    fields = re.sub(r'<h3>.*?</h3>\s*<p class="description">.*?</p>', '', step2_inner.group(1), count=1, flags=re.DOTALL)
else:
    fields = ""

# Now put it into Step 3 (which will become Step 2)
# Change Step 3 data-step to 2
new_step2_html = re.sub(r'<div class="aips-wizard-step-content" data-step="3" style="display: none;">', '<div class="aips-wizard-step-content" data-step="2" style="display: none;">', step3_html)
# Rename the comment
new_step2_html = new_step2_html.replace('<!-- Step 3: Content -->', '<!-- Step 2: Content, Title & Excerpt -->')

# Insert fields right before the first field in Step 3
# Find the first .aips-form-row in Step 3
insert_pos = new_step2_html.find('<div class="aips-form-row">')
if insert_pos != -1:
    new_step2_html = new_step2_html[:insert_pos] + fields + new_step2_html[insert_pos:]

# Now replace the old Step 2 and Step 3 in the document with the new Step 2
content = content[:step2_start] + new_step2_html + content[step3_end:]


# 4. Renumber remaining steps
content = re.sub(r'<div class="aips-wizard-step-content" data-step="4" style="display: none;">', '<div class="aips-wizard-step-content" data-step="3" style="display: none;">', content)
content = content.replace('<!-- Step 4: Featured Image -->', '<!-- Step 3: Featured Image -->')

content = re.sub(r'<div class="aips-wizard-step-content" data-step="5" style="display: none;">', '<div class="aips-wizard-step-content" data-step="4" style="display: none;">', content)
content = content.replace('<!-- Step 5: Summary -->', '<!-- Step 4: Summary -->')

content = re.sub(r'<div class="aips-wizard-step-content aips-post-save-step" data-step="6" style="display: none;">', '<div class="aips-wizard-step-content aips-post-save-step" data-step="5" style="display: none;">', content)
content = content.replace('<!-- Step 6: Success -->', '<!-- Step 5: Success -->')


with open(file_path, "w") as f:
    f.write(content)

print("Template wizard updated successfully.")
