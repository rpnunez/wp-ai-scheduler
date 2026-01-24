from playwright.sync_api import sync_playwright, expect

def test_modal_focus():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        page.goto("http://localhost:8000/verification/mock_admin.html")

        # Click the add template button
        page.click(".aips-add-template-btn")

        # Wait for the modal to appear
        expect(page.locator("#aips-template-modal")).to_be_visible()

        # Wait a bit for the focus timeout
        page.wait_for_timeout(500)

        # Check if the input has focus
        focused_element = page.evaluate("document.activeElement.id")
        print(f"Focused element ID: {focused_element}")

        if focused_element == "template_name":
            print("SUCCESS: Focus is on template_name")
        else:
            print(f"FAILURE: Focus is on {focused_element}")
            exit(1)

        page.screenshot(path="verification/focus_test.png")
        browser.close()

if __name__ == "__main__":
    test_modal_focus()
