import sys
from playwright.sync_api import sync_playwright
import os

def run_verification():
    file_path = os.path.abspath("verification/mock_xss_test.html")

    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()
        page.goto(f"file://{file_path}")

        # Check if the img tag was inserted into the DOM (proving HTML injection)
        img_exists = page.evaluate("document.querySelector('#aips-details-summary img') !== null")

        if img_exists:
            print("VULNERABILITY CONFIRMED: <img> tag injected via generated_title")
        else:
            print("SAFE: <img> tag NOT injected")

        # Check if onerror would fire (we can check if window.xssTriggered becomes true)
        # Note: onerror might not fire in this context depending on CSP or timing, but DOM injection is enough proof.
        # But we can try to force it or just rely on DOM check.

        # Let's check HTML content
        summary_html = page.inner_html("#aips-details-summary")
        print(f"Summary HTML: {summary_html}")

        browser.close()

        if img_exists:
            return True
        return False

if __name__ == "__main__":
    is_vulnerable = run_verification()
    if is_vulnerable:
        sys.exit(0) # Exit 0 to indicate script ran successfully and found what we expected (for this step)
    else:
        print("Failed to reproduce vulnerability")
        sys.exit(1)
