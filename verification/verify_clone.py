
import os
from playwright.sync_api import sync_playwright, expect

def verify_clone():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Load local mock file
        cwd = os.getcwd()
        page.goto(f"file://{cwd}/verification/mock_schedule.html")

        # Click clone
        page.click(".aips-clone-schedule")

        # Verify modal visible
        expect(page.locator("#aips-schedule-modal")).to_be_visible()

        # Verify title
        expect(page.locator("#aips-schedule-modal-title")).to_have_text("Clone Schedule")

        # Verify fields populated
        expect(page.locator("#schedule_template")).to_have_value("5")
        expect(page.locator("#schedule_frequency")).to_have_value("daily")
        expect(page.locator("#schedule_topic")).to_have_value("My Cool Topic")
        expect(page.locator("#article_structure_id")).to_have_value("10")
        expect(page.locator("#rotation_pattern")).to_have_value("sequential")

        # Verify ID is empty (new schedule)
        expect(page.locator("#schedule_id")).to_have_value("")

        # Verify Start Time is empty
        expect(page.locator("#schedule_start_time")).to_have_value("")

        # Screenshot
        page.screenshot(path="verification/clone_verified.png")

        # Verify "Add New" resets title
        page.reload()
        # Click Add New
        page.click(".aips-add-schedule-btn")
        expect(page.locator("#aips-schedule-modal-title")).to_have_text("Add New Schedule")

        browser.close()

if __name__ == "__main__":
    verify_clone()
