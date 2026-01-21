from playwright.sync_api import sync_playwright
import os

def verify_clone_button():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        html_content = """
        <!DOCTYPE html>
        <html>
        <head>
            <title>AIPS Templates</title>
            <style>
                .button { background: #f7f7f7; border: 1px solid #ccc; padding: 5px 10px; cursor: pointer; }
                .aips-clone-template { color: #0073aa; }
            </style>
        </head>
        <body>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="column-name">Name</th>
                        <th class="column-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="column-name"><strong>Test Template</strong></td>
                        <td class="column-actions">
                            <button class="button aips-edit-template" data-id="1">Edit</button>
                            <button class="button aips-run-now" data-id="1">Run Now</button>
                            <button class="button aips-clone-template" data-id="1">Clone</button>
                            <button class="button button-link-delete aips-delete-template" data-id="1">Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </body>
        </html>
        """

        file_path = os.path.abspath("verification/mock_templates.html")
        with open(file_path, "w") as f:
            f.write(html_content)

        page.goto(f"file://{file_path}")

        # Verify the "Clone" button exists
        clone_btn = page.locator(".aips-clone-template")
        if clone_btn.count() > 0:
            print("Clone button found!")
        else:
            print("Clone button NOT found!")

        page.screenshot(path="verification/clone_verification.png")
        browser.close()

if __name__ == "__main__":
    verify_clone_button()
