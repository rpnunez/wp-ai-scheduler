
import os
from playwright.sync_api import sync_playwright

def verify_copy_button():
    with sync_playwright() as p:
        browser = p.chromium.launch()
        context = browser.new_context()
        context.grant_permissions(['clipboard-read', 'clipboard-write'])
        page = context.new_page()

        # Load the mock HTML file
        file_path = os.path.abspath("verification/mock_status.html")
        page.goto(f"file://{file_path}")

        # Screenshot before click
        page.screenshot(path="verification/before_click.png")

        # Click the copy button
        page.click('.aips-copy-log')

        # Wait for the text to change to "Copied!"
        page.wait_for_selector('.aips-copy-log:has-text("Copied!")')

        # Screenshot after click (showing feedback)
        page.screenshot(path="verification/after_click.png")

        browser.close()

if __name__ == "__main__":
    verify_copy_button()
