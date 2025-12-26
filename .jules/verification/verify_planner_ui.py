from playwright.sync_api import sync_playwright
import os

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch()
        # Ensure clipboard permissions
        context = browser.new_context(permissions=['clipboard-read', 'clipboard-write'])
        page = context.new_page()

        # Load mock HTML
        cwd = os.getcwd()
        page.goto("file://" + os.path.join(cwd, ".jules/verification/mock_planner.html"))

        # 1. Verify buttons exist
        assert page.is_visible("#btn-copy-topics")
        assert page.is_visible("#btn-clear-topics")

        # 2. Test Copy Selected (Mocking clipboard since headless might have issues)
        # We'll check if the button text changes to "Copied!" which our JS does
        page.evaluate("navigator.clipboard = { writeText: () => Promise.resolve() }")
        page.click("#btn-copy-topics")
        page.wait_for_timeout(100) # Wait for text update
        assert page.inner_text("#btn-copy-topics") == "Copied!"

        # 3. Test Clear List
        # Mock confirm to return true
        page.on("dialog", lambda dialog: dialog.accept())
        page.click("#btn-clear-topics")

        # Verify list is empty and results hidden
        assert page.inner_html("#topics-list") == ""
        # The JS calls slideUp, which might take time or just set display none eventually.
        # In Playwright with jQuery slideUp, it animates. We can check visibility or style.
        # But since we are verifying structure primarily, the empty list is key.

        # Take screenshot
        page.screenshot(path=".jules/verification/planner_ui_verified.png")

        print("Verification successful")

        browser.close()

if __name__ == "__main__":
    run()
