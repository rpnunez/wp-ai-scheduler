from playwright.sync_api import sync_playwright, expect
import threading
import http.server
import socketserver
import os
import time

# Serve the verification directory
PORT = 8000
DIRECTORY = "."

class Handler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=DIRECTORY, **kwargs)

def run_server():
    with socketserver.TCPServer(("", PORT), Handler) as httpd:
        print(f"Serving at port {PORT}")
        httpd.serve_forever()

def verify_bulk_delete():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Navigate to the mock page
        page.goto(f"http://localhost:{PORT}/verification/mock_schedule.html")

        # Wait for JS to load
        page.wait_for_load_state("networkidle")

        # Check initial state: Button disabled
        delete_btn = page.locator("#aips-delete-selected-schedules-btn")
        expect(delete_btn).to_be_disabled()

        # Act: Click "Select All"
        select_all = page.locator("#cb-select-all-schedules")
        select_all.click()

        # Assert: All checkboxes checked
        expect(page.locator("#cb-select-schedule-1")).to_be_checked()
        expect(page.locator("#cb-select-schedule-2")).to_be_checked()

        # Assert: Button enabled
        expect(delete_btn).to_be_enabled()

        # Act: Uncheck one
        page.locator("#cb-select-schedule-1").uncheck()

        # Assert: Select All unchecked
        expect(select_all).not_to_be_checked()

        # Assert: Button still enabled (one selected)
        expect(delete_btn).to_be_enabled()

        # Act: Uncheck the other one
        page.locator("#cb-select-schedule-2").uncheck()

        # Assert: Button disabled
        expect(delete_btn).to_be_disabled()

        # Act: Check one manually
        page.locator("#cb-select-schedule-1").check()
        expect(delete_btn).to_be_enabled()

        # Screenshot
        page.screenshot(path="verification/bulk_delete_verified.png")
        print("Verification successful, screenshot saved.")

        browser.close()

if __name__ == "__main__":
    # Start server in background
    server_thread = threading.Thread(target=run_server)
    server_thread.daemon = True
    server_thread.start()

    # Give server a moment to start
    time.sleep(1)

    try:
        verify_bulk_delete()
    except Exception as e:
        print(f"Verification failed: {e}")
        exit(1)
