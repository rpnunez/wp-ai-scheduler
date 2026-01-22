from playwright.sync_api import sync_playwright
import os

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Load the mock file
        filepath = os.path.abspath("verification/mock_structures.html")
        page.goto(f"file://{filepath}")

        # Wait for table to be visible
        page.wait_for_selector(".aips-structures-list")

        # Take initial screenshot
        page.screenshot(path="verification/1_initial.png")
        print("Initial state captured.")

        # Search for "News"
        page.fill("#aips-structure-search", "News")
        page.keyboard.up("Enter") # Trigger keyup event

        # Wait for filter to apply (simple jQuery show/hide is synchronous but let's wait a bit or check visibility)
        page.wait_for_timeout(500)

        # Check that "News Article" is visible and "Blog Post" is hidden
        news_row = page.locator("tr[data-structure-id='2']")
        blog_row = page.locator("tr[data-structure-id='1']")

        if news_row.is_visible() and not blog_row.is_visible():
            print("Search for 'News' successful: News visible, Blog hidden.")
        else:
            print(f"Search failed: News visible={news_row.is_visible()}, Blog visible={blog_row.is_visible()}")

        page.screenshot(path="verification/2_search_news.png")

        # Search for "Nonexistent"
        page.fill("#aips-structure-search", "Nonexistent")
        page.keyboard.up("Enter")
        page.wait_for_timeout(500)

        # Check empty state
        empty_state = page.locator("#aips-structure-search-no-results")
        if empty_state.is_visible():
             print("Empty state visible.")
        else:
             print("Empty state NOT visible.")

        page.screenshot(path="verification/3_empty_state.png")

        # Clear search
        page.click(".aips-clear-structure-search-btn")
        page.wait_for_timeout(500)

        if blog_row.is_visible() and news_row.is_visible():
             print("Clear search successful: All rows visible.")
        else:
             print("Clear search failed.")

        page.screenshot(path="verification/4_cleared.png")

        browser.close()

if __name__ == "__main__":
    run()
