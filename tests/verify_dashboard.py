from playwright.sync_api import sync_playwright
import os

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    page = browser.new_page()

    # Load the mock HTML file
    file_path = os.path.abspath("tests/mock_dashboard.html")
    page.goto(f"file://{file_path}")

    # 1. Verify Metrics tab is active initially
    assert page.is_visible("#aips-dashboard-metrics")
    assert not page.is_visible("#aips-dashboard-logs")

    # 2. Click Logs tab and verify switch
    page.click("a[data-target='logs']")
    assert not page.is_visible("#aips-dashboard-metrics")
    assert page.is_visible("#aips-dashboard-logs")

    # 3. Click Automation tab and verify switch
    page.click("a[data-target='automation']")
    assert page.is_visible("#aips-dashboard-automation")

    # 4. Mock AJAX for Fetch Logs
    page.route("**/wp-admin/admin-ajax.php", lambda route: route.fulfill(
        status=200,
        content_type="application/json",
        body='{"success": true, "data": {"logs": ["Log Entry 1", "Log Entry 2"]}}'
    ))

    page.click("a[data-target='logs']")
    page.click("#aips-fetch-logs")

    # Verify logs are displayed
    page.wait_for_selector("#aips-log-viewer:has-text('Log Entry 1')")
    print("Logs fetched successfully.")

    browser.close()

with sync_playwright() as playwright:
    run(playwright)
