import os
import re
from playwright.sync_api import sync_playwright

def mock_dashboard_php_to_html(php_path, html_path):
    with open(php_path, 'r') as f:
        content = f.read()

    # Mock PHP echo statements
    # <?php echo esc_html($total_generated); ?> -> 123
    content = re.sub(r'<\?php echo esc_html\(\$total_generated\); \?>', '123', content)
    content = re.sub(r'<\?php echo esc_html\(\$pending_scheduled\); \?>', '5', content)
    content = re.sub(r'<\?php echo esc_html\(\$total_templates\); \?>', '10', content)
    content = re.sub(r'<\?php echo esc_html\(\$failed_count\); \?>', '2', content)

    # Mock localization
    # <?php esc_html_e('Posts Generated', 'ai-post-scheduler'); ?> -> Posts Generated
    content = re.sub(r"<\?php esc_html_e\('([^']*)', 'ai-post-scheduler'\); \?>", r"\1", content)

    # Mock URL echoes
    content = re.sub(r"<\?php echo esc_url\(.*?\); \?>", "#", content)

    # Mock complex PHP blocks (if loops/conditionals exist)
    # Removing PHP tags for loops/ifs to flatten the structure for visual verification of the static parts
    # This is a bit aggressive but works for verify static attributes on the grid/table headers
    content = re.sub(r'<\?php .*? \?>', '', content, flags=re.DOTALL)

    # Wrap in basic HTML structure
    html_content = f"""
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Dashboard Mock</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dashicons/3.0.1/css/dashicons.min.css">
        <style>
            body {{ font-family: sans-serif; background: #f1f1f1; padding: 20px; }}
            .wrap {{ max-width: 1200px; margin: 0 auto; }}
            .aips-stats-grid {{ display: flex; gap: 20px; margin-bottom: 20px; }}
            .aips-stat-card {{ background: #fff; padding: 20px; border: 1px solid #ccd0d4; display: flex; align-items: center; gap: 15px; width: 25%; box-sizing: border-box; }}
            .aips-stat-icon {{ font-size: 32px; width: 32px; height: 32px; color: #2271b1; }}
            .aips-stat-content {{ display: flex; flex-direction: column; }}
            .aips-stat-number {{ font-size: 24px; font-weight: bold; color: #1d2327; }}
            .aips-stat-label {{ font-size: 13px; color: #646970; }}
            .widefat {{ width: 100%; border-spacing: 0; background: #fff; border: 1px solid #c3c4c7; }}
            .widefat th {{ text-align: left; padding: 8px 10px; border-bottom: 1px solid #c3c4c7; font-weight: 600; }}
            .widefat td {{ padding: 8px 10px; }}
        </style>
    </head>
    <body>
        {content}
    </body>
    </html>
    """

    with open(html_path, 'w') as f:
        f.write(html_content)

def verify_dashboard(page):
    html_path = os.path.abspath('verification/mock_dashboard.html')
    page.goto(f'file://{html_path}')

    # Verify aria-hidden on stat icons
    stat_icons = page.locator('.aips-stat-icon')
    count = stat_icons.count()
    print(f"Found {count} stat icons")
    for i in range(count):
        icon = stat_icons.nth(i)
        aria_hidden = icon.get_attribute('aria-hidden')
        assert aria_hidden == 'true', f"Stat icon {i} missing aria-hidden='true'"
        print(f"✅ Stat icon {i} has aria-hidden='true'")

    # Verify scope on table headers
    table_headers = page.locator('th')
    th_count = table_headers.count()
    print(f"Found {th_count} table headers")
    # Note: We stripped the PHP loops, so the table bodies might be empty, but the headers are static HTML
    for i in range(th_count):
        th = table_headers.nth(i)
        scope = th.get_attribute('scope')
        assert scope == 'col', f"Table header {i} missing scope='col'"
        print(f"✅ Table header {i} has scope='col'")

    page.screenshot(path='verification/dashboard_a11y.png')
    print("📸 Screenshot saved to verification/dashboard_a11y.png")

if __name__ == '__main__':
    if not os.path.exists('verification'):
        os.makedirs('verification')

    mock_dashboard_php_to_html(
        'ai-post-scheduler/templates/admin/dashboard.php',
        'verification/mock_dashboard.html'
    )

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        try:
            verify_dashboard(page)
        except Exception as e:
            print(f"❌ Verification failed: {e}")
            exit(1)
        finally:
            browser.close()
