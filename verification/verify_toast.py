from playwright.sync_api import sync_playwright
import os

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    page = browser.new_page()

    # Load the mock HTML file
    file_path = os.path.abspath("verification/mock_toast.html")
    page.goto(f"file://{file_path}")

    # Trigger Success Toast
    page.click("text=Show Success")
    page.wait_for_selector(".aips-toast-success")
    page.screenshot(path="verification/toast_success.png")

    # Trigger Error Toast
    page.click("text=Show Error")
    page.wait_for_selector(".aips-toast-error")
    page.screenshot(path="verification/toast_error.png")

    browser.close()

with sync_playwright() as playwright:
    run(playwright)
