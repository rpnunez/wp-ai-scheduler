import re

file_path = 'ai-post-scheduler/includes/class-aips-templates-controller.php'

def check_file():
    with open(file_path, 'r') as f:
        content = f.read()

    # Check for unescaped get_permalink
    pattern1 = r'<a href="\<\?php echo get_permalink\(\$item->post_id\); \?>" target="_blank">'
    match1 = re.search(pattern1, content)

    # Check for unescaped get_edit_post_link
    pattern2 = r'<a href="\<\?php echo get_edit_post_link\(\$item->post_id\); \?>" class="button button-small" target="_blank">'
    match2 = re.search(pattern2, content)

    if match1 or match2:
        print("VULNERABILITY FOUND: Unescaped URL output detected.")
        if match1: print("- Found unescaped get_permalink")
        if match2: print("- Found unescaped get_edit_post_link")
        return True
    else:
        # Check if they are escaped
        pattern3 = r'<a href="\<\?php echo esc_url\(get_permalink\(\$item->post_id\)\); \?>" target="_blank">'
        pattern4 = r'<a href="\<\?php echo esc_url\(get_edit_post_link\(\$item->post_id\)\); \?>" class="button button-small" target="_blank">'

        match3 = re.search(pattern3, content)
        match4 = re.search(pattern4, content)

        if match3 and match4:
            print("SUCCESS: URLs are properly escaped.")
            return False
        else:
            print("WARNING: Vulnerability not found, but fix also not found. Manual inspection required.")
            print("Content excerpt:")
            # Find context around "href"
            start_index = content.find('href="<?php echo get_permalink')
            if start_index != -1:
                print(content[start_index:start_index+100])
            return False

if __name__ == "__main__":
    if check_file():
        # Found vulnerability
        exit(0)
    else:
        # Did not find vulnerability (or already fixed)
        exit(1)
