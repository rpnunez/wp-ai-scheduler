
import os
from playwright.sync_api import sync_playwright, expect

def test_copy_log(page):
    # Load the mock HTML
    page.goto(f"file://{os.getcwd()}/verification/mock_system_status.html")

    # Inject the modified admin.js content
    # We read the actual file content to verify the real code
    with open('ai-post-scheduler/assets/js/admin.js', 'r') as f:
        js_content = f.read()

    page.add_script_tag(content=js_content)

    # Initialize AIPS
    page.evaluate("window.AIPS.init();")

    # Grant clipboard permissions
    page.context.grant_permissions(['clipboard-read', 'clipboard-write'])

    # Find the copy button
    copy_btn = page.locator('.aips-copy-btn')

    # Click the copy button
    copy_btn.click()

    # Verify button text changes to "Copied!"
    expect(copy_btn).to_have_text("Copied!")

    # Verify clipboard content
    # Note: In some headless environments, reading clipboard might be restricted.
    # However, since we granted permissions, it should work.
    clipboard_content = page.evaluate("navigator.clipboard.readText()")

    expected_log = """2024-05-28 10:00:00 [INFO] System check started.
2024-05-28 10:00:01 [INFO] Database connection OK.
2024-05-28 10:00:02 [WARNING] Memory limit low (128M).
2024-05-28 10:00:03 [INFO] Check completed."""

    # Using strip to avoid newline mismatches
    assert clipboard_content.strip() == expected_log.strip()

    # Take screenshot
    page.screenshot(path="verification/verification_copy_log.png")
    print("Verification successful!")

if __name__ == "__main__":
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        try:
            test_copy_log(page)
        except Exception as e:
            print(f"Test failed: {e}")
            page.screenshot(path="verification/verification_failed.png")
            raise
        finally:
            browser.close()
