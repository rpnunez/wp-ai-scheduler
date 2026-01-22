from playwright.sync_api import sync_playwright
import os
import sys

def verify_authors_search():
    # Get absolute path to the mock file
    mock_file_path = os.path.abspath("verification/mock_authors.html")
    file_url = f"file://{mock_file_path}"

    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()
        page.goto(file_url)

        print("1. Verifying initial state...")
        rows = page.locator(".aips-authors-table tbody tr")
        print(f"Found {rows.count()} rows.")
        if rows.count() != 3:
            print(f"Error: Expected 3 rows, found {rows.count()}")
            sys.exit(1)

        # Verify empty state is hidden
        if page.is_visible("#aips-author-search-no-results"):
            print("Error: Empty state should be hidden initially")
            sys.exit(1)

        print("2. Verifying search functionality...")
        # Search for "Tech" (should match John Doe and Bob Jones)
        page.fill("#aips-author-search", "Tech")
        page.dispatch_event("#aips-author-search", "keyup")

        # Wait for filtering
        page.wait_for_timeout(100)

        visible_rows = page.locator(".aips-authors-table tbody tr:visible")
        count = visible_rows.count()
        print(f"Search 'Tech': Found {count} visible rows.")

        if count != 2:
            print(f"Error: Expected 2 rows for 'Tech', found {count}")
            # print visible rows text
            for i in range(count):
                print(visible_rows.nth(i).inner_text())
            sys.exit(1)

        page.screenshot(path="verification/search_tech.png")
        print("Screenshot saved: verification/search_tech.png")

        # Check Clear button visibility
        if not page.is_visible("#aips-author-search-clear"):
            print("Error: Clear button should be visible after typing")
            sys.exit(1)

        print("3. Verifying 'No Results' state...")
        page.fill("#aips-author-search", "XYZ123")
        page.dispatch_event("#aips-author-search", "keyup")
        page.wait_for_timeout(100)

        visible_rows = page.locator(".aips-authors-table tbody tr:visible")
        if visible_rows.count() != 0:
            print(f"Error: Expected 0 rows, found {visible_rows.count()}")
            sys.exit(1)

        if not page.is_visible("#aips-author-search-no-results"):
            print("Error: No Results message should be visible")
            sys.exit(1)

        page.screenshot(path="verification/no_results.png")
        print("Screenshot saved: verification/no_results.png")

        print("4. Verifying 'Clear Search' button in empty state...")
        page.click(".aips-clear-author-search-btn")

        visible_rows = page.locator(".aips-authors-table tbody tr:visible")
        if visible_rows.count() != 3:
            print(f"Error: Expected 3 rows after clearing, found {visible_rows.count()}")
            sys.exit(1)

        if page.input_value("#aips-author-search") != "":
            print("Error: Search input should be empty after clearing")
            sys.exit(1)

        print("SUCCESS: Author search verification passed!")
        browser.close()

if __name__ == "__main__":
    verify_authors_search()
