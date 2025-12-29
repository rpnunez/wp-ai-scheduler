from playwright.sync_api import sync_playwright
import os

def verify_feature():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        # Grant permissions
        context = browser.new_context(permissions=['clipboard-read', 'clipboard-write'])
        page = context.new_page()

        with open("ai-post-scheduler/assets/js/admin.js", "r") as f:
            admin_js = f.read()

        mock_html = """
        <!DOCTYPE html>
        <html>
        <head>
            <title>Settings Feature Verification</title>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dashicons/3.0.0/css/dashicons.min.css">
            <style>
                body { padding: 20px; }
                .dashicons-yes { color: #00a32a; border: 1px solid red; } /* Style to see if it changes */
            </style>
        </head>
        <body>
            <button type="button" class="button button-small aips-copy-btn" data-clipboard-text="{{date}}">
                <span class="dashicons dashicons-clipboard"></span>
            </button>

            <div id="console-log"></div>

            <script>
            window.aipsAjax = { ajaxUrl: 'mock_url', nonce: 'mock_nonce' };

            // Capture logs
            var oldLog = console.log;
            console.log = function(message) {
                document.getElementById('console-log').innerHTML += message + '<br>';
                oldLog.apply(console, arguments);
            };
            var oldError = console.error;
            console.error = function(message) {
                document.getElementById('console-log').innerHTML += 'ERROR: ' + message + '<br>';
                oldError.apply(console, arguments);
            };
            </script>
            <script>
            """ + admin_js + """
            </script>
        </body>
        </html>
        """

        with open("verification/feature_test.html", "w") as f:
            f.write(mock_html)

        page.goto("file://" + os.path.abspath("verification/feature_test.html"))

        # Click
        print("Clicking...")
        page.click(".aips-copy-btn")

        # Wait a bit
        page.wait_for_timeout(1000)

        # Check logs
        logs = page.eval_on_selector("#console-log", "el => el.innerHTML")
        print("Page Logs:", logs)

        # Check class
        cls = page.eval_on_selector(".aips-copy-btn span", "el => el.className")
        print("Icon Class:", cls)

        page.screenshot(path="verification/debug_click.png")

        if "dashicons-yes" in cls:
            print("SUCCESS: Class changed")
        else:
            print("FAILURE: Class did not change")

        browser.close()

if __name__ == "__main__":
    verify_feature()
