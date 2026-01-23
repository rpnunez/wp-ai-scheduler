import os
from playwright.sync_api import sync_playwright, expect

def verify_author_search():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Load the mock file
        cwd = os.getcwd()
        file_path = f"file://{cwd}/verification/mock_author_search.html"
        print(f"Navigating to {file_path}")
        page.goto(file_path)

        # Wait for table to be visible
        table = page.locator(".wp-list-table")
        expect(table).to_be_visible()

        print("Table visible. Starting search test.")

        # 1. Search for "Doe"
        search_input = page.locator("#aips-author-search")
        search_input.click()
        search_input.type("Doe", delay=100) # Type with delay to ensure events fire

        # Verify filtering
        # "John Doe" should be visible
        expect(page.locator("tr:has-text('John Doe')")).to_be_visible()
        # "Jane Smith" should be hidden
        expect(page.locator("tr:has-text('Jane Smith')")).not_to_be_visible()

        page.screenshot(path="verification/search_active.png")
        print("Search active verified.")

        # 2. Clear Search
        clear_btn = page.locator("#aips-author-search-clear")
        expect(clear_btn).to_be_visible()
        clear_btn.click()

        # Verify all visible
        expect(page.locator("tr:has-text('John Doe')")).to_be_visible()
        expect(page.locator("tr:has-text('Jane Smith')")).to_be_visible()

        print("Clear search verified.")

        # 3. Search for non-existent
        search_input.click()
        search_input.fill("") # ensure clean
        search_input.type("XYZ", delay=100)

        # Verify empty state
        no_results = page.locator("#aips-author-search-no-results")
        expect(no_results).to_be_visible()
        expect(table).not_to_be_visible()

        page.screenshot(path="verification/search_empty.png")
        print("Empty state verified.")

        browser.close()

if __name__ == "__main__":
    verify_author_search()
