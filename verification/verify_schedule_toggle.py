from playwright.sync_api import sync_playwright
import os
import sys

def test_schedule_toggle():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        cwd = os.getcwd()
        mock_file_path = os.path.join(cwd, 'verification/mock_schedule.html')
        page.goto(f"file://{mock_file_path}")

        # Inject Mock Ajax
        page.evaluate("""
            window.mockAjaxCalls = [];
            jQuery.ajax = function(options) {
                window.mockAjaxCalls.push(options);
                // Simulate async failure response from server
                setTimeout(function() {
                     if (options.success) {
                         options.success({ success: false, data: { message: "Simulated Error" } });
                     }
                     // If we wanted to simulate network error:
                     // if (options.error) options.error();
                }, 50);
            };
        """)

        print("Page loaded")

        # 1. Verify initial state
        if page.is_checked('#toggle-123'):
            print("Error: Should be unchecked initially")
            sys.exit(1)

        # 2. Click the toggle (Turn ON)
        print("Clicking toggle...")
        page.click('#toggle-123')

        # 3. Wait for "Ajax"
        page.wait_for_timeout(200)

        # 4. Verify state
        # If the backend says "success: false", the UI should revert to unchecked.

        is_checked_after = page.is_checked('#toggle-123')

        if is_checked_after:
            print("Error: Checkbox remained checked despite server error (Bug Present)")
            page.screenshot(path="verification/verification_failed.png")
            sys.exit(1)
        else:
            print("Success: Checkbox reverted to unchecked state")
            page.screenshot(path="verification/verification_passed.png")
            sys.exit(0)

        browser.close()

if __name__ == "__main__":
    test_schedule_toggle()
