
import os
from playwright.sync_api import sync_playwright, expect

def verify_planner(page):
    # Load the mock HTML
    # We need to inject the admin-planner.js content manually since it's a file path

    # 1. Read the JS file
    with open('ai-post-scheduler/assets/js/admin-planner.js', 'r') as f:
        js_content = f.read()

    # 2. Load the HTML
    cwd = os.getcwd()
    page.goto(f"file://{cwd}/verification/mock_planner.html")

    # 3. Inject the JS
    page.add_script_tag(content=js_content)

    # --- Test Case 1: Manual Topic Parsing & Truncation ---
    # Create a long topic > 500 chars
    long_topic = "A" * 600
    page.fill('#planner-manual-topics', f"{long_topic}\nShort Topic")

    # Click parse
    page.click('#btn-parse-manual')

    # Expect results to show
    expect(page.locator('#planner-results')).to_be_visible()

    # Check truncation
    # The first input should have value length 500
    first_input = page.locator('.topic-text-input').first
    val = first_input.input_value()
    assert len(val) == 500, f"Expected length 500, got {len(val)}"

    # --- Test Case 2: Copy Topics (UX) ---
    # Mock clipboard
    page.context.grant_permissions(['clipboard-read', 'clipboard-write'])

    # Click Copy
    page.click('#btn-copy-topics')

    # Expect button text change (Wizard UX)
    expect(page.locator('#btn-copy-topics')).to_have_text("Copied!")

    # Wait for revert (2s in code)
    page.wait_for_timeout(2100)
    expect(page.locator('#btn-copy-topics')).to_have_text("Copy Selected")

    # Take screenshot
    page.screenshot(path="verification/verify_planner_ux.png")
    print("Verification complete. Screenshot saved.")

if __name__ == "__main__":
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        try:
            verify_planner(page)
        except Exception as e:
            print(f"Error: {e}")
            page.screenshot(path="verification/error.png")
        finally:
            browser.close()
