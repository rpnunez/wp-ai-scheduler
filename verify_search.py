from playwright.sync_api import sync_playwright, expect
import os

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    page = browser.new_page()

    # Load the static mock file
    file_path = os.path.abspath("mock_history_static.html")
    page.goto(f"file://{file_path}")

    # Check if search box exists
    search_input = page.locator("#aips-history-search-input")
    expect(search_input).to_be_visible()

    # Check if search button exists
    search_btn = page.locator("#aips-history-search-btn")
    expect(search_btn).to_be_visible()

    # Take screenshot
    if not os.path.exists("/home/jules/verification"):
        os.makedirs("/home/jules/verification")
    page.screenshot(path="/home/jules/verification/history_search.png")

    print("Verification successful, screenshot saved.")
    browser.close()

with sync_playwright() as playwright:
    run(playwright)
