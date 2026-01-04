
import os
from playwright.sync_api import sync_playwright, expect

def verify_clone_voice():
    with sync_playwright() as p:
        # Disable web security to allow local file AJAX
        browser = p.chromium.launch(headless=True, args=["--disable-web-security"])
        page = browser.new_page()

        # Subscribe to console events
        page.on("console", lambda msg: print(f"Console: {msg.text}"))

        # Load the mock HTML file
        file_path = os.path.abspath("verification/mock_voices.html")
        page.goto(f"file://{file_path}")

        # Inject mock BEFORE interaction
        page.evaluate("""
            window.originalAjax = $.ajax;
            $.ajax = function(options) {
                // Safely access options.data
                var data = (options && options.data) ? options.data : {};

                if (data.action === 'aips_get_voice') {
                    console.log('Mocking aips_get_voice');
                    if (options.success) {
                        options.success({
                            success: true,
                            data: {
                                voice: {
                                    id: '123',
                                    name: 'Original Voice',
                                    title_prompt: 'Title Prompt',
                                    content_instructions: 'Content',
                                    excerpt_instructions: 'Excerpt',
                                    is_active: '1'
                                }
                            }
                        });
                    }
                    return { fail: function() {} };
                }
                if (window.originalAjax) {
                    return window.originalAjax(options);
                }
            };
        """)

        # Click the Clone button
        print("Clicking clone button...")
        page.click(".aips-clone-voice")

        # Wait for the modal to appear
        print("Waiting for modal...")
        expect(page.locator("#aips-voice-modal")).to_be_visible()

        # Verify the modal title
        expect(page.locator("#aips-voice-modal-title")).to_have_text("Clone Voice")

        # Verify the inputs
        expect(page.locator("#voice_id")).to_have_value("") # ID should be empty
        expect(page.locator("#voice_name")).to_have_value("Original Voice (Copy)")
        expect(page.locator("#voice_title_prompt")).to_have_value("Title Prompt")

        # Take a screenshot
        page.screenshot(path="verification/clone_voice_success.png")
        print("Verification successful! Screenshot saved to verification/clone_voice_success.png")

        browser.close()

if __name__ == "__main__":
    verify_clone_voice()
