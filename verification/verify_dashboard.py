
import re
import os
from playwright.sync_api import sync_playwright

def generate_mock_html():
    with open('ai-post-scheduler/templates/admin/dashboard.php', 'r') as f:
        content = f.read()

    # Simple mock of PHP echo statements
    # Replace <?php echo esc_html($var); ?> with generic text
    content = re.sub(r'<\?php echo esc_html\(\$total_generated\); \?>', '123', content)
    content = re.sub(r'<\?php echo esc_html\(\$pending_scheduled\); \?>', '5', content)
    content = re.sub(r'<\?php echo esc_html\(\$total_templates\); \?>', '10', content)
    content = re.sub(r'<\?php echo esc_html\(\$failed_count\); \?>', '2', content)

    # Replace translations
    content = re.sub(r"<\?php esc_html_e\('([^']+)', 'ai-post-scheduler'\); \?>", r"\1", content)

    # Replace other PHP blocks with empty string or simple mock
    content = re.sub(r'<\?php.*?\?>', '', content, flags=re.DOTALL)

    # Wrap in a basic HTML structure to make it renderable
    html_wrapper = """
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Dashboard Mock</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dashicons/4.6.3/css/dashicons.min.css">
        <style>
            .aips-stats-grid { display: flex; gap: 20px; }
            .aips-stat-card { border: 1px solid #ccc; padding: 20px; display: flex; align_items: center; }
            .aips-stat-icon { font-size: 32px; margin-right: 10px; }
            .widefat { width: 100%; border-collapse: collapse; }
            .widefat td, .widefat th { border: 1px solid #ddd; padding: 8px; }
        </style>
    </head>
    <body>
    """ + content + """
    </body>
    </html>
    """

    output_path = os.path.abspath('verification/dashboard_mock.html')
    with open(output_path, 'w') as f:
        f.write(html_wrapper)

    return output_path

def verify_accessibility(page):
    # Check for aria-hidden on icons
    icons = page.locator('.dashicons')
    count = icons.count()
    print(f"Found {count} dashicons")

    hidden_icons = page.locator('.dashicons[aria-hidden="true"]')
    hidden_count = hidden_icons.count()
    print(f"Found {hidden_count} dashicons with aria-hidden='true'")

    # We expect all decorative icons we touched to have aria-hidden
    # Stats: 4, Quick Actions: 3. Total 7.
    # Note: The mock regex might have stripped the PHP inside attributes if I wasn't careful,
    # but my regex `<\?php.*?\?>` is aggressive.
    # Let's check if my regex broke the attributes.
    # In the code: <div class="aips-stat-icon dashicons dashicons-edit" aria-hidden="true"></div>
    # There is no PHP inside this tag. So it should be fine.

    if hidden_count < 7:
        print("WARNING: Not all icons have aria-hidden='true'")

    # Check table association
    upcoming_heading = page.locator('#aips-upcoming-posts-heading')
    upcoming_table = page.locator('table[aria-labelledby="aips-upcoming-posts-heading"]')

    if upcoming_heading.is_visible() and upcoming_table.is_visible():
        print("Upcoming posts table is correctly associated with heading.")
    else:
        print("FAILED: Upcoming posts table association missing.")

def run():
    html_path = generate_mock_html()
    print(f"Generated mock HTML at {html_path}")

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        page.goto(f"file://{html_path}")

        verify_accessibility(page)

        page.screenshot(path='verification/dashboard_accessibility.png')
        browser.close()

if __name__ == "__main__":
    run()
