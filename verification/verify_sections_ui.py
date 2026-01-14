
from playwright.sync_api import Page, expect, sync_playwright
import os
import time

def verify_sections_page(page: Page):
    # Load the mock HTML file
    file_path = os.path.abspath("verification/mock_sections.html")
    page.goto(f"file://{file_path}")

    # Verify initial state
    expect(page.locator(".aips-sections-list")).to_be_visible()
    expect(page.locator("tr[data-section-id='1']")).to_be_visible()
    expect(page.locator("tr[data-section-id='2']")).to_be_visible()
    expect(page.locator("tr[data-section-id='3']")).to_be_visible()

    # Verify search input exists
    search_input = page.locator("#aips-section-search")
    expect(search_input).to_be_visible()

    # ACT 1: Search for "Intro"
    search_input.click()
    page.keyboard.type("Intro", delay=100) # Type slowly to trigger events

    # Wait for JS to process (debounce or just execution)
    page.wait_for_timeout(500)

    # ASSERT 1: Only Introduction should be visible
    expect(page.locator("tr[data-section-id='1']")).to_be_visible()
    expect(page.locator("tr[data-section-id='2']")).not_to_be_visible()
    expect(page.locator("tr[data-section-id='3']")).not_to_be_visible()
    expect(page.locator("#aips-section-search-no-results")).not_to_be_visible()

    # Take screenshot of filtered state
    page.screenshot(path="verification/verification_filtered.png")

    # ACT 2: Search for non-existent term
    search_input.fill("") # Clear first
    search_input.click()
    page.keyboard.type("XYZ123", delay=100)

    page.wait_for_timeout(500)

    # ASSERT 2: No results
    expect(page.locator("tr[data-section-id='1']")).not_to_be_visible()
    expect(page.locator("#aips-section-search-no-results")).to_be_visible()

    # Take screenshot of empty state
    page.screenshot(path="verification/verification_empty.png")

    # ACT 3: Clear search via button
    page.locator(".aips-clear-section-search-btn").click()

    page.wait_for_timeout(500)

    # ASSERT 3: All visible
    expect(page.locator("tr[data-section-id='1']")).to_be_visible()
    expect(page.locator("tr[data-section-id='2']")).to_be_visible()
    expect(page.locator("tr[data-section-id='3']")).to_be_visible()

    # Take final screenshot
    page.screenshot(path="verification/verification_final.png")

if __name__ == "__main__":
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        try:
            verify_sections_page(page)
            print("Verification script completed successfully.")
        except Exception as e:
            print(f"Verification failed: {e}")
        finally:
            browser.close()
