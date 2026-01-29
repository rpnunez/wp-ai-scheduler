
from playwright.sync_api import sync_playwright, expect
import os

def run_verification():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)

        # Test Schedule
        page = browser.new_page()
        file_path = os.path.abspath("verification/mock_schedule.html")
        page.goto(f"file://{file_path}")
        dashicon = page.locator(".dashicons")
        expect(dashicon).to_have_attribute("aria-hidden", "true")
        print("Verification passed: Schedule Dashicon has aria-hidden='true'")
        page.screenshot(path="verification/verification_schedule.png")
        page.close()

        # Test Generated Posts
        page = browser.new_page()
        file_path = os.path.abspath("verification/mock_generated_posts.html")
        page.goto(f"file://{file_path}")
        close_icon = page.locator(".dashicons-no")
        expect(close_icon).to_have_attribute("aria-hidden", "true")
        print("Verification passed: Modal Close Dashicon has aria-hidden='true'")
        page.screenshot(path="verification/verification_generated_posts.png")
        page.close()

        browser.close()

if __name__ == "__main__":
    run_verification()
