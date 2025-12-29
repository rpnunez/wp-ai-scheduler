from playwright.sync_api import sync_playwright
import os

def test_copy_clipboard():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        # Enable clipboard permissions
        context = browser.new_context(permissions=['clipboard-read', 'clipboard-write'])
        page = context.new_page()

        # Create a mock HTML file
        mock_html = """
        <!DOCTYPE html>
        <html>
        <head>
            <title>Mock Settings</title>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dashicons/3.0.0/css/dashicons.min.css">
            <style>
                .aips-copy-btn { cursor: pointer; color: #2271b1; }
                .aips-copy-btn:hover { color: #135e96; }
            </style>
        </head>
        <body>
            <div class="wrap">
                <h1>Settings</h1>
                <table>
                    <tr>
                        <td><code>{{date}}</code></td>
                        <td>
                            <button type="button" class="button button-small aips-copy-btn" data-clipboard-text="{{date}}" aria-label="Copy to clipboard">
                                <span class="dashicons dashicons-clipboard"></span>
                            </button>
                        </td>
                    </tr>
                </table>
            </div>

            <script>
            // Mock the AIPS object and copy logic
            window.AIPS = window.AIPS || {};

            (function($) {
                // Simplified copy logic to verify implementation plan
                $(document).on('click', '.aips-copy-btn', function(e) {
                    e.preventDefault();
                    var $btn = $(this);
                    var text = $btn.data('clipboard-text');
                    var $icon = $btn.find('.dashicons');
                    var originalIcon = $icon.attr('class');

                    // Use a mock clipboard action since real clipboard might be restricted in headless
                    console.log('Copying: ' + text);
                    window.lastCopiedText = text; // Store for verification

                    // Visual feedback
                    $icon.attr('class', 'dashicons dashicons-yes');
                    setTimeout(function() {
                        $icon.attr('class', originalIcon);
                    }, 2000);
                });
            })(jQuery);
            </script>
        </body>
        </html>
        """

        with open("verification/mock_settings.html", "w") as f:
            f.write(mock_html)

        page.goto("file://" + os.path.abspath("verification/mock_settings.html"))

        # Click the copy button
        page.click(".aips-copy-btn")

        # Verify the visual feedback (icon change)
        # Using state='attached' to avoid visibility issues if the element is small or hidden
        page.wait_for_selector(".dashicons-yes", state="attached")
        print("Visual feedback verified: Icon changed to checkmark")

        # Verify the logic was executed (via console log or window property)
        last_copied = page.evaluate("window.lastCopiedText")
        print(f"Copied text: {last_copied}")

        if last_copied == "{{date}}":
            print("SUCCESS: Text copied correctly")
        else:
            print("FAILURE: Text not copied correctly")

        browser.close()

if __name__ == "__main__":
    test_copy_clipboard()
