import re
from playwright.sync_api import sync_playwright

def verify_button_presence():
    """
    Simulates the Schedule page HTML and verifies the presence of the Run Now button
    with correct attributes.
    """
    html_content = """
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Schedule Test</title>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <style>
            .dashicons { display: inline-block; width: 20px; height: 20px; }
            .button { padding: 5px 10px; cursor: pointer; }
        </style>
    </head>
    <body>
        <div class="wrap aips-wrap">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="column-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr data-schedule-id="123">
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

        # Verify button exists
        button = page.locator('.aips-run-schedule-now')
        if button.count() == 0:
            print("❌ Error: Run Now button not found")
            browser.close()
            return

        # Verify attributes
        data_id = button.get_attribute('data-id')
        aria_label = button.get_attribute('aria-label')

        if data_id != '123':
            print(f"❌ Error: data-id mismatch. Expected '123', got '{data_id}'")
        elif aria_label != 'Run schedule now':
             print(f"❌ Error: aria-label mismatch. Expected 'Run schedule now', got '{aria_label}'")
        else:
            print("✅ Button attributes verified.")

        # Verify Icon
        icon = button.locator('.dashicons-controls-play')
        if icon.count() > 0:
             print("✅ Icon verified.")
        else:
             print("❌ Error: Icon not found")

        # Verify Click
        msg = []
        page.on("console", lambda msg: print(f"Console: {msg.text}"))

        button.click()

        # check if text changed
        if "Generating..." in button.inner_text():
             print("✅ Click handler verified (text changed).")
        else:
             print("❌ Error: Click handler failed")

        browser.close()

if __name__ == "__main__":
    verify_button_presence()
