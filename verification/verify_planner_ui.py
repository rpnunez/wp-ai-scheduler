from playwright.sync_api import sync_playwright
import os

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()

        # Load mock HTML
        cwd = os.getcwd()
        page.goto(f"file://{cwd}/verification/mock_planner.html")

        # Mock AJAX
        page.route('**/mock-ajax', lambda route: route.fulfill(
            status=200,
            content_type='application/json',
            body='{"success": true, "data": {"topics": ["Topic 1", "Topic 2"], "id": 123, "count": 2}}'
        ))

        # Test 1: Generate Topics
        print("Testing Topic Generation...")
        page.click('#btn-generate-topics')

        # Wait for results
        page.wait_for_selector('#planner-results', state='visible')

        # Check items
        items = page.query_selector_all('.topic-item')
        if len(items) == 2:
            print("PASS: 2 topics rendered.")
        else:
            print(f"FAIL: Expected 2 topics, got {len(items)}")

        # Test 2: Open Matrix
        print("Testing Matrix Modal...")
        page.click('#btn-open-matrix')
        page.wait_for_selector('#aips-matrix-modal', state='visible')
        if page.is_visible('#aips-matrix-modal'):
            print("PASS: Modal opened.")
        else:
            print("FAIL: Modal did not open.")

        # Test 3: Toggle Rules
        print("Testing Custom Rules Toggle...")
        page.select_option('#matrix-frequency', 'custom')
        page.dispatch_event('#matrix-frequency', 'change') # Force change event
        # Wait a bit for slideDown
        page.wait_for_timeout(500)

        if page.is_visible('#matrix-custom-rules'):
            print("PASS: Custom rules visible.")
        else:
            print("FAIL: Custom rules not visible.")

        browser.close()

if __name__ == '__main__':
    run()
