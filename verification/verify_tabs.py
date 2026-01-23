from playwright.sync_api import sync_playwright, expect
import os
import re

def test_tabs(page):
    cwd = os.getcwd()
    # Ensure absolute path for file:// protocol
    url = f"file://{cwd}/verification/mock_tabs.html"
    print(f"Navigating to {url}")
    page.goto(url)

    # 1. Test JS Tab (should work as before - client side switch)
    print("Testing JS tab switching...")
    page.click("#tab-other")
    # Verify it got the active class
    expect(page.locator("#tab-other")).to_have_class(re.compile(r"nav-tab-active"))
    # Verify content shown
    expect(page.locator("#other-tab")).to_be_visible()

    # 2. Test Real Link Tab (should NOT use JS switching)
    print("Testing Real Link tab (should not switch client-side)...")

    # We want to verify that admin.js did NOT add the 'nav-tab-active' class.
    # If admin.js intercepted it, it would add the class.

    # Note: Clicking will try to navigate to file://.../?page=... which might fail or reload.
    # We use evaluate to stub navigation or just check quickly.
    # Actually, since it's a file URL with query params, it might just reload the file or fail.
    # To be safe, we can change the href to something safe like "javascript:void(0)" BUT
    # then admin.js might behave differently if it relies on href? No, it relies on data-tab.

    # Let's just click.
    page.click("#tab-schedule")

    # Verify it DOES NOT have the active class (because admin.js ignored it)
    expect(page.locator("#tab-schedule")).not_to_have_class(re.compile(r"nav-tab-active"))

    # Take screenshot
    page.screenshot(path="verification/tabs_verification.png")
    print("Screenshot saved to verification/tabs_verification.png")

if __name__ == "__main__":
    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()
        test_tabs(page)
        browser.close()
