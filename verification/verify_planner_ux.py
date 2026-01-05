import sys
from playwright.sync_api import sync_playwright
import os

def run_verification():
    file_path = os.path.abspath("verification/mock_planner.html")
    file_url = f"file://{file_path}"

    print(f"Loading {file_url}...")

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        page.goto(file_url)

        # 1. Click Generate Button
        btn = page.locator("#btn-generate-topics")
        print("Clicking Generate Topics button...")
        btn.click()

        # 2. Verify button text changes to "Generating..."
        # Wait slightly for the JS to execute (it's synchronous but good to be safe)
        page.wait_for_timeout(100)
        btn_text = btn.evaluate("el => el.innerText")
        print(f"Button text during generation: '{btn_text}'")
        if "Generating..." not in btn_text:
            print("❌ FAIL: Button text did not change to 'Generating...'")
            sys.exit(1)
        else:
            print("✅ PASS: Button text changed to 'Generating...'")

        # 3. Wait for mock AJAX to complete (500ms in mock)
        page.wait_for_timeout(1000)

        # 4. Verify ARIA status update
        status_text = page.locator("#aips-planner-a11y-status").inner_text()
        print(f"Status text after generation: '{status_text}'")
        if "topics generated successfully" not in status_text:
            print("❌ FAIL: Status text did not update.")
            sys.exit(1)
        else:
            print("✅ PASS: Status text updated correctly.")

        # 5. Verify Focus moved to heading
        # Wait for slideDown animation (400ms)
        page.wait_for_timeout(500)

        focused_id = page.evaluate("document.activeElement.id")
        print(f"Focused element ID: '{focused_id}'")
        if focused_id != "planner-results-heading":
            print("❌ FAIL: Focus did not move to results heading.")
            sys.exit(1)
        else:
            print("✅ PASS: Focus moved to results heading.")

        # 6. Verify button text restored
        final_btn_text = page.locator("#btn-generate-topics").inner_text().strip()
        print(f"Final button text: '{final_btn_text}'")
        if "Generate Topics" not in final_btn_text:
             print("❌ FAIL: Button text was not restored.")
             sys.exit(1)
        else:
             print("✅ PASS: Button text restored.")

        browser.close()

if __name__ == "__main__":
    run_verification()
