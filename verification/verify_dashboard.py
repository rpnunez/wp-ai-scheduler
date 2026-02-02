from playwright.sync_api import sync_playwright
import os

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()

        # Load the mock HTML file
        file_path = os.path.abspath("verification/mock_dashboard.html")
        page.goto(f"file://{file_path}")

        # Verify the "Run Now" buttons exist
        run_buttons = page.locator(".aips-run-schedule-btn")
        count = run_buttons.count()
        print(f"Found {count} Run Now buttons")

        if count != 2:
            print("Error: Expected 2 Run Now buttons")
            exit(1)

        # Take screenshot
        page.screenshot(path="verification/dashboard_verification.png")
        print("Screenshot saved to verification/dashboard_verification.png")

        browser.close()

if __name__ == "__main__":
    run()
