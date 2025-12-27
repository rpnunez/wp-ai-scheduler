
import os
from playwright.sync_api import sync_playwright

def verify_seeder_frontend():
    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()

        # Load the mock HTML file
        cwd = os.getcwd()
        page.goto(f"file://{cwd}/tests/mock_seeder.html")

        # Verify initial state
        print("Verifying initial state...")
        assert page.is_visible("#aips-seeder-form")
        assert page.input_value("#seeder-voices") == "0"

        # Verify new field exists
        print("Verifying keywords field...")
        assert page.is_visible("#seeder-keywords")

        # Fill the form
        print("Filling form...")
        page.fill("#seeder-keywords", "wordpress, plugin development")
        page.fill("#seeder-voices", "5")

        # Mock window.confirm to always return true
        page.evaluate("window.confirm = function() { return true; }")

        # Setup request interception to mock AJAX
        def handle_route(route):
            request = route.request
            post_data = request.post_data
            print(f"Intercepted AJAX: {post_data}")

            # Verify keywords are sent
            if "keywords=wordpress%2C+plugin+development" not in post_data:
                 print("WARNING: Keywords missing from request!")

            if "type=voices" in post_data:
                route.fulfill(status=200, body='{"success": true, "data": {"message": "Created 5 voices"}}', headers={'Content-Type': 'application/json'})
            else:
                route.fulfill(status=400, body='{"success": false, "data": {"message": "Unknown type"}}', headers={'Content-Type': 'application/json'})

        # Intercept the request.
        page.route("**/*mock-ajax*", handle_route)

        # Click submit
        print("Clicking submit...")
        page.click("#aips-seeder-submit")

        # Wait for results
        print("Waiting for results...")
        try:
            page.wait_for_selector("#aips-seeder-results", state="visible")
            page.wait_for_selector("#aips-seeder-log div:has-text('Created 5 voices')", timeout=5000)
            page.wait_for_selector("#aips-seeder-log div:has-text('All Done!')", timeout=5000)
        except Exception as e:
            print(f"Failed to find text: {e}")
            print(page.inner_html("#aips-seeder-log"))
            raise e

        # Take screenshot
        screenshot_path = f"{cwd}/verification_seeder_updated.png"
        page.screenshot(path=screenshot_path)
        print(f"Screenshot saved to {screenshot_path}")

        browser.close()
        return screenshot_path

if __name__ == "__main__":
    verify_seeder_frontend()
