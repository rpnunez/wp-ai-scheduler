import os
from playwright.sync_api import sync_playwright, expect

def test_authors_search():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Get absolute path to the mock HTML
        file_path = os.path.abspath("verification/mock_authors.html")
        page.goto(f"file://{file_path}")

        # 1. Verify initial state: 3 rows visible
        expect(page.locator(".aips-authors-table tbody tr")).to_have_count(3)
        print("Initial state: 3 authors visible.")

        # 2. Search for "PHP"
        search_input = page.locator("#aips-author-search")
        search_input.fill("PHP")
        # Trigger events that admin.js listens to
        search_input.press("KeyA")
        search_input.press("Backspace")
        search_input.fill("PHP")
        page.evaluate("jQuery('#aips-author-search').trigger('keyup')")


        # Expect 1 row visible
        expect(page.locator(".aips-authors-table tbody tr:visible")).to_have_count(1)
        expect(page.locator(".aips-authors-table tbody tr:visible .column-name")).to_contain_text("John Doe")
        print("Search 'PHP': 1 author visible (John Doe).")

        # Take screenshot
        page.screenshot(path="verification/authors_search_filtered.png")

        # 3. Clear search using button
        clear_btn = page.locator("#aips-author-search-clear")
        expect(clear_btn).to_be_visible()
        clear_btn.click()

        # Expect 3 rows visible
        expect(page.locator(".aips-authors-table tbody tr:visible")).to_have_count(3)
        print("Clear search: 3 authors visible.")

        # 4. Search for non-existent
        search_input.fill("NonExistent")
        page.evaluate("jQuery('#aips-author-search').trigger('keyup')")

        # Expect 0 rows visible, empty state visible
        expect(page.locator(".aips-authors-table tbody tr:visible")).to_have_count(0)
        expect(page.locator("#aips-author-search-no-results")).to_be_visible()
        print("Search 'NonExistent': 0 authors visible, empty state shown.")

        # Take screenshot
        page.screenshot(path="verification/authors_search_empty.png")

        browser.close()

if __name__ == "__main__":
    test_authors_search()
