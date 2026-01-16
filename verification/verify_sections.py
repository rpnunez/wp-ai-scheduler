import os
from playwright.sync_api import sync_playwright, expect

def test_sections_filter():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Load local file
        file_path = os.path.abspath("verification/mock_sections.html")
        page.goto(f"file://{file_path}")

        # 1. Verify initial state
        print("Verifying initial state...")
        rows = page.locator(".aips-sections-list tbody tr")
        expect(rows).to_have_count(3)
        expect(page.locator("#aips-section-search-clear")).not_to_be_visible()

        # 2. Search for "conclusion"
        print("Searching for 'conclusion'...")
        page.locator("#aips-section-search").fill("")
        page.locator("#aips-section-search").type("conclusion")

        # 3. Verify filtering
        print("Verifying filtering...")
        # Wait for jQuery filter to apply (it's synchronous but good to wait)
        page.wait_for_timeout(500)

        visible_rows = page.locator(".aips-sections-list tbody tr:visible")
        expect(visible_rows).to_have_count(1)
        expect(visible_rows.first).to_contain_text("Conclusion")
        expect(page.locator("#aips-section-search-clear")).to_be_visible()

        # Screenshot of filtered state
        page.screenshot(path="verification/sections_filtered.png")

        # 4. Clear search
        print("Clearing search...")
        page.locator("#aips-section-search-clear").click()

        # 5. Verify reset
        print("Verifying reset...")
        page.wait_for_timeout(500)
        visible_rows = page.locator(".aips-sections-list tbody tr:visible")
        expect(visible_rows).to_have_count(3)
        expect(page.locator("#aips-section-search")).to_be_empty()

        # 6. Test No Results
        print("Testing no results...")
        page.locator("#aips-section-search").type("xyz non existent")

        page.wait_for_timeout(500)
        expect(page.locator("#aips-section-search-no-results")).to_be_visible()
        expect(page.locator(".aips-sections-list")).not_to_be_visible()

        # Screenshot of no results
        page.screenshot(path="verification/sections_no_results.png")

        print("Verification successful!")
        browser.close()

if __name__ == "__main__":
    test_sections_filter()
