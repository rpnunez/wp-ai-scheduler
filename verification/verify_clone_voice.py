import os
from playwright.sync_api import sync_playwright, expect

# Define the path to the JS file to read it content
ADMIN_JS_PATH = "ai-post-scheduler/assets/js/admin.js"

def get_admin_js_content():
    with open(ADMIN_JS_PATH, "r") as f:
        return f.read()

def test_clone_voice():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Enable console logging
        page.on("console", lambda msg: print(f"PAGE LOG: {msg.text}"))
        page.on("pageerror", lambda err: print(f"PAGE ERROR: {err}"))

        # Load the mock HTML file
        mock_file_path = os.path.abspath("verification/mock_voice_clone.html")
        page.goto(f"file://{mock_file_path}")

        # Inject the admin.js content
        admin_js = get_admin_js_content()
        page.add_script_tag(content=admin_js)

        # Manually initialize AIPS since document.ready might have passed
        page.evaluate("window.AIPS.init()")

        # Verify AIPS is initialized
        page.evaluate("console.log('AIPS initialized:', window.AIPS)")

        # Mock the AJAX response for aips_get_voice
        # NOTE: $.ajax in file:// context might behave weirdly.
        # But page.route should intercept it if using a relative URL or absolute http URL.
        # My mock HTML has `ajaxUrl: '/wp-admin/admin-ajax.php'`, which resolves to file:///wp-admin/...
        # This will fail CORS.

        # FIX: Update aipsAjax to use a fake HTTP url
        page.evaluate("window.aipsAjax.ajaxUrl = 'http://example.com/wp-admin/admin-ajax.php'")

        page.route("http://example.com/wp-admin/admin-ajax.php", lambda route: route.fulfill(
            status=200,
            content_type="application/json",
            body='{"success": true, "data": {"voice": {"id": 123, "name": "Professional", "title_prompt": "Write a title...", "content_instructions": "Write content...", "is_active": 1}}}'
        ))

        # Check initial state
        expect(page.locator("#aips-voice-modal")).not_to_be_visible()

        # Click the clone button
        print("Clicking clone button...")
        page.click(".aips-clone-voice")

        # Verify modal opens
        print("Waiting for modal...")
        expect(page.locator("#aips-voice-modal")).to_be_visible()

        # Verify modal title
        expect(page.locator("#aips-voice-modal-title")).to_have_text("Clone Voice")

        # Verify fields
        # ID should be empty
        expect(page.locator("#voice_id")).to_have_value("")

        # Name should have (Copy)
        expect(page.locator("#voice_name")).to_have_value("Professional (Copy)")

        # Other fields should match the mock data
        expect(page.locator("#voice_title_prompt")).to_have_value("Write a title...")
        expect(page.locator("#voice_content_instructions")).to_have_value("Write content...")
        expect(page.locator("#voice_is_active")).to_be_checked()

        # Take a screenshot
        screenshot_path = os.path.abspath("verification/clone_voice_success.png")
        page.screenshot(path=screenshot_path)
        print(f"Screenshot saved to {screenshot_path}")

        browser.close()

if __name__ == "__main__":
    test_clone_voice()
