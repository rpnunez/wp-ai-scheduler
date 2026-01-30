from playwright.sync_api import sync_playwright
import os

def test_planner_search():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Determine absolute path to the mock file
        cwd = os.getcwd()
        mock_file_path = os.path.join(cwd, 'verification/mock_planner.html')
        page.goto(f"file://{mock_file_path}")

        print("Page loaded")

        # 1. Search for 'PHP'
        page.fill('#planner-topic-search', 'PHP')
        # Trigger keyup
        page.evaluate("jQuery('#planner-topic-search').trigger('keyup')")

        # Verify PHP is visible, others hidden
        # We need to find the topic item that contains the input with value 'PHP Guide'
        # .topic-item -> input[value="PHP Guide"]

        # Check PHP visibility
        # The script hides the parent .topic-item

        # Let's target by value
        php_input = page.locator('.topic-text-input[value="PHP Guide"]')
        php_item = page.locator('.topic-item').filter(has=php_input)

        if not php_item.is_visible():
            print("Error: PHP Guide should be visible")
            exit(1)

        react_input = page.locator('.topic-text-input[value="React Tutorial"]')
        react_item = page.locator('.topic-item').filter(has=react_input)

        if react_item.is_visible():
            print("Error: React Tutorial should be hidden")
            exit(1)

        print("Search 'PHP' verified")

        # 2. Select All (Filtered)
        # Only visible items should be selected
        page.check('#check-all-topics')
        # Trigger change
        page.evaluate("jQuery('#check-all-topics').trigger('change')")

        # PHP should be checked
        if not php_item.locator('.topic-checkbox').is_checked():
             print("Error: PHP Guide should be checked")
             exit(1)

        # React should NOT be checked (it was hidden)
        if react_item.locator('.topic-checkbox').is_checked():
             print("Error: React Tutorial should NOT be checked (it is hidden)")
             exit(1)

        print("Select All (Filtered) verified")

        # 3. Clear search
        page.fill('#planner-topic-search', '')
        page.evaluate("jQuery('#planner-topic-search').trigger('keyup')")

        if not react_item.is_visible():
             print("Error: React Tutorial should be visible after clear")
             exit(1)

        print("Clear search verified")

        browser.close()

if __name__ == "__main__":
    test_planner_search()
