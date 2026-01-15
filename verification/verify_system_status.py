
from playwright.sync_api import sync_playwright, expect
import os

def test_system_status_copy(page):
    # Load the mock HTML file
    filepath = os.path.abspath('verification/mock_system_status.html')
    page.goto(f'file://{filepath}')

    # Verify the button exists
    copy_btn = page.locator('.aips-copy-btn')
    expect(copy_btn).to_be_visible()
    expect(copy_btn).to_have_text('Copy System Report')

    # Click the button
    copy_btn.click()

    # Verify the button text changes to "Copied!"
    expect(copy_btn).to_have_text('Copied!')

    # Verify the text was "copied" (using our mock window.lastCopiedText)
    copied_text = page.evaluate('window.lastCopiedText')

    print(f"Copied text length: {len(copied_text)}")
    print(f"Copied text preview: {copied_text[:50]}...")

    assert "### AI Post Scheduler System Report ###" in copied_text
    assert "PHP Version: 8.2 (OK)" in copied_text

    # Take a screenshot
    page.screenshot(path='verification/system_status_copy.png')

if __name__ == "__main__":
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        test_system_status_copy(page)
        browser.close()
