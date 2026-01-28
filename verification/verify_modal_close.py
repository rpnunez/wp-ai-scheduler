from playwright.sync_api import sync_playwright, expect
import os

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Use absolute path
        cwd = os.getcwd()
        page.goto(f"file://{cwd}/verification/mock_modal.html")

        # Select the header close button
        close_btn = page.locator(".aips-modal-header .aips-modal-close")

        # Verify it's visible
        expect(close_btn).to_be_visible()

        # Verify type attribute
        type_attr = close_btn.get_attribute("type")
        if type_attr != "button":
            print(f"FAILURE: Expected type='button', found type='{type_attr}'")
            exit(1)

        print("SUCCESS: Header close button has type='button'")

        # Select the footer cancel button (also has class aips-modal-close)
        cancel_btn = page.locator(".aips-modal-footer .aips-modal-close")
        expect(cancel_btn).to_be_visible()

        type_attr_cancel = cancel_btn.get_attribute("type")
        if type_attr_cancel != "button":
             print(f"FAILURE: Expected type='button' for cancel button, found type='{type_attr_cancel}'")
             exit(1)

        print("SUCCESS: Footer cancel button has type='button'")

        page.screenshot(path="verification/modal_verification.png")
        browser.close()

if __name__ == "__main__":
    run()
