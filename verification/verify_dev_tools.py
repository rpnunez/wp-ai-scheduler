from playwright.sync_api import sync_playwright
import os

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    page = browser.new_page()

    # Load the mock file
    cwd = os.getcwd()
    file_path = f"file://{cwd}/verification/mock_dev_tools.html"
    page.goto(file_path)

    # Check title
    assert "Mock Dev Tools" in page.title()

    # Fill topic
    page.fill("#topic", "Hydroponics")

    # Check checkboxes
    page.check("#include_voice")

    # Click Generate
    page.click("#aips-generate-scaffold-btn")

    # Wait for success message
    page.wait_for_selector("#aips-dev-output", state="visible")

    # Verify content
    success_text = page.inner_text("#aips-dev-output-message")
    assert "Scaffold generated successfully" in success_text

    # Take screenshot
    page.screenshot(path="verification/dev_tools_verification.png")

    browser.close()

with sync_playwright() as playwright:
    run(playwright)
