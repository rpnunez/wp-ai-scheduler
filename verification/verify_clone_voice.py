import pytest
from playwright.sync_api import sync_playwright, Page
import os

def test_clone_voice():
    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()

        # Listen for console logs
        page.on("console", lambda msg: print(f"Console: {msg.text}"))

        # Listen for page errors
        page.on("pageerror", lambda exc: print(f"Page Error: {exc}"))

        # Load the mock HTML file
        file_path = os.path.abspath("verification/verify_clone_voice.html")
        page.goto(f"file://{file_path}")

        # Verify initial state
        assert page.is_hidden("#aips-voice-modal")

        # Mock AJAX response
        page.route("**/wp-admin/admin-ajax.php", lambda route: route.fulfill(
            status=200,
            content_type="application/json",
            body='{"success": true, "data": {"voice": {"id": "123", "name": "Test Voice", "title_prompt": "Prompt", "content_instructions": "Content", "excerpt_instructions": "Excerpt", "is_active": 1}}}'
        ))

        # Click Clone Button
        print("Clicking clone button...")
        page.click(".aips-clone-voice")

        # Verify Modal Opens
        try:
            page.wait_for_selector("#aips-voice-modal", state="visible", timeout=5000)
            print("Modal appeared.")
        except Exception as e:
            print("Modal did NOT appear.")
            print(f"Modal visibility: {page.is_visible('#aips-voice-modal')}")

        assert page.is_visible("#aips-voice-modal")

        # Verify Modal Title
        title = page.inner_text("#aips-voice-modal-title")
        assert title == "Clone Voice"

        # Verify Form Data (Name should have (Copy) appended)
        name_val = page.eval_on_selector("#voice_name", "el => el.value")
        assert name_val == "Test Voice (Copy)"

        # Verify ID is empty (treated as new)
        id_val = page.eval_on_selector("#voice_id", "el => el.value")
        assert id_val == ""

        # Verify other fields populated
        assert page.eval_on_selector("#voice_title_prompt", "el => el.value") == "Prompt"

        # Take screenshot
        page.screenshot(path="verification/clone_voice_success.png")

        print("Verification Successful: Clone Voice logic works as expected.")
        browser.close()

if __name__ == "__main__":
    test_clone_voice()
