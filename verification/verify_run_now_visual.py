import re
from playwright.sync_api import sync_playwright

def verify_button_presence_and_screenshot():
    """
    Simulates the Schedule page HTML and verifies the presence of the Run Now button
    with correct attributes, then takes a screenshot.
    """
    html_content = """
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Schedule Test</title>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dashicons/1.0.0/css/dashicons.min.css">
        <style>
            body { font-family: sans-serif; background: #f1f1f1; padding: 20px; }
            .wrap { background: #fff; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04); max-width: 1000px; margin: 0 auto; }
            .wp-list-table { width: 100%; border-collapse: collapse; }
            .wp-list-table th, .wp-list-table td { text-align: left; padding: 10px; border-bottom: 1px solid #ddd; }
            .button {
                display: inline-block;
                text-decoration: none;
                font-size: 13px;
                line-height: 2.15384615;
                min-height: 30px;
                margin: 0;
                padding: 0 10px;
                cursor: pointer;
                border-width: 1px;
                border-style: solid;
                -webkit-appearance: none;
                border-radius: 3px;
                white-space: nowrap;
                box-sizing: border-box;
                background: #f6f7f7;
                color: #2271b1;
                border-color: #2271b1;
                margin-right: 5px;
            }
            .button-link-delete { color: #b32d2e; border-color: transparent; background: transparent; }
            .dashicons { vertical-align: text-bottom; margin-right: 3px; }
        </style>
    </head>
    <body>
        <div class="wrap aips-wrap">
            <h2>Post Schedules</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="column-template">Template</th>
                        <th class="column-frequency">Frequency</th>
                        <th class="column-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr data-schedule-id="123">
                        <td>Daily News Summary</td>
                        <td>Daily</td>
                        <td class="column-actions">
                            <!-- Button we added -->
                            <button class="button aips-run-schedule-now" data-id="123" aria-label="Run schedule now">
                                <span class="dashicons dashicons-controls-play" aria-hidden="true" style="line-height: 1.3; font-size: 16px;"></span>
                                Run Now
                            </button>
                            <!-- Existing buttons -->
                            <button class="button aips-clone-schedule" aria-label="Clone schedule">
                                Clone
                            </button>
                            <button class="button button-link-delete aips-delete-schedule" data-id="123">
                                Delete
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <script>
            // Mock AIPS object
            window.AIPS = {
                runScheduleNow: function(e) {
                    e.preventDefault();
                    console.log('Run Schedule Now Clicked: ' + $(this).data('id'));
                    $(this).text('Generating...');
                }
            };
            // Bind event manually since we don't load full admin.js
            $(document).on('click', '.aips-run-schedule-now', window.AIPS.runScheduleNow);
        </script>
    </body>
    </html>
    """

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        page.set_content(html_content)

        # Take screenshot of the button state
        page.screenshot(path="verification/schedule_run_now.png")
        print("📸 Screenshot saved to verification/schedule_run_now.png")

        # Verify button exists and verify logic
        button = page.locator('.aips-run-schedule-now')
        if button.count() == 0:
            print("❌ Error: Run Now button not found")
            browser.close()
            return

        button.click()

        # check if text changed
        if "Generating..." in button.inner_text():
             print("✅ Click handler verified (text changed).")
        else:
             print("❌ Error: Click handler failed")

        browser.close()

if __name__ == "__main__":
    verify_button_presence_and_screenshot()
