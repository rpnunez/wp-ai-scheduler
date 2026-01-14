from playwright.sync_api import Page, expect, sync_playwright
import os

def test_run_now_button(page: Page):
    # Load the mock HTML file
    cwd = os.getcwd()
    page.goto(f"file://{cwd}/verification/mock_schedule.html")

    # Find the "Run Now" button
    run_now_btn = page.locator(".aips-run-now")

    # Assert it exists and is visible
    expect(run_now_btn).to_be_visible()

    # Assert it has the correct attributes
    expect(run_now_btn).to_have_attribute("data-id", "123")
    expect(run_now_btn).to_have_attribute("data-type", "schedule")

    # Take a screenshot
    page.screenshot(path="verification/verify_run_now_button.png")

if __name__ == "__main__":
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        try:
            test_run_now_button(page)
        finally:
            browser.close()
