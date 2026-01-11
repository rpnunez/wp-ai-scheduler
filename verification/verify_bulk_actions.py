import os
from playwright.sync_api import sync_playwright, expect

def verify_bulk_actions():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Load mock HTML
        file_path = os.path.abspath("verification/mock_schedule.html")
        page.goto(f"file://{file_path}")

        # Inject our admin.js
        with open("ai-post-scheduler/assets/js/admin.js", "r") as f:
            js_content = f.read()
            page.add_script_tag(content=js_content)

        # Verify initial state
        delete_btn = page.locator("#aips-delete-selected-schedules-btn")
        expect(delete_btn).to_be_disabled()

        # Test 1: Select one item
        page.check("#cb-select-101")
        expect(delete_btn).to_be_enabled()

        # Test 2: Unselect it
        page.uncheck("#cb-select-101")
        expect(delete_btn).to_be_disabled()

        # Test 3: Select All
        page.check("#cb-select-all-1")
        # Verify all checkboxes are checked
        expect(page.locator("#cb-select-101")).to_be_checked()
        expect(page.locator("#cb-select-102")).to_be_checked()
        expect(page.locator("#cb-select-103")).to_be_checked()
        expect(delete_btn).to_be_enabled()

        # Take screenshot of enabled state
        page.screenshot(path="verification/schedule_bulk_actions.png")

        # Test 4: Mock AJAX call for deletion
        page.evaluate("""
            window.confirm = function() { return true; };
            // Simple mock that checks if called correctly
            $.ajax = function(options) {
                console.log('AJAX call:', options);
                // Check if options and data exist
                if (options && options.data && options.data.action === 'aips_delete_schedule_bulk') {
                    // Simulate success
                    if (options.success) {
                        try {
                            // Try to hijack reload
                            window.location.reload = function() { console.log('Reload hijacked'); };
                        } catch(e) {
                            console.log('Could not hijack reload');
                        }
                        options.success({ success: true, data: { message: 'Deleted', count: 3 } });
                    }
                }
            };
        """)

        # Click delete
        delete_btn.click()

        print("Bulk actions verification passed visually.")

        browser.close()

if __name__ == "__main__":
    verify_bulk_actions()
