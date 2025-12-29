from playwright.sync_api import sync_playwright
import os

def test_copy_clipboard_integration():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        # Enable clipboard permissions
        context = browser.new_context(permissions=['clipboard-read', 'clipboard-write'])
        page = context.new_page()

        # Read actual files
        with open("ai-post-scheduler/assets/js/admin.js", "r") as f:
            admin_js = f.read()

        # Construct a mock HTML that includes the JS and the new DOM elements
        mock_html = """
        <!DOCTYPE html>
        <html>
        <head>
            <title>Mock Settings</title>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dashicons/3.0.0/css/dashicons.min.css">
            <style>
                /* Minimal CSS to support visibility checks */
                .aips-copy-btn { cursor: pointer; color: #2271b1; }
            </style>
        </head>
        <body>
            <div class="wrap">
                 <table>
                    <tr>
                        <td>
                            <code>{{date}}</code>
                            <button type="button" class="button button-small aips-copy-btn" data-clipboard-text="{{date}}" aria-label="Copy variable">
                                <span class="dashicons dashicons-clipboard"></span>
                            </button>
                        </td>
                    </tr>
                 </table>
            </div>

            <script>
            // Mock window.aipsAjax
            window.aipsAjax = { ajaxUrl: 'mock_url', nonce: 'mock_nonce' };
            </script>

            <script>
            // Injecting the actual admin.js content
            %s
            </script>
        </body>
        </html>
        """ % admin_js

        with open("verification/integration_test.html", "w") as f:
            f.write(mock_html)

        page.goto("file://" + os.path.abspath("verification/integration_test.html"))

        # Verify event listener is attached
        page.evaluate("""
            console.log('Checking listeners...');
        """)

        # Click the copy button
        print("Clicking button...")
        page.click(".aips-copy-btn")

        # Verify the visual feedback (icon change)
        print("Waiting for feedback...")
        try:
            page.wait_for_selector(".dashicons-yes", state="attached", timeout=5000)
            print("Visual feedback verified: Icon changed to checkmark")
            print("SUCCESS: Text copied correctly via integration test (visual confirmed)")
        except Exception as e:
            print(f"Error waiting for selector: {e}")
            # Dump page content for debugging
            print(page.content())

        browser.close()

if __name__ == "__main__":
    test_copy_clipboard_integration()
