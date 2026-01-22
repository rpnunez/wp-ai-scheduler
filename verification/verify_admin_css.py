from playwright.sync_api import sync_playwright
import os

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Create a mock HTML file that uses the CSS
        html_content = """
        <!DOCTYPE html>
        <html>
        <head>
            <link rel="stylesheet" href="../ai-post-scheduler/assets/css/admin.css">
            <style>
                body { font-family: sans-serif; padding: 20px; }
            </style>
        </head>
        <body>
            <h1>Test .aips-input-group</h1>

            <div class="aips-input-group">
                <input type="text" value="Test Input" style="padding: 10px; border: 1px solid #ccc;">
                <button class="button">Action</button>
            </div>

            <hr>

            <div style="display: block;">
                <p>Without class (for comparison):</p>
                <input type="text" value="Test Input" style="padding: 10px; border: 1px solid #ccc;">
                <button class="button">Action</button>
            </div>
        </body>
        </html>
        """

        with open('verification/mock_admin.html', 'w') as f:
            f.write(html_content)

        page.goto('file://' + os.path.abspath('verification/mock_admin.html'))

        # Take a screenshot
        page.screenshot(path='verification/admin_css.png')

        browser.close()

if __name__ == '__main__':
    run()
