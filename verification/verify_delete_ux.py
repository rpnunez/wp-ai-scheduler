import pytest
from playwright.sync_api import sync_playwright, expect
import os
import re

def test_delete_ux():
    # Read admin.js content
    with open('ai-post-scheduler/assets/js/admin.js', 'r') as f:
        admin_js = f.read()

    # Read mock html
    with open('verification/mock_schedule_static.html', 'r') as f:
        html_content = f.read()

    # Inject js
    final_html = html_content.replace('// Inject the admin.js content here for testing', admin_js)

    # Save to temp file
    with open('verification/mock_schedule_final.html', 'w') as f:
        f.write(final_html)

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Load the rendered HTML
        page.goto(f"file://{os.getcwd()}/verification/mock_schedule_final.html")

        # 1. Verify Accessibility of Delete Button
        delete_btn = page.locator('.aips-delete-schedule').first
        expect(delete_btn).to_have_attribute('aria-label', 'Delete schedule for Tech News Daily')
        print("✅ ARIA label verified")

        # 2. Verify Hidden Status Icon
        status_icon = page.locator('.aips-schedule-status .dashicons').first
        expect(status_icon).to_have_attribute('aria-hidden', 'true')
        print("✅ Status icon aria-hidden verified")

        # 3. Verify Soft Confirm Interaction
        # Click once
        delete_btn.click()

        # Verify text changed
        expect(delete_btn).to_have_text("Click again to confirm")
        expect(delete_btn).to_have_class(re.compile(r"aips-confirm-delete"))
        print("✅ Soft confirm state verified")

        # Take screenshot
        page.screenshot(path='verification/delete_ux.png')

        browser.close()

if __name__ == "__main__":
    test_delete_ux()
