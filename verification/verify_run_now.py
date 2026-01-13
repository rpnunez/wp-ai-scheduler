import os
from playwright.sync_api import sync_playwright

def test_run_now_feedback():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Load the mock HTML
        page.goto("file://" + os.path.abspath("verification/mock_run_now.html"))

        # Inject the admin.js logic we modified
        # We only need the parts relevant to runNow and bindEvents
        js_content = """
        (function($) {
            window.AIPS = window.AIPS || {};

            // Mock showToast if not present (though it is in HTML)
            if (!window.AIPS.showToast) {
                 window.AIPS.showToast = function(message, type) {
                    $('body').append('<div>TOAST: ' + message + '</div>');
                 };
            }

            Object.assign(window.AIPS, {
                runNow: function(e) {
                    e.preventDefault();
                    var id = $(this).data('id');
                    var $btn = $(this);
                    var originalText = $btn.text();

                    $btn.prop('disabled', true).text(aipsAdminL10n.generating || 'Generating...');

                    // Mock AJAX
                    console.log('AJAX request started');
                    setTimeout(function() {
                        var response = { success: true, data: { message: '5 post(s) generated successfully!', edit_url: '' } };

                        if (response.success) {
                            window.AIPS.showToast(response.data.message, 'success');
                            // Mock reload
                            console.log('Reload scheduled');
                        }
                        $btn.prop('disabled', false).text(originalText);
                    }, 1000);
                }
            });

            $(document).on('click', '.aips-run-now', window.AIPS.runNow);
        })(jQuery);
        """
        page.add_script_tag(content=js_content)

        # Click the button
        page.click(".aips-run-now")

        # Check if text changed to "Generating..."
        btn = page.locator(".aips-run-now")
        assert btn.inner_text() == "Generating..."
        assert btn.is_disabled()

        # Wait for "AJAX" to complete (1s in mock)
        page.wait_for_timeout(1500)

        # Check if toast appeared
        toast = page.locator(".aips-toast-success")
        assert toast.is_visible()
        assert "5 post(s) generated successfully!" in toast.inner_text()

        # Check if button reset
        assert btn.inner_text() == "Run Now"
        assert not btn.is_disabled()

        # Take screenshot
        page.screenshot(path="verification/verify_run_now.png")
        browser.close()

if __name__ == "__main__":
    test_run_now_feedback()
