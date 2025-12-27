from playwright.sync_api import sync_playwright
import os

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    page = browser.new_page()

    # Load the mock HTML file
    file_path = os.path.abspath("mock_planner.html")
    page.goto(f"file://{file_path}")

    # Mock window.confirm to return True
    page.evaluate("window.confirm = function() { return true; }")

    # Click the "Clear List" button
    page.click("#btn-clear-topics")

    # Check if the button text changes to "Cleared!"
    btn = page.locator("#btn-clear-topics")
    if btn.inner_text() == "Cleared!":
        print("SUCCESS: Button text changed to 'Cleared!'")
    else:
        print(f"FAILURE: Button text is '{btn.inner_text()}'")

    # Take a screenshot
    page.screenshot(path="verification_planner.png")

    browser.close()

with sync_playwright() as playwright:
    run(playwright)
