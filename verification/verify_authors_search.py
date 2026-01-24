from playwright.sync_api import sync_playwright
import os

def test_authors_search():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Determine absolute path to the mock file
        cwd = os.getcwd()
        mock_file_path = os.path.join(cwd, 'verification/verify_authors_search.html')
        page.goto(f"file://{mock_file_path}")

        print("Page loaded")

        # 1. Search for 'PHP'
        page.fill('#aips-author-search', 'PHP')
        # Trigger keyup event manually as fill might not trigger it exactly how jQuery expects
        page.evaluate("jQuery('#aips-author-search').trigger('keyup')")

        # Verify John Doe (PHP) is visible
        if not page.is_visible("tr[data-author-id='1']"):
            print("Error: John Doe should be visible")
            exit(1)
        # Verify Jane Smith (React) is hidden
        if page.is_visible("tr[data-author-id='2']"):
            print("Error: Jane Smith should be hidden")
            exit(1)

        page.screenshot(path="verification/1_search_php.png")
        print("Search 'PHP' verified")

        # 2. Search for 'XYZ' (No results)
        page.fill('#aips-author-search', 'XYZ')
        page.evaluate("jQuery('#aips-author-search').trigger('keyup')")

        if not page.is_visible('#aips-author-search-no-results'):
             print("Error: Empty state should be visible")
             exit(1)
        if page.is_visible("table.wp-list-table"):
             print("Error: Table should be hidden")
             exit(1)

        page.screenshot(path="verification/2_search_empty.png")
        print("Search empty state verified")

        # 3. Clear search via button in empty state
        page.click('.aips-clear-author-search-btn')

        if page.is_visible('#aips-author-search-no-results'):
            print("Error: Empty state should be hidden after clear")
            exit(1)
        if not page.is_visible("table.wp-list-table"):
            print("Error: Table should be visible after clear")
            exit(1)
        if not page.is_visible("tr[data-author-id='1']"):
             print("Error: John Doe should be visible after clear")
             exit(1)
        if not page.is_visible("tr[data-author-id='2']"):
             print("Error: Jane Smith should be visible after clear")
             exit(1)

        page.screenshot(path="verification/3_search_cleared.png")
        print("Clear search verified")

        browser.close()

if __name__ == "__main__":
    test_authors_search()
