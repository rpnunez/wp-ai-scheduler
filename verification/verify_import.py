from playwright.sync_api import sync_playwright
import os

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Load the mock HTML
        cwd = os.getcwd()
        page.goto(f"file://{cwd}/verification/mock_system_status.html")

        # Check if Data Management card exists
        if not page.is_visible("text=Data Management"):
            print("Data Management card not found")
            exit(1)

        # Check for Export and Import forms
        if not page.is_visible("text=Export Data"):
            print("Export Data section not found")
            exit(1)

        if not page.is_visible("text=Import Data"):
            print("Import Data section not found")
            exit(1)

        # Handle Confirm Dialog
        # We need to setup the listener before triggering the event

        # Let's set a file first to test the confirm dialog
        with open('verification/dummy.sql', 'w') as f:
            f.write('DUMMY')

        page.set_input_files('input[name="import_file"]', 'verification/dummy.sql')

        # Now click import
        # We expect a confirm dialog

        def handle_dialog(dialog):
            print(f"Dialog message: {dialog.message}")
            if "Have you made a backup?" in dialog.message:
                print("Confirmation dialog verified.")
                dialog.accept()
            else:
                print("Unexpected dialog message.")
                dialog.dismiss()

        page.on("dialog", handle_dialog)

        page.click(".aips-import-data-btn")

        # Take screenshot
        page.screenshot(path="verification/verification.png")

        browser.close()

if __name__ == "__main__":
    run()
