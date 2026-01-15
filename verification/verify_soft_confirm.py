from playwright.sync_api import sync_playwright
import os

def test_soft_confirm():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Determine the absolute path to the mock HTML file
        cwd = os.getcwd()
        filepath = f"file://{cwd}/verification/mock_soft_confirm.html"

        print(f"Navigating to: {filepath}")
        page.goto(filepath)

        # 1. Click delete template first time
        print("Clicking delete template (1st click)...")
        page.click('#delete-template')

        # 2. Check text changed
        btn = page.locator('#delete-template')
        text = btn.text_content()
        print(f"Button text: {text}")
        if "Click again" not in text:
            print("ERROR: Text did not change on first click")
        else:
            print("SUCCESS: Text changed to confirm message")

        # Screenshot state 1
        page.screenshot(path="verification/soft_confirm_step1.png")

        # 3. Click again to confirm
        print("Clicking delete template (2nd click)...")
        page.click('#delete-template')

        text = btn.text_content()
        print(f"Button text: {text}")
        if "Deleted" not in text:
            print("ERROR: Action did not fire on second click")
        else:
            print("SUCCESS: Action fired")

        # Screenshot state 2
        page.screenshot(path="verification/soft_confirm_step2.png")

        browser.close()

if __name__ == "__main__":
    test_soft_confirm()
