
import pytest
from playwright.sync_api import sync_playwright

def test_copy_button():
    with sync_playwright() as p:
        browser = p.chromium.launch()
        context = browser.new_context()
        # Grant clipboard permissions
        context.grant_permissions(['clipboard-read', 'clipboard-write'])
        page = context.new_page()

        # Mock HTML content
        html_content = """
        <!DOCTYPE html>
        <html>
        <head>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dashicons/4.6/dashicons.min.css">
        </head>
        <body>
            <button type="button" class="button button-small aips-copy-btn" data-clipboard-text="{{date}}" aria-label="Copy variable">
                <span class="dashicons dashicons-admin-page"></span>
            </button>
            <script>
            // Mock AIPS object and copyToClipboard function from admin.js
            window.AIPS = window.AIPS || {};
            window.AIPS.copyToClipboard = function(e) {
                e.preventDefault();
                var $btn = $(this);
                var text = $btn.data('clipboard-text');
                var $icon = $btn.find('.dashicons');
                var originalIcon = $btn.data('original-icon') || 'dashicons-admin-page';

                if (!$btn.data('original-icon')) {
                    $btn.data('original-icon', originalIcon);
                }

                var fallbackCopy = function() {
                   // Fallback not easily testable in headless without user interaction context sometimes
                   console.log("Fallback copy triggered");
                };

                var showSuccess = function() {
                    $icon.removeClass(originalIcon).addClass('dashicons-yes');
                    setTimeout(function() {
                        $icon.removeClass('dashicons-yes').addClass(originalIcon);
                    }, 2000);
                };

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(showSuccess).catch(fallbackCopy);
                } else {
                    fallbackCopy();
                }
            };

            $(document).on('click', '.aips-copy-btn', window.AIPS.copyToClipboard);
            </script>
        </body>
        </html>
        """

        # We need to serve this or load it. Loading data: uri is easiest for small content.
        # But for clipboard, we often need a real origin or secure context (https or localhost).
        # We'll save it to a file and load it via file:// but file:// often blocks clipboard.
        # However, Playwright's `grant_permissions` might override. Let's try.

        with open("verification/test_copy.html", "w") as f:
            f.write(html_content)

        import os
        file_path = os.path.abspath("verification/test_copy.html")
        page.goto(f"file://{file_path}")

        # Click the button
        page.click('.aips-copy-btn')

        # Verify icon changed
        page.wait_for_selector('.dashicons-yes', state='attached', timeout=5000)

        # Verify clipboard content
        clipboard_text = page.evaluate("navigator.clipboard.readText()")
        assert clipboard_text == "{{date}}"

        print("Test passed: Icon changed and clipboard content verified.")

        browser.close()

if __name__ == "__main__":
    test_copy_button()
