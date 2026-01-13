from playwright.sync_api import sync_playwright, expect
import os

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    page = browser.new_page()

    # Load the mock file
    file_path = os.path.abspath("verification/mock_sections.html")
    page.goto(f"file://{file_path}")

    # Wait for JS to load
    page.wait_for_load_state("networkidle")

    # Verify initial state
    rows = page.locator(".aips-sections-list tbody tr")
    expect(rows).to_have_count(3)
    expect(page.locator("#aips-section-search-clear")).not_to_be_visible()

    # Search for "Intro"
    print("Searching for 'Intro'")
    page.fill("#aips-section-search", "Intro")
    page.evaluate("jQuery('#aips-section-search').trigger('keyup')")

    # Expect 1 row visible
    expect(page.locator(".aips-sections-list tbody tr:visible")).to_have_count(1)
    expect(page.locator(".aips-sections-list tbody tr:nth-child(1)")).to_be_visible() # Intro is first
    expect(page.locator(".aips-sections-list tbody tr:nth-child(2)")).not_to_be_visible() # Conclusion hidden

    # Take screenshot of search result
    page.screenshot(path="/home/jules/verification/search_intro.png")

    # Search for "body_main" (key)
    print("Searching for 'body_main'")
    page.fill("#aips-section-search", "body_main")
    page.evaluate("jQuery('#aips-section-search').trigger('keyup')")
    expect(page.locator(".aips-sections-list tbody tr:visible")).to_have_count(1)

    # Search for something non-existent
    print("Searching for 'xyz'")
    page.fill("#aips-section-search", "xyz")
    page.evaluate("jQuery('#aips-section-search').trigger('keyup')")
    expect(page.locator(".aips-sections-list")).not_to_be_visible()
    expect(page.locator("#aips-section-search-no-results")).to_be_visible()

    # Take screenshot of empty state
    page.screenshot(path="/home/jules/verification/search_empty.png")

    # Click clear button (in empty state)
    print("Clicking clear button")
    page.click(".aips-clear-section-search-btn")
    expect(page.locator("#aips-section-search")).to_have_value("")
    expect(page.locator(".aips-sections-list")).to_be_visible()
    expect(page.locator(".aips-sections-list tbody tr:visible")).to_have_count(3)

    print("Verification successful!")
    browser.close()

with sync_playwright() as playwright:
    run(playwright)
