
from playwright.sync_api import sync_playwright
import os
import time

def verify_structures_copy():
    # Get absolute path to the mock file
    mock_file_path = os.path.abspath("verification/mock_structures_copy.html")
    file_url = f"file://{mock_file_path}"

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        # Grant permissions if possible, though file:// is tricky
        context = browser.new_context(permissions=["clipboard-write"])
        page = context.new_page()

        # Override clipboard.writeText to succeed immediately
        page.add_init_script("""
            if (navigator.clipboard) {
                navigator.clipboard.writeText = (text) => { return Promise.resolve(); };
            } else {
                navigator.clipboard = {
                    writeText: (text) => { return Promise.resolve(); }
                };
            }
        """)

        page.on("console", lambda msg: print(f"Console: {msg.text}"))

        print(f"Navigating to {file_url}")
        page.goto(file_url)

        # Check if table exists
        page.wait_for_selector(".wp-list-table")

        # Find the first copy button
        copy_btn = page.locator(".aips-copy-btn").first

        # Click it
        print("Clicking copy button...")
        copy_btn.click()

        # Wait a bit
        time.sleep(1.0)

        # Verify icon changed
        icon = copy_btn.locator(".dashicons")
        class_attr = icon.get_attribute("class")
        print(f"Icon class after click: {class_attr}")

        if "dashicons-yes" in class_attr:
            print("✅ Verification Passed: Icon changed to checkmark.")
        else:
            print(f"❌ Verification Failed: Icon class is {class_attr}")

        screenshot_path = "verification/structures_copy_verified.png"
        page.screenshot(path=screenshot_path)
        print(f"Screenshot saved to {screenshot_path}")

        browser.close()

if __name__ == "__main__":
    verify_structures_copy()
