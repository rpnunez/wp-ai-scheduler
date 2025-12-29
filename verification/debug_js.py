from playwright.sync_api import sync_playwright
import os

def debug_js():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Read admin.js and inject logs for debugging
        with open("ai-post-scheduler/assets/js/admin.js", "r") as f:
            admin_js = f.read()

        # Inject logs
        admin_js = admin_js.replace("copyToClipboard: function(e) {", "copyToClipboard: function(e) { console.log('copyToClipboard called');")
        admin_js = admin_js.replace("bindEvents: function() {", "bindEvents: function() { console.log('bindEvents called');")
        admin_js = admin_js.replace("AIPS.init();", "console.log('AIPS.init called'); AIPS.init();")

        mock_html = """
        <!DOCTYPE html>
        <html>
        <head>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        </head>
        <body>
            <button class="aips-copy-btn" data-clipboard-text="test">Copy</button>
            <script>
            window.aipsAjax = { ajaxUrl: '', nonce: '' };
            </script>
            <script>
            %s
            </script>
        </body>
        </html>
        """ % admin_js

        with open("verification/debug_test.html", "w") as f:
            f.write(mock_html)

        page.on("console", lambda msg: print(f"Console: {msg.text}"))
        page.goto("file://" + os.path.abspath("verification/debug_test.html"))

        page.click(".aips-copy-btn")

        browser.close()

if __name__ == "__main__":
    debug_js()
