from playwright.sync_api import sync_playwright
import os

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Load local HTML file
        url = f"file://{os.getcwd()}/verification/mock_voices.html"
        page.goto(url)

        # Check if Clone button exists with correct class
        clone_btn = page.locator(".aips-clone-voice")

        # Assert it's visible and text is correct
        if clone_btn.is_visible():
            print("SUCCESS: Clone button is visible.")
            if clone_btn.inner_text().strip() == "Clone":
                print("SUCCESS: Clone button text is correct.")
            else:
                print(f"FAILURE: Clone button text is '{clone_btn.inner_text()}'")
        else:
            print("FAILURE: Clone button is not visible.")

        # Take screenshot
        page.screenshot(path="verification/verification.png")
        print("Screenshot saved to verification/verification.png")

        browser.close()

if __name__ == "__main__":
    run()
