from playwright.sync_api import Page, expect, sync_playwright
import os

def test_history_ui(page: Page):
    # Load the mock HTML file
    file_path = os.path.abspath("verification/mock_history.html")
    page.goto(f"file://{file_path}")

    # Assert the "Clear Filters" button is visible and has correct text
    clear_btn = page.get_by_role("link", name="Clear Filters")
    expect(clear_btn).to_be_visible()

    # Assert it has the 'button' class
    expect(clear_btn).to_have_class("button")

    # Take screenshot
    page.screenshot(path="verification/history_ui.png")

if __name__ == "__main__":
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        try:
            test_history_ui(page)
        finally:
            browser.close()
