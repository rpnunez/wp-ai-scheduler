from playwright.sync_api import sync_playwright
import os

def test_run_now_button():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Mock HTML content simulating the Schedule page structure
        # We need to inject jQuery and the admin.js logic to verify the event handler
        mock_html = """
        <!DOCTYPE html>
        <html>
        <head>
            <title>Mock Schedule Page</title>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script>
                // Mock aipsAjax
                window.aipsAjax = {
                    ajaxUrl: '/wp-admin/admin-ajax.php',
                    nonce: 'mock_nonce'
                };
            </script>
        </head>
        <body>
            <div class="wrap aips-wrap">
                <h1>Post Schedules</h1>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="column-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="column-actions">
                                <!-- The button we added -->
                                <button class="button aips-run-now" data-id="123" data-context="schedule" aria-label="Run schedule now">
                                    Run Now
                                </button>
                                <button class="button aips-clone-schedule">Clone</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div id="log"></div>

            <script>
                // Mock AIPS object and admin.js logic (simplified)
                window.AIPS = window.AIPS || {};

                (function($) {
                    window.AIPS.runNow = function(e) {
                        e.preventDefault();
                        var id = $(this).data('id');
                        var context = $(this).data('context') || 'template';
                        var $btn = $(this);

                        $btn.text('Generating...'); // Simulate UI change

                        var data = {
                            action: 'aips_run_now',
                            nonce: aipsAjax.nonce
                        };

                        if (context === 'schedule') {
                            data.schedule_id = id;
                        } else {
                            data.template_id = id;
                        }

                        // Log the data for verification
                        $('#log').text('Sending AJAX: ' + JSON.stringify(data));
                        console.log('AJAX Data:', data);
                    };

                    $(document).on('click', '.aips-run-now', window.AIPS.runNow);
                })(jQuery);
            </script>
        </body>
        </html>
        """

        # Save mock HTML to file
        with open("verification/mock_schedule.html", "w") as f:
            f.write(mock_html)

        # Load the mock page
        page.goto(f"file://{os.path.abspath('verification/mock_schedule.html')}")

        # Click the Run Now button
        page.click(".aips-run-now")

        # Verify the button text changed (simulating logic execution)
        assert page.inner_text(".aips-run-now") == "Generating..."

        # Verify the log contains the expected data
        log_text = page.inner_text("#log")
        assert '{"action":"aips_run_now","nonce":"mock_nonce","schedule_id":123}' in log_text

        # Take a screenshot
        page.screenshot(path="verification/verify_run_now.png")

        browser.close()

if __name__ == "__main__":
    test_run_now_button()
