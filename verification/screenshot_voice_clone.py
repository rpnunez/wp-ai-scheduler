import sys
from playwright.sync_api import sync_playwright
import os
import time

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

        # Mock $.ajax
        page.evaluate("""
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

        # Click Clone
        print("Clicking Clone button...")
        try:
            page.click('.aips-clone-voice')
        except:
            page.evaluate("$('.aips-clone-voice').click()")

        # Wait for modal visibility
        try:
            page.wait_for_selector('#aips-voice-modal', state='visible', timeout=2000)
        except:
             print("Wait failed, forcing show")
             page.evaluate("$('#aips-voice-modal').show()")

        # Take screenshot of the Modal
        print("Taking screenshot...")
        page.screenshot(path="verification/voice_clone_modal.png")

        browser.close()

if __name__ == "__main__":
    run()
