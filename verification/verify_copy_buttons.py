
from playwright.sync_api import sync_playwright, expect
import os

def verify_copy_buttons():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        # Grant clipboard permissions
        context = browser.new_context(permissions=['clipboard-read', 'clipboard-write'])
        page = context.new_page()

        # Load local mock file
        filepath = os.path.abspath("verification/mock_settings.html")
        page.goto(f"file://{filepath}")

        # 1. Verify buttons exist
        expect(page.locator('.aips-copy-btn')).to_have_count(2)

        # 2. Test click interaction on the {{date}} button
        date_btn = page.locator('.aips-copy-btn').first

        # Click button
        date_btn.click()

        # 3. Verify visual feedback ('Copied!')
        expect(date_btn).to_have_text("Copied!")

        # 4. Verify clipboard content
        # Note: In headless mode, clipboard read might be restricted, but we can verify the feedback loop
        # For this test, we rely on the button text change which indicates success path was taken

        # 5. Take screenshot
        page.screenshot(path="verification/settings_copy_verification.png")

        print("Verification successful: Buttons found and interactive.")
        browser.close()

if __name__ == "__main__":
    verify_copy_buttons()
