from playwright.sync_api import sync_playwright, expect
import os
import http.server
import socketserver
import threading
import time

PORT = 8000

def start_server():
    # Serve from current directory (repo root)
    Handler = http.server.SimpleHTTPRequestHandler
    # Allow address reuse to avoid "Address already in use" errors on quick restarts
    socketserver.TCPServer.allow_reuse_address = True
    with socketserver.TCPServer(("", PORT), Handler) as httpd:
        print(f"Serving at port {PORT}")
        httpd.serve_forever()

def test_modal_focus():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Start server in background
        server_thread = threading.Thread(target=start_server, daemon=True)
        server_thread.start()
        time.sleep(2) # Wait for server

        url = f"http://localhost:{PORT}/verification/mock_admin.html"
        print(f"Navigating to {url}")
        page.goto(url)

        # Click the button to open modal
        page.click('.aips-add-template-btn')

        # Wait for modal to be visible
        modal = page.locator('#aips-template-modal')
        expect(modal).to_be_visible()

        # Wait a bit for the focus timeout (100ms in JS)
        page.wait_for_timeout(500)

        # Check active element
        active_element_id = page.evaluate("document.activeElement.id")
        print(f"Active element ID: {active_element_id}")

        # Take screenshot
        page.screenshot(path="verification/verification.png")

        # Assert
        # The first focusable element in #aips-template-modal form is #template_name (since #template_id is hidden)
        if active_element_id == 'template_name':
            print("SUCCESS: Focus is on #template_name")
        else:
            print(f"FAILURE: Focus is on {active_element_id}, expected template_name")
            exit(1)

        browser.close()

if __name__ == "__main__":
    test_modal_focus()
