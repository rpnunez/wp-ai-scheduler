from playwright.sync_api import sync_playwright, expect
import os

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    page = browser.new_page()

    # Load the mock HTML file
    filepath = os.path.abspath("verification/mock_research.html")
    page.goto(f"file://{filepath}")

    # 1. Verify ARIA labels
    print("Verifying ARIA labels...")
    expect(page.locator("#filter-niche")).to_have_attribute("aria-label", "Filter by Niche")
    expect(page.locator("#filter-score")).to_have_attribute("aria-label", "Filter by Score")
    print("ARIA labels verified.")

    # 2. Verify Spinner Interaction
    print("Verifying Spinner Interaction...")
    load_btn = page.locator("#load-topics")
    spinner = page.locator(".spinner")

    # Initial state
    expect(spinner).not_to_have_class("is-active")
    expect(load_btn).not_to_be_disabled()

    # Click button
    load_btn.click()

    # Loading state (mocked delay is 2 seconds)
    expect(spinner).to_have_class("spinner is-active")
    expect(load_btn).to_be_disabled()

    # Wait for completion (timeout > 2000ms)
    page.wait_for_timeout(2500)

    # Final state
    expect(spinner).not_to_have_class("is-active") # Use generic check if class order matters, but 'spinner' is always there
    # Actually Playwright checks if the class list *contains* the classes.
    # But checking for absence of 'is-active' is better.
    # Note: .to_have_class is strict about full string matching or regex.
    # Better: expect(spinner).not_to_have_class(re.compile(r"is-active"))

    # Let's just check if it's visible or not based on CSS
    # .is-active makes it visible.
    # In mock CSS: .spinner { visibility: hidden; } .spinner.is-active { visibility: visible; }
    # So we can check visibility.

    # Re-verify initial state logic using visibility which is more robust
    # Wait, the element is always in DOM, just visibility hidden. Playwright .be_visible() checks visibility style.

    # Let's reset and do it again with visibility checks
    page.reload()
    load_btn = page.locator("#load-topics")
    spinner = page.locator(".spinner")

    expect(spinner).not_to_be_visible()
    load_btn.click()
    expect(spinner).to_be_visible()
    expect(load_btn).to_be_disabled()

    page.wait_for_timeout(2500)
    expect(spinner).not_to_be_visible()
    expect(load_btn).not_to_be_disabled()

    print("Spinner interaction verified.")

    # 3. Take Screenshot
    page.screenshot(path="verification/research_filters.png")
    print("Screenshot saved to verification/research_filters.png")

    browser.close()

with sync_playwright() as playwright:
    run(playwright)
