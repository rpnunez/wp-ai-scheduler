from playwright.sync_api import sync_playwright
import os

def run():
    # Read the PHP template content
    with open("ai-post-scheduler/templates/admin/settings.php", "r") as f:
        php_content = f.read()

    # Extract the table content (simple extraction for verification)
    # We are mocking the rendering because we can't run PHP
    table_start = php_content.find('<table class="widefat striped">')
    table_end = php_content.find('</table>', table_start) + 8
    table_html = php_content[table_start:table_end]

    # Clean up PHP tags for the mock
    # Replace PHP echo calls with mock data
    table_html = table_html.replace("<?php esc_html_e('Variable', 'ai-post-scheduler'); ?>", "Variable")
    table_html = table_html.replace("<?php esc_html_e('Description', 'ai-post-scheduler'); ?>", "Description")
    table_html = table_html.replace("<?php esc_html_e('Example', 'ai-post-scheduler'); ?>", "Example")
    table_html = table_html.replace("<?php esc_html_e('Actions', 'ai-post-scheduler'); ?>", "Actions")

    table_html = table_html.replace("<?php esc_html_e('Current date', 'ai-post-scheduler'); ?>", "Current date")
    table_html = table_html.replace("<?php echo esc_html(date('F j, Y')); ?>", "May 29, 2024")

    table_html = table_html.replace("<?php esc_attr_e('Copy {{date}} variable', 'ai-post-scheduler'); ?>", "Copy {{date}} variable")
    table_html = table_html.replace("<?php esc_html_e('Copy', 'ai-post-scheduler'); ?>", "Copy")

    # We just need to verify that the button with the class and data attribute exists
    # So we don't need to perfectly replace everything, just enough to be valid HTML

    # Create a mock HTML file
    mock_html_content = f"""
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Settings Verification</title>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <style>
            .aips-copy-btn {{ cursor: pointer; color: blue; }}
        </style>
    </head>
    <body>
        <div class="wrap aips-wrap">
            {table_html}
        </div>

        <script>
            // Mock the AIPS object and copy function from admin.js
            window.AIPS = {{
                copyToClipboard: function(e) {{
                    e.preventDefault();
                    var $btn = $(this);
                    var text = $btn.data('clipboard-text');
                    console.log('Copying: ' + text);
                    $btn.text('Copied!');
                    $btn.addClass('copied');
                }}
            }};

            $(document).on('click', '.aips-copy-btn', window.AIPS.copyToClipboard);
        </script>
    </body>
    </html>
    """

    with open("verification/mock_settings_copy.html", "w") as f:
        f.write(mock_html_content)

    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()
        page.goto(f"file://{os.path.abspath('verification/mock_settings_copy.html')}")

        # Check if the button exists for {{date}}
        copy_btn = page.locator("button[data-clipboard-text='{{date}}']")
        assert copy_btn.count() > 0, "Copy button for {{date}} not found"

        # Click the button
        copy_btn.click()

        # Check if text changed to "Copied!" (verifying the click handler worked)
        assert page.locator("button[data-clipboard-text='{{date}}']").inner_text() == "Copied!", "Button text did not change to 'Copied!'"

        print("Verification successful: Copy button exists and is clickable.")

        # Take a screenshot
        page.screenshot(path="verification/verify_settings_copy.png")

        browser.close()

if __name__ == "__main__":
    run()
