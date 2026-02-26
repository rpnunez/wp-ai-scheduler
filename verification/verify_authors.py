from playwright.sync_api import sync_playwright, expect
import os

def test_authors_search():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Load local HTML file
        cwd = os.getcwd()
        page.goto(f"file://{cwd}/verification/mock_authors.html")

        # Take initial screenshot
        page.screenshot(path="verification/authors_initial.png")

        # 1. Search for "Jane"
        page.fill("#aips-author-search", "Jane")
        page.wait_for_timeout(100) # Wait for debounce if any (though currently synchronous)

        # Verify Jane is visible
        expect(page.locator("tr[data-author-id='2']")).to_be_visible()
        # Verify John and Bob are hidden
        expect(page.locator("tr[data-author-id='1']")).not_to_be_visible()
        expect(page.locator("tr[data-author-id='3']")).not_to_be_visible()

        page.screenshot(path="verification/authors_search_jane.png")

        # 2. Search for "Tech" (matches Niche)
        page.fill("#aips-author-search", "Tech")
        page.wait_for_timeout(100)

        expect(page.locator("tr[data-author-id='1']")).to_be_visible() # Tech Blog
        expect(page.locator("tr[data-author-id='3']")).to_be_visible() # Tech News
        expect(page.locator("tr[data-author-id='2']")).not_to_be_visible() # Gardening

        page.screenshot(path="verification/authors_search_tech.png")

        # 3. Search for non-existent
        page.fill("#aips-author-search", "XYZ")
        page.wait_for_timeout(100)

        expect(page.locator("tr[data-author-id='1']")).not_to_be_visible()
        expect(page.locator("tr[data-author-id='2']")).not_to_be_visible()
        expect(page.locator("tr[data-author-id='3']")).not_to_be_visible()
        expect(page.locator("#aips-author-search-no-results")).to_be_visible()

        page.screenshot(path="verification/authors_search_none.png")

        # 4. Clear search
        page.click(".aips-clear-author-search-btn")

        expect(page.locator("tr[data-author-id='1']")).to_be_visible()
        expect(page.locator("tr[data-author-id='2']")).to_be_visible()
        expect(page.locator("tr[data-author-id='3']")).to_be_visible()
        expect(page.locator("#aips-author-search-no-results")).not_to_be_visible()

        print("Search verification successful!")

        browser.close()

if __name__ == "__main__":
    test_authors_search()
