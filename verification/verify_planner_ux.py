from playwright.sync_api import sync_playwright
import os

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    page = browser.new_page()
    page.goto("file://" + os.path.abspath("verification/mock_planner.html"))

    # Initial State
    print("Initial Text: " + page.inner_text("#btn-clear-topics"))
    page.screenshot(path="verification/step1_initial.png")

    # Click 1: Should ask for confirmation
    page.click("#btn-clear-topics")
    print("After Click 1: " + page.inner_text("#btn-clear-topics"))
    page.screenshot(path="verification/step2_confirm.png")

    # Verify text changed
    assert "Click again" in page.inner_text("#btn-clear-topics")

    # Click 2: Should clear
    page.click("#btn-clear-topics")
    print("After Click 2: " + page.inner_text("#btn-clear-topics"))
    page.screenshot(path="verification/step3_cleared.png")

    # Verify cleared text and empty list
    assert "Cleared!" in page.inner_text("#btn-clear-topics")
    # Verify list is empty
    assert page.inner_html("#topics-list") == ""

    # Wait for reset
    page.wait_for_timeout(2000)
    print("After Reset: " + page.inner_text("#btn-clear-topics"))
    page.screenshot(path="verification/step4_reset.png")

    assert "Clear List" in page.inner_text("#btn-clear-topics")

    browser.close()

with sync_playwright() as playwright:
    run(playwright)
