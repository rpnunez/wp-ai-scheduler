from playwright.sync_api import sync_playwright
import os

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()

        cwd = os.getcwd()
        page.goto(f"file://{cwd}/verification/mock_calendar.html")

        # Trigger Render via View Switch to ensure JS runs
        page.click('button[data-view="calendar"]')

        try:
            page.wait_for_selector('.aips-event', timeout=5000)
            print("PASS: Events rendered.")
        except:
            print("FAIL: Events did not render.")

        # Screenshot
        page.screenshot(path="verification/calendar_view.png")
        print("Screenshot saved to verification/calendar_view.png")

        browser.close()

if __name__ == '__main__':
    run()
