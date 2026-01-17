import re
import sys

def verify_structures_copy():
    filepath = 'ai-post-scheduler/templates/admin/structures.php'

    with open(filepath, 'r') as f:
        content = f.read()

    # Normalize content (collapse whitespace)
    normalized_content = re.sub(r'\s+', ' ', content)

    # 1. Check for th class="column-key"
    th_part = r"""<th class="column-key"><?php esc_html_e('Key', 'ai-post-scheduler'); ?></th>"""
    normalized_th = re.sub(r'\s+', ' ', th_part)

    if normalized_th not in normalized_content:
        print("❌ Error: <th class='column-key'> not found.")
        print("Expected:", normalized_th)
        return False
    else:
        print("✅ TH verified.")

    # 2. Check for div wrapper
    div_start = r'<td class="column-key"> <div class="aips-variable-code-cell">'
    normalized_div_start = re.sub(r'\s+', ' ', div_start)
    if normalized_div_start not in normalized_content:
        print("❌ Error: <td class='column-key'><div class='aips-variable-code-cell'> not found.")
        return False
    else:
        print("✅ Div start verified.")

    # 3. Check for code block
    code_block = r"""<code><?php echo esc_html($section->section_key); ?></code>"""
    normalized_code = re.sub(r'\s+', ' ', code_block)
    if normalized_code not in normalized_content:
         print("❌ Error: Code block not found.")
         return False
    else:
        print("✅ Code block verified.")

    # 4. Check for button
    button_part = r"""<button type="button" class="aips-copy-btn" data-clipboard-text="{{section:<?php echo esc_attr($section->section_key); ?>}}" aria-label="<?php esc_attr_e('Copy placeholder', 'ai-post-scheduler'); ?>" title="<?php esc_attr_e('Copy placeholder', 'ai-post-scheduler'); ?>">"""
    normalized_button = re.sub(r'\s+', ' ', button_part)

    if normalized_button not in normalized_content:
        print("❌ Error: Button start not found.")
        print("Expected:", normalized_button)
        return False
    else:
        print("✅ Button start verified.")

    print("✅ Success: 'Copy to Clipboard' button structure verified in structures.php")
    return True

if __name__ == "__main__":
    if verify_structures_copy():
        sys.exit(0)
    else:
        sys.exit(1)
