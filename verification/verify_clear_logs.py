
from playwright.sync_api import Page, expect, sync_playwright
import os

def test_clear_logs_button(page: Page):
    # Load the mock HTML file
    cwd = os.getcwd()
    file_path = f"file://{cwd}/verification/mock_system_status.html"
    page.goto(file_path)

    # Check if the "Clear Logs" button is visible
    clear_button = page.locator('.aips-clear-logs')
    expect(clear_button).to_be_visible()
    expect(clear_button).to_have_text("Clear Logs")

    # Handle dialog (alert/confirm)
    page.on("dialog", lambda dialog: dialog.accept())

    # Click the button
    clear_button.click()

    # Check if disabled state is applied (simulating the JS logic)
    # The mock script adds text 'Clearing...'
    expect(clear_button).to_have_text("Clearing...")
    expect(clear_button).to_be_disabled()

    # Wait for the "AJAX" to complete (mocked timeout)
    page.wait_for_timeout(600)

    # Should be enabled again
    expect(clear_button).to_be_enabled()
    expect(clear_button).to_have_text("Clear Logs")

    # Take screenshot
    page.screenshot(path="verification/verification.png")

if __name__ == "__main__":
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        try:
            test_clear_logs_button(page)
            print("Verification script ran successfully.")
        except Exception as e:
            print(f"Verification failed: {e}")
        finally:
            browser.close()
