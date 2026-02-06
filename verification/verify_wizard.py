
import os
from playwright.sync_api import sync_playwright

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Load local HTML file
        file_path = os.path.abspath("verification/verify_wizard_js.html")
        page.goto(f"file://{file_path}")

        # Click the button
        page.click(".aips-view-details")

        # Wait for modal content to appear
        page.wait_for_selector("#aips-details-content", state="visible")

        # Wait for generated content to be visible
        page.wait_for_selector("text=Generated Content")

        # Take screenshot
        page.screenshot(path="verification/wizard_verification.png")

        browser.close()

if __name__ == "__main__":
    run()
