
import os
from playwright.sync_api import sync_playwright, expect

def verify_sections_search():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Monitor logs
        page.on("console", lambda msg: print(f"PAGE LOG: {msg.text}"))
        page.on("pageerror", lambda err: print(f"PAGE ERROR: {err}"))

        # Load the mock HTML file
        mock_file_path = os.path.abspath("verification/mock_sections_search.html")
        page.goto(f"file://{mock_file_path}")

        # Verify initial state
        expect(page.locator(".aips-sections-list tbody tr")).to_have_count(4)

        # Test 1: Search for "Intro" (Name)
        print("Testing 'Intro'...")
        page.focus("#aips-section-search")
        page.keyboard.type("Intro")
        page.wait_for_timeout(500) # Wait for JS execution

        # Should show 1 row
        expect(page.locator(".aips-sections-list tbody tr:visible")).to_have_count(1)
        expect(page.locator(".aips-sections-list tbody tr:visible .column-name")).to_contain_text("Introduction")
        expect(page.locator("#aips-section-search-clear")).to_be_visible()

        # Clear via keyboard
        page.fill("#aips-section-search", "")
        page.keyboard.press("Backspace") # Trigger keyup
        page.wait_for_timeout(200)

        # Test 2: Search for "faq" (Key)
        print("Testing 'faq'...")
        page.focus("#aips-section-search")
        page.keyboard.type("faq")
        page.wait_for_timeout(200)

        expect(page.locator(".aips-sections-list tbody tr:visible")).to_have_count(1)
        expect(page.locator(".aips-sections-list tbody tr:visible .column-key")).to_contain_text("faq")

        page.fill("#aips-section-search", "")
        page.keyboard.press("Backspace") # Trigger keyup
        page.wait_for_timeout(200)

        # Test 3: Search for "instructions" (Description)
        print("Testing 'instructions'...")
        page.focus("#aips-section-search")
        page.keyboard.type("instructions")
        page.wait_for_timeout(200)

        expect(page.locator(".aips-sections-list tbody tr:visible")).to_have_count(1)
        expect(page.locator(".aips-sections-list tbody tr:visible .column-name")).to_contain_text("Step by Step")

        # Test 4: No results
        print("Testing 'xyz123'...")
        page.fill("#aips-section-search", "")
        page.focus("#aips-section-search")
        page.keyboard.type("xyz123")
        page.wait_for_timeout(200)

        expect(page.locator(".aips-sections-list")).not_to_be_visible()
        expect(page.locator("#aips-section-search-no-results")).to_be_visible()

        # Test 5: Clear search via button
        print("Testing clear button...")
        page.click("#aips-section-search-clear")
        page.wait_for_timeout(200)

        expect(page.locator(".aips-sections-list")).to_be_visible()
        expect(page.locator(".aips-sections-list tbody tr:visible")).to_have_count(4)
        expect(page.locator("#aips-section-search")).to_be_empty()

        # Screenshot
        page.focus("#aips-section-search")
        page.keyboard.type("Intro")
        page.wait_for_timeout(200)
        page.screenshot(path="verification/verification_sections_search.png")

        print("Verification successful!")
        browser.close()

if __name__ == "__main__":
    verify_sections_search()
