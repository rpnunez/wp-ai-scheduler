from playwright.sync_api import sync_playwright, expect
import os

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Load the mock file
        filepath = os.path.abspath("verification/mock_schedule.html")
        page.goto(f"file://{filepath}")

        # Verify "Select All" works
        select_all = page.locator("#cb-select-all-1")
        checkbox_1 = page.locator("#cb-select-1")
        checkbox_2 = page.locator("#cb-select-2")

        # Click Select All
        select_all.check()

        # Check if individual checkboxes are checked
        expect(checkbox_1).to_be_checked()
        expect(checkbox_2).to_be_checked()

        # Uncheck one
        checkbox_1.uncheck()

        # Select All should be unchecked
        expect(select_all).not_to_be_checked()

        # Check it back
        checkbox_1.check()
        expect(select_all).to_be_checked()

        # Take screenshot
        page.screenshot(path="verification/verify_schedule_bulk.png")
        print("Verification successful, screenshot saved.")

        browser.close()

if __name__ == "__main__":
    run()
