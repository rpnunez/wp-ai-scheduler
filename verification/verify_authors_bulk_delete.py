from playwright.sync_api import sync_playwright, expect
import os

def test_authors_bulk_delete():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Load the mock HTML file
        mock_file_path = os.path.abspath("verification/mock_authors.html")
        page.goto(f"file://{mock_file_path}")

        # Verify initial state
        expect(page.locator("#cb-select-all-1")).to_be_visible()
        expect(page.locator("#bulk-action-selector-top")).to_be_visible()
        expect(page.locator(".aips-authors-bulk-action")).to_be_visible()

        # Select all authors
        page.click("#cb-select-all-1")

        # Verify all checkboxes are checked
        expect(page.locator("#cb-select-1")).to_be_checked()
        expect(page.locator("#cb-select-2")).to_be_checked()

        # Select "Delete" action
        page.select_option("#bulk-action-selector-top", "delete")

        # Handle dialog
        page.on("dialog", lambda dialog: dialog.accept())

        # Click Apply
        page.click(".aips-authors-bulk-action")

        # Verify success toast appears
        # Note: The mock HTML simulates a toast appearing.
        # In authors.js, showToast appends to #aips-toast-container
        expect(page.locator(".aips-toast.success")).to_be_visible()
        expect(page.locator(".aips-toast-message")).to_contain_text("deleted successfully")

        # Take screenshot
        page.screenshot(path="verification/authors_bulk_delete_verified.png")

        browser.close()

if __name__ == "__main__":
    test_authors_bulk_delete()
