from playwright.sync_api import sync_playwright, expect
import os

def test_voices_ui():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Load the mock HTML file
        file_path = os.path.abspath("verification/mock_voices.html")
        page.goto(f"file://{file_path}")

        # Verify "Copy ID" button exists
        copy_btn = page.locator(".aips-copy-btn").first
        expect(copy_btn).to_be_visible()
        expect(copy_btn).to_have_text("1")

        # Verify "Clone" button exists
        clone_btn = page.locator(".aips-clone-voice").first
        expect(clone_btn).to_be_visible()
        expect(clone_btn).to_have_text("Clone")

        # Click "Clone" and verify modal opens with populated data
        clone_btn.click()

        modal = page.locator("#aips-voice-modal")
        expect(modal).to_be_visible()

        modal_title = page.locator("#aips-voice-modal-title")
        expect(modal_title).to_have_text("Clone Voice")

        voice_name = page.locator("#voice_name")
        expect(voice_name).to_have_value("Professional (Copy)")

        # Take screenshot
        page.screenshot(path="verification/voices_ui_verification.png")
        print("Verification successful! Screenshot saved to verification/voices_ui_verification.png")

        browser.close()

if __name__ == "__main__":
    test_voices_ui()
