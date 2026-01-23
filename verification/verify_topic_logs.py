from playwright.sync_api import sync_playwright, expect
import os

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    page = browser.new_page()

    # Route AJAX requests
    def handle_ajax(route):
        # Check if post data exists and contains our action
        post_data = route.request.post_data
        if post_data and "action=aips_get_topic_logs" in post_data:
            route.fulfill(
                status=200,
                content_type="application/json",
                body='{"success": true, "data": {"logs": [{"action": "topic_approved", "user_name": "Test User", "created_at": "2023-10-27 10:00:00", "notes": "Approved via UI"}]}}'
            )
        else:
            route.continue_()

    page.route("**/admin-ajax.php", handle_ajax)

    # Load the mock HTML file
    cwd = os.getcwd()
    page.goto(f"file://{cwd}/verification/mock_authors.html")

    # Click the button
    page.click(".aips-view-topic-log")

    # Wait for modal to appear and content to load
    page.wait_for_selector("#aips-topic-logs-modal", state="visible")
    page.wait_for_selector("#aips-topic-logs-content table", state="visible")

    # Take screenshot
    page.screenshot(path="verification/topic_logs_modal.png")

    # Assertions
    expect(page.locator("#aips-topic-logs-modal")).to_be_visible()
    expect(page.locator("text=Topic History Log")).to_be_visible()
    expect(page.locator("text=Test User")).to_be_visible()
    # "topic_approved" might be transformed by CSS or JS, but let's check text
    expect(page.locator("text=topic_approved")).to_be_visible()

    print("Verification successful!")
    browser.close()

with sync_playwright() as playwright:
    run(playwright)
