import sys
from playwright.sync_api import sync_playwright
import os

def run_verification():
    file_path = os.path.abspath("verification/mock_xss_test_fixed.html")

    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()
        page.goto(f"file://{file_path}")

        # Check if the img tag was inserted into the DOM (proving HTML injection)
        img_exists = page.evaluate("document.querySelector('#aips-details-summary img') !== null")

        # Check HTML content
        summary_html = page.inner_html("#aips-details-summary")
        print(f"Summary HTML: {summary_html}")

        if img_exists:
            print("FAILURE: <img> tag still injected")
            browser.close()
            return False
        else:
            print("SUCCESS: <img> tag NOT injected (escaped)")
            # Verify the escaped text is present
            escaped_tag_present = "&lt;img" in summary_html or "&amp;lt;img" in summary_html
            if escaped_tag_present:
                 print("Verified: Escaped tag found in HTML source")
            else:
                 print("Warning: Escaped tag not found in HTML source (check escaping logic)")

            browser.close()
            return True

if __name__ == "__main__":
    is_fixed = run_verification()
    if is_fixed:
        sys.exit(0)
    else:
        sys.exit(1)
