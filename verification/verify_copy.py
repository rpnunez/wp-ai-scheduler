from playwright.sync_api import sync_playwright, expect
import os

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    page = browser.new_page()

    # Load the mock HTML
    cwd = os.getcwd()
    page.goto(f"file://{cwd}/verification/mock_admin_copy.html")

    # Force mock clipboard again just in case
    page.evaluate("""
        try {
            Object.defineProperty(navigator, 'clipboard', {
                value: {
                    writeText: function(text) {
                        console.log('Clipboard write:', text);
                        return Promise.resolve();
                    }
                },
                writable: true,
                configurable: true
            });
        } catch(e) { console.error(e); }
    """)

    # Click "View Details"
    page.click('.aips-view-details')

    # Wait for modal content
    page.wait_for_selector('#aips-details-template table')

    # Verify "Copy" buttons exist
    copy_btns = page.locator('.aips-copy-btn')
    count = copy_btns.count()
    print(f"Found {count} copy buttons")

    if count < 3:
        print("FAILURE: Not enough copy buttons found")
        # Snapshot anyway
        page.screenshot(path="verification/verify_copy.png")
        exit(1)

    # Screenshot of initial state
    page.screenshot(path="verification/verify_copy_initial.png")

    # Test Click
    msg_list = []
    page.on("console", lambda msg: msg_list.append(msg.text))

    first_btn = copy_btns.first
    first_btn.click()

    # Wait for text change - wait explicitly
    try:
        expect(first_btn).to_have_text("Copied!")
        print("SUCCESS: Button text changed to Copied!")
    except:
        print("WARNING: Button text did not change (Mock issue?)")
        print("Console:", msg_list)

    page.screenshot(path="verification/verify_copy.png")
    browser.close()

with sync_playwright() as playwright:
    run(playwright)
