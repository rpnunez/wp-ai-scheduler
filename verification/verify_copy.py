from playwright.sync_api import sync_playwright
import os
import time

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    # Grant permissions just in case, though we are mocking
    context = browser.new_context()
    context.grant_permissions(['clipboard-read', 'clipboard-write'])
    page = context.new_page()

    # Capture console logs
    page.on("console", lambda msg: print(f"PAGE LOG: {msg.text}"))

    # Load the verification file
    file_path = f"file://{os.path.abspath('verification/verify_copy_clipboard.html')}"
    page.goto(file_path)

    # Check initial state
    initial_html = page.inner_html('.aips-copy-btn')
    print(f"Initial HTML: {initial_html}")

    # Test 1: Single Click
    print("\n--- Test 1: Single Click ---")
    page.click('.aips-copy-btn')

    # Wait for the clipboard write
    try:
        page.wait_for_function("window.lastCopiedText !== undefined", timeout=5000)
    except Exception as e:
        print("Timeout waiting for window.lastCopiedText")

    copied_text = page.evaluate("window.lastCopiedText")

    if copied_text == "{{date}}":
        print("SUCCESS: Copied '{{date}}' to clipboard.")
    else:
        print(f"FAILURE: Expected '{{date}}', got '{copied_text}'")

    # Check visual feedback
    try:
        page.wait_for_function("document.querySelector('.aips-copy-btn').innerText === 'Copied!'", timeout=2000)
        print("SUCCESS: Visual feedback received.")
    except:
        btn_text = page.inner_text('.aips-copy-btn')
        print(f"FAILURE: Expected button text 'Copied!', got '{btn_text}'")

    # Test 2: Double Click (Re-entrancy)
    print("\n--- Test 2: Re-entrancy ---")
    # Button should currently say "Copied!" and have is-copying flag

    # Click again immediately
    page.click('.aips-copy-btn')

    # Check if re-entrancy was blocked (we logged it in verification script)
    is_blocked = page.evaluate("window.reentrancyBlocked === true")
    if is_blocked:
        print("SUCCESS: Re-entrancy blocked.")
    else:
        print("FAILURE: Re-entrancy NOT blocked.")

    # Test 3: Restoration
    print("\n--- Test 3: Restoration ---")
    print("Waiting for restoration (2s)...")
    # Wait for the reset
    try:
        page.wait_for_function("document.querySelector('.aips-copy-btn').innerHTML.includes('span')", timeout=3000)
        print("SUCCESS: Button content restored.")
    except:
        curr_html = page.inner_html('.aips-copy-btn')
        print(f"FAILURE: Button not restored. Current HTML: {curr_html}")

    # Check if is-copying flag is cleared (we can check by clicking again and seeing if it writes)
    # Or check data attribute if jQuery exposes it to DOM (it usually doesn't sync perfectly to DOM attributes)
    # We'll rely on the restored HTML as proof resetBtn ran.

    browser.close()

with sync_playwright() as playwright:
    run(playwright)
