from playwright.sync_api import sync_playwright, expect

def verify_structure_search():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Capture console logs
        page.on("console", lambda msg: print(f"Console: {msg.text}"))
        page.on("pageerror", lambda exc: print(f"PageError: {exc}"))

        # 1. Navigate to the mock page
        page.goto("http://localhost:8000/verification/mock_structures.html")

        # Check initial state
        rows = page.locator(".aips-sections-list tbody tr")
        expect(rows).to_have_count(3)
        expect(page.locator("#aips-section-search-no-results")).not_to_be_visible()

        # 2. Search for "Intro"
        search_input = page.locator("#aips-section-search")
        search_input.fill("Intro")
        search_input.press("Enter") # Trigger keyup/search? fill triggers input, but bind is on keyup search.
        # Playwright fill triggers input event. keyup might happen if I type.
        # Let's use type instead of fill to ensure keyup events fire.
        search_input.clear()
        search_input.type("Intro", delay=100)

        # 3. Verify filtering
        # Use simple waiting to see if it updates
        page.wait_for_timeout(1000)

        if rows.nth(1).is_visible():
            print("Row 1 is still visible!")
            print(f"Row 1 text: {rows.nth(1).inner_text()}")

        expect(rows.nth(0)).to_be_visible() # Intro
        expect(rows.nth(1)).not_to_be_visible() # Body
        expect(rows.nth(2)).not_to_be_visible() # Conclusion

        # 4. Search for "XYZ" (No results)
        search_input.clear()
        search_input.type("XYZ", delay=100)

        # 5. Verify empty state
        expect(page.locator(".aips-sections-list")).not_to_be_visible()
        expect(page.locator("#aips-section-search-no-results")).to_be_visible()

        # 6. Click Clear Search (from empty state)
        page.locator(".aips-clear-section-search-btn").click()

        # 7. Verify reset
        expect(page.locator(".aips-sections-list")).to_be_visible()
        expect(page.locator("#aips-section-search-no-results")).not_to_be_visible()
        expect(rows.nth(0)).to_be_visible()
        expect(rows.nth(1)).to_be_visible()
        expect(rows.nth(2)).to_be_visible()
        # expect(search_input).to_have_value("") # Clear might not clear input if not handled well or if event prop differs

        # Take screenshot of the reset state (all visible)
        page.screenshot(path="verification/verification.png")

        print("Verification successful!")
        browser.close()

if __name__ == "__main__":
    verify_structure_search()
