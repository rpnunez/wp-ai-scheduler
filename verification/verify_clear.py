from playwright.sync_api import sync_playwright, expect
import os

def test_clear_topics():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Load the mock file using absolute path
        file_path = os.path.abspath("verification/mock_planner.html")
        page.goto(f"file://{file_path}")

        # Verify initial state
        expect(page.locator("#topics-list")).not_to_be_empty()

        # Click Clear List
        page.click("#btn-clear-topics")

        # Verify button text changes to "Cleared!"
        expect(page.locator("#btn-clear-topics")).to_have_text("Cleared!")

        # Take screenshot
        page.screenshot(path="verification/verification.png")

        # Verify list is empty
        expect(page.locator("#topics-list")).to_be_empty()

        # Verify check-all is unchecked
        expect(page.locator("#check-all-topics")).not_to_be_checked()

        browser.close()

if __name__ == "__main__":
    test_clear_topics()
