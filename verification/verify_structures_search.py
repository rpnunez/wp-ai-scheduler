from playwright.sync_api import sync_playwright, expect
import os

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    page = browser.new_page()

    # Capture console logs
    page.on("console", lambda msg: print(f"PAGE LOG: {msg.text}"))
    page.on("pageerror", lambda exc: print(f"PAGE ERROR: {exc}"))

    file_path = os.path.abspath("verification/mock_structures_search.html")
    # Use allow_file_access_from_files usually requires args, but let's see if console reveals blocked script
    page.goto(f"file://{file_path}")

    # --- Test Structures Search ---

    # Initial state
    rows = page.locator(".aips-structures-list tbody tr")
    expect(rows).to_have_count(3)

    # Search for "Listicle" (Name)
    page.type("#aips-structure-search", "Listicle")

    # Wait a bit to ensure event fired
    page.wait_for_timeout(500)

    visible_rows = page.locator(".aips-structures-list tbody tr:visible")
    expect(visible_rows).to_have_count(1)
    expect(visible_rows.first).to_contain_text("Listicle")

    # Clear search
    page.click("#aips-structure-search-clear")
    expect(rows).to_have_count(3)

    # Search for "analysis" (Description)
    page.type("#aips-structure-search", "analysis")
    visible_rows = page.locator(".aips-structures-list tbody tr:visible")
    expect(visible_rows).to_have_count(1)
    expect(visible_rows.first).to_contain_text("Product Review")

    # Search for "nomatch"
    page.type("#aips-structure-search", "nomatch")
    expect(page.locator(".aips-structures-list tbody tr:visible")).to_have_count(0)
    expect(page.locator("#aips-structure-search-no-results")).to_be_visible()

    # Clear via empty state button
    page.click(".aips-clear-structure-search-btn")
    expect(page.locator(".aips-structures-list tbody tr:visible")).to_have_count(3)

    # --- Test Sections Search (Switch Tab first) ---

    page.click("a[data-tab='aips-structure-sections']")
    # Ensure tab content is visible
    expect(page.locator("#aips-structure-sections-tab")).to_be_visible()

    # Initial state
    section_rows = page.locator(".aips-sections-list tbody tr")
    expect(section_rows).to_have_count(2)

    # Search for "Intro"
    page.type("#aips-section-search", "Intro")
    visible_section_rows = page.locator(".aips-sections-list tbody tr:visible")
    expect(visible_section_rows).to_have_count(1)
    expect(visible_section_rows.first).to_contain_text("Intro")

    # Take screenshot
    if not os.path.exists("verification/screenshots"):
        os.makedirs("verification/screenshots", exist_ok=True)

    page.screenshot(path="verification/screenshots/structures_search.png", full_page=True)
    print("Verification successful! Screenshot saved to verification/screenshots/structures_search.png")

    browser.close()

with sync_playwright() as playwright:
    run(playwright)
