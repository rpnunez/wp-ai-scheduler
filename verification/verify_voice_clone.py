import sys
from playwright.sync_api import sync_playwright
import os

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()

        cwd = os.getcwd()
        page.goto(f"file://{cwd}/verification/mock_voices.html")

        # Inject jQuery first
        with open('verification/jquery.js', 'r') as f:
            page.add_script_tag(content=f.read())

        # Inject mock object for aipsAjax
        page.evaluate("""
            window.aipsAjax = {
                ajaxUrl: '/wp-admin/admin-ajax.php',
                nonce: 'mock_nonce'
            };
            window.aipsAdminL10n = {
                errorOccurred: 'Error occurred'
            };
        """)

        # Inject admin.js
        with open('ai-post-scheduler/assets/js/admin.js', 'r') as f:
            page.add_script_tag(content=f.read())

        # Mock $.ajax AFTER admin.js has run (which binds events but uses $ internally)
        # Note: $.ajax is a property of jQuery object.
        page.evaluate("""
            // Save original if needed, or just overwrite
            var originalAjax = $.ajax;
            $.ajax = function(options) {
                console.log('AJAX call:', options);

                // Safety check for null options
                if (!options || !options.data) {
                    console.error('AJAX called with invalid options');
                    return;
                }

                if (options.data.action === 'aips_get_voice') {
                    // Simulate success response
                    if (options.success) {
                        options.success({
                            success: true,
                            data: {
                                voice: {
                                    id: 1,
                                    name: 'Professional',
                                    title_prompt: 'Make it pop',
                                    content_instructions: 'Be pro',
                                    excerpt_instructions: 'Short',
                                    is_active: 1
                                }
                            }
                        });
                    }
                }
            };
        """)

        # Test the Clone function
        print("Clicking Clone button...")
        try:
            # We use page.evaluate to trigger click because sometimes Playwright's click
            # might not trigger jQuery handlers if elements are "obscured" or "not visible"
            # in a headless, non-rendered environment without proper layout.
            # But let's try standard click first.
            page.click('.aips-clone-voice')
        except Exception as e:
            print(f"Click failed: {e}")
            # Fallback to JS click
            page.evaluate("$('.aips-clone-voice').click()")

        # Verify Modal State
        print("Verifying Modal State...")

        # Check Title
        try:
            # Wait for modal to be visible/updated
            # In our synchronous mock, it should be instant.
            title = page.inner_text('#aips-voice-modal-title')
            if title != 'Clone Voice':
                print(f"FAILED: Modal title is '{title}', expected 'Clone Voice'")
                sys.exit(1)

            # Check Name
            name_val = page.eval_on_selector('#voice_name', 'el => el.value')
            if name_val != 'Professional (Copy)':
                print(f"FAILED: Voice name is '{name_val}', expected 'Professional (Copy)'")
                sys.exit(1)

            # Check ID is empty
            id_val = page.eval_on_selector('#voice_id', 'el => el.value')
            if id_val != '':
                print(f"FAILED: Voice ID is '{id_val}', expected empty string")
                sys.exit(1)

            print("SUCCESS: Clone functionality verified!")
        except Exception as e:
            print(f"Verification failed: {e}")
            sys.exit(1)

        browser.close()

if __name__ == "__main__":
    run()
