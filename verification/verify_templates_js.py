import os
from playwright.sync_api import sync_playwright, expect

def verify_templates_js():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Load the mock HTML
        # We need to inject the JS content directly because we can't easily serve local files with relative paths in this environment for file://
        # Or we can read the files and inject them.

        with open('verification/mock_templates.html', 'r') as f:
            html_content = f.read()

        with open('ai-post-scheduler/assets/js/admin.js', 'r') as f:
            admin_js = f.read()

        with open('ai-post-scheduler/assets/js/admin-templates.js', 'r') as f:
            admin_templates_js = f.read()

        # Inject scripts into HTML
        final_html = html_content.replace(
            '<!-- Scripts will be injected by Playwright or loaded manually if needed -->',
            f'<script>{admin_js}</script><script>{admin_templates_js}</script>'
        )

        page.set_content(final_html)

        # Check if AIPS.initTemplates is defined
        is_defined = page.evaluate("() => typeof window.AIPS.initTemplates === 'function'")
        print(f"AIPS.initTemplates is defined: {is_defined}")
        assert is_defined

        # Verify that clicking the button opens the modal (mocking the behavior)
        # First, ensure modal is hidden
        expect(page.locator('#aips-template-modal')).to_be_hidden()

        # Click the button
        page.click('.aips-add-template-btn')

        # Expect modal to be visible (because admin-templates.js logic should handle it)
        expect(page.locator('#aips-template-modal')).to_be_visible()

        # Verify title was updated
        expect(page.locator('#aips-modal-title')).to_have_text('Add New Template')

        print("Frontend verification passed!")

        # Take screenshot
        os.makedirs('verification', exist_ok=True)
        page.screenshot(path='verification/verify_templates.png')

        browser.close()

if __name__ == "__main__":
    verify_templates_js()
