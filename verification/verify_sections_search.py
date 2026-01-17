from playwright.sync_api import sync_playwright, expect
import os

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    # Grant clipboard permissions if possible, though fallback should work
    context = browser.new_context(permissions=["clipboard-read", "clipboard-write"])
    page = context.new_page()

    # Load the static mock file
    file_path = os.path.abspath("verification/mock_sections.html")
    page.goto(f"file://{file_path}")

    # Verify initial state
    rows = page.locator(".aips-sections-list tbody tr")
    expect(rows).to_have_count(3)

    # Test Search: "Intro"
    page.type("#aips-section-search", "Intro")

    visible_rows = page.locator(".aips-sections-list tbody tr:visible")
    expect(visible_rows).to_have_count(1)
    expect(visible_rows.first).to_contain_text("Introduction")

    # Test Clear button appearance
    clear_btn = page.locator("#aips-section-search-clear")
    expect(clear_btn).to_be_visible()

    # Test Search: "Nomatch"
    page.fill("#aips-section-search", "")
    page.type("#aips-section-search", "Nomatch")

    expect(visible_rows).to_have_count(0)
    expect(page.locator("#aips-section-search-no-results")).to_be_visible()

    # Test Clear button click
    page.click("#aips-section-search-clear")

    expect(visible_rows).to_have_count(3)
    expect(page.locator("#aips-section-search-no-results")).not_to_be_visible()

    # Test Copy Button functionality
    copy_btn = page.locator(".aips-copy-btn").first
    expect(copy_btn).to_be_visible()
    expect(copy_btn).to_have_attribute("data-clipboard-text", "{{section:introduction}}")

    # Click copy button
    copy_btn.click()

    # Check if icon changed to checkmark (dashicons-yes)
    # The class attribute should become "dashicons dashicons-yes"
    icon = copy_btn.locator(".dashicons")
    expect(icon).to_have_class("dashicons dashicons-yes")

    # Wait for 2 seconds + buffer and check if it reverts
    page.wait_for_timeout(2500)
    expect(icon).to_have_class("dashicons dashicons-admin-page")

    # Take screenshot of the result (original state restored)
    if not os.path.exists("verification/screenshots"):
        os.makedirs("verification/screenshots", exist_ok=True)

    page.screenshot(path="verification/screenshots/sections_search.png", full_page=True)

    print("Verification successful! Screenshot saved to verification/screenshots/sections_search.png")
    browser.close()

with sync_playwright() as playwright:
    run(playwright)
