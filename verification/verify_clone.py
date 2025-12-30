from playwright.sync_api import sync_playwright, expect
import os

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Load the mock HTML file
        url = "file://" + os.path.abspath("verification/mock_schedule.html")
        page.goto(url)

        # Verify Clone button exists
        clone_btn = page.locator(".aips-clone-schedule")
        expect(clone_btn).to_be_visible()

        # Click Clone
        clone_btn.click()

        # Verify Modal opens
        modal = page.locator("#aips-schedule-modal")
        expect(modal).to_be_visible()

        # Verify Title changes to "Clone Schedule"
        expect(page.locator("#aips-schedule-modal h2")).to_have_text("Clone Schedule")

        # Verify Topic has "(Copy)" appended
        expect(page.locator("#schedule_topic")).to_have_value("Tech News (Copy)")

        # Take screenshot
        page.screenshot(path="verification/verify_clone_schedule.png")

        browser.close()

if __name__ == "__main__":
    run()
