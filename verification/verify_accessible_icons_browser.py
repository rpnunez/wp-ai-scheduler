import os
from playwright.sync_api import sync_playwright, expect

def test_accessible_icons():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Load mock HTML
        cwd = os.getcwd()
        page.goto(f"file://{cwd}/verification/mock_admin.html")

        # Inject the modified admin.js content
        with open('ai-post-scheduler/assets/js/admin.js', 'r') as f:
            js_content = f.read()
        page.add_script_tag(content=js_content)

        # Mock $.ajax to simulate responses that trigger the modified code
        page.evaluate("""
            $.ajax = function(options) {
                if (options.data.action === 'aips_test_connection') {
                    // Simulate success
                    options.success({
                        success: true,
                        data: { message: 'Connection successful' }
                    });
                }
            };

            // Trigger connection test
            $('#aips-test-connection').click();

            // Trigger renderDetails with mock data
            AIPS.renderDetails({
                status: 'completed',
                generated_title: 'Test Title',
                generation_log: {
                    template: {
                        name: 'Test Template',
                        prompt_template: 'Write about AI',
                        title_prompt: 'Generate title'
                    },
                    voice: {
                        name: 'Professional',
                        title_prompt: 'Make it professional',
                        content_instructions: 'Be concise',
                        excerpt_instructions: 'Summarize it'
                    },
                    ai_calls: [
                        {
                            type: 'content',
                            timestamp: '2024-05-23 10:00:00',
                            request: { prompt: 'User prompt' },
                            response: { success: true, content: 'AI response' }
                        }
                    ]
                }
            });

            // Show modal manually since renderDetails doesn't show it
            $('#aips-details-modal').show();
        """)

        # 1. Verify Test Connection Icon
        # Wait for the result to be populated
        expect(page.locator('#aips-connection-result')).to_contain_text("Connection successful")

        # Check if the icon has aria-hidden="true"
        connection_icon = page.locator('#aips-connection-result .dashicons-yes')
        expect(connection_icon).to_have_attribute("aria-hidden", "true")
        print("✅ Connection test icon has aria-hidden='true'")

        # 2. Verify Render Details Icons (Copy Buttons)
        # Check template prompt copy button
        template_copy_btn_icon = page.locator('#aips-details-template .aips-copy-btn .dashicons-admin-page').first
        expect(template_copy_btn_icon).to_have_attribute("aria-hidden", "true")
        print("✅ Template copy button icon has aria-hidden='true'")

        # Check voice instructions copy button
        voice_copy_btn_icon = page.locator('#aips-details-voice .aips-copy-btn .dashicons-admin-page').first
        expect(voice_copy_btn_icon).to_have_attribute("aria-hidden", "true")
        print("✅ Voice copy button icon has aria-hidden='true'")

        # Check AI call copy button
        call_copy_btn_icon = page.locator('#aips-details-ai-calls .aips-copy-btn .dashicons-admin-page').first
        expect(call_copy_btn_icon).to_have_attribute("aria-hidden", "true")
        print("✅ AI call copy button icon has aria-hidden='true'")

        # Take screenshot
        page.screenshot(path="verification/verification_icons.png")
        print("📸 Screenshot saved to verification/verification_icons.png")

        browser.close()

if __name__ == "__main__":
    test_accessible_icons()
