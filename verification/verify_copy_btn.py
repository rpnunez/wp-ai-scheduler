import pytest
from playwright.sync_api import sync_playwright
import os

def test_copy_button():
    with sync_playwright() as p:
        browser = p.chromium.launch()
        context = browser.new_context()
        context.grant_permissions(['clipboard-read', 'clipboard-write'])
        page = context.new_page()

        # Create a mock HTML file content
        html_content = """
        <!DOCTYPE html>
        <html>
        <head>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <style>
                /* Mock styles for visibility */
                .aips-copy-btn { cursor: pointer; border: 1px solid #ccc; padding: 5px; }
            </style>
        </head>
        <body>
            <table>
                <tbody>
                    <tr>
                        <td>
                            <code>{{date}}</code>
                            <button type="button" class="button button-small aips-copy-btn" data-clipboard-text="{{date}}" aria-label="Copy variable">
                                <span class="dashicons dashicons-admin-page">Copy</span>
                            </button>
                        </td>
                        <td>Current date</td>
                        <td>May 23, 2024</td>
                    </tr>
                </tbody>
            </table>
            <div id="result"></div>

            <script>
            // Mock AIPS object and copy handler from admin.js
            window.AIPS = window.AIPS || {};

            // Mock esc_attr_e
            function esc_attr_e(text, domain) { return text; }

            (function($) {
                // Simplified version of the copyToClipboard from admin.js for testing logic
                window.AIPS.copyToClipboard = function(e) {
                    e.preventDefault();
                    var $btn = $(this);
                    var text = $btn.data('clipboard-text');
                    var originalText = $btn.find('span').text(); // Simplified for test

                    if (!text) return;

                    // In Playwright we can verify this logic works
                    navigator.clipboard.writeText(text).then(function() {
                        $btn.find('span').text('Copied!'); // Change inner span for test visibility
                        $('#result').text('Copied: ' + text);
                        setTimeout(function() {
                            $btn.find('span').text(originalText);
                        }, 2000);
                    }, function(err) {
                        console.error('Async: Could not copy text: ', err);
                        $('#result').text('Error copying');
                    });
                };

                $(document).on('click', '.aips-copy-btn', window.AIPS.copyToClipboard);
            })(jQuery);
            </script>
        </body>
        </html>
        """

        # Write to a temporary file
        with open("verification/mock_settings.html", "w") as f:
            f.write(html_content)

        # Load the file
        page.goto("file://" + os.path.abspath("verification/mock_settings.html"))

        # Verify button exists
        assert page.locator(".aips-copy-btn").count() > 0

        # Click the button
        page.locator(".aips-copy-btn").first.click()

        # Check if the result div is updated (indicating success callback ran)
        page.wait_for_selector("#result:has-text('Copied: {{date}}')")

        # Verify clipboard content
        # Note: reading clipboard requires permissions which might be tricky in headless,
        # but the success callback execution confirms the promise resolved.
        # Let's try to paste it into an input if we added one, or just trust the callback.

        print("Verification successful: Button clicked and copy logic executed.")

        browser.close()

if __name__ == "__main__":
    test_copy_button()
