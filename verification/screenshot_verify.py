from playwright.sync_api import sync_playwright
import os

def run_verification():
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
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dashicons/4.0.0/css/dashicons.min.css">
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; background: #f1f1f1; padding: 20px; }
                .wrap { max-width: 1200px; margin: 0 auto; }
                .aips-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
                .widefat { border-spacing: 0; width: 100%; clear: both; margin: 0; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04); background: #fff; }
                .widefat td, .widefat th { padding: 8px 10px; text-align: left; vertical-align: top; }
                .widefat th { border-bottom: 1px solid #c3c4c7; }
                .widefat td { border-bottom: 1px solid #c3c4c7; }
                .button { display: inline-block; text-decoration: none; font-size: 13px; line-height: 2.15384615; min-height: 30px; margin: 0; padding: 0 10px; cursor: pointer; border-width: 1px; border-style: solid; -webkit-appearance: none; border-radius: 3px; white-space: nowrap; box-sizing: border-box; color: #2271b1; border-color: #2271b1; background: #f6f7f7; vertical-align: top; }
                .button-small { min-height: 24px; line-height: 2; padding: 0 8px; font-size: 11px; }
                .aips-copy-btn .dashicons { font-size: 16px; line-height: 1.5; margin-right: 0; }
                code { font-family: Consolas, Monaco, monospace; background: #f0f0f1; padding: 1px 5px; font-size: 13px; }

                /* This matches the CSS added to admin.css */
                .aips-copy-btn {
                    margin-left: 10px;
                    vertical-align: middle;
                }
            </style>
        </head>
        <body>
            <div class="wrap aips-wrap">
                <div class="aips-card">
                    <h2>Template Variables</h2>
                    <p>You can use these variables in your prompt templates:</p>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>Variable</th>
                                <th>Description</th>
                                <th>Example</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <code>{{date}}</code>
                                    <button type="button" class="button button-small aips-copy-btn" data-clipboard-text="{{date}}" aria-label="Copy variable">
                                        <span class="dashicons dashicons-admin-page"></span>
                                    </button>
                                </td>
                                <td>Current date</td>
                                <td>May 23, 2024</td>
                            </tr>
                            <tr>
                                <td>
                                    <code>{{site_name}}</code>
                                    <button type="button" class="button button-small aips-copy-btn" data-clipboard-text="{{site_name}}" aria-label="Copy variable">
                                        <span class="dashicons dashicons-admin-page"></span>
                                    </button>
                                </td>
                                <td>Site name</td>
                                <td>My Awesome Site</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <script>
            window.AIPS = window.AIPS || {};
            window.AIPS.copyToClipboard = function(e) {
                e.preventDefault();
                var $btn = $(this);
                var text = $btn.data('clipboard-text');
                var originalText = $btn.html();

                if (!text) return;

                navigator.clipboard.writeText(text).then(function() {
                    $btn.text('Copied!');
                    setTimeout(function() {
                        $btn.html(originalText);
                    }, 2000);
                }, function(err) {
                    console.error('Async: Could not copy text: ', err);
                });
            };

            $(document).on('click', '.aips-copy-btn', window.AIPS.copyToClipboard);
            </script>
        </body>
        </html>
        """

        with open("verification/mock_settings_screenshot.html", "w") as f:
            f.write(html_content)

        page.goto("file://" + os.path.abspath("verification/mock_settings_screenshot.html"))

        # Take initial screenshot
        page.screenshot(path="verification/settings_initial.png")

        # Click first copy button
        page.locator(".aips-copy-btn").first.click()

        # Take screenshot of copied state
        page.locator(".aips-copy-btn").first.wait_for(state="visible")
        page.wait_for_timeout(100)

        page.screenshot(path="verification/settings_copied.png")

        browser.close()

if __name__ == "__main__":
    run_verification()
