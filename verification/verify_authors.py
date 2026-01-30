import os
from playwright.sync_api import sync_playwright, expect

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    page = browser.new_page()

    # Load the local HTML file
    file_path = os.path.abspath("verification/mock_authors.html")
    page.goto(f"file://{file_path}")

    # Check initial state
    authors_tab = page.locator("#tab-authors-list")
    queue_tab = page.locator("#tab-generation-queue")

    expect(authors_tab).to_have_attribute("aria-selected", "true")
    expect(queue_tab).to_have_attribute("aria-selected", "false")
    expect(page.locator("#authors-list-tab")).to_be_visible()
    expect(page.locator("#generation-queue-tab")).not_to_be_visible()

    print("Initial state verified.")

    # Switch tab
    queue_tab.click()

    # Check updated state
    expect(authors_tab).to_have_attribute("aria-selected", "false")
    expect(queue_tab).to_have_attribute("aria-selected", "true")
    expect(page.locator("#authors-list-tab")).not_to_be_visible()
    expect(page.locator("#generation-queue-tab")).to_be_visible()

    print("Tab switch verified.")

    # Screenshot
    page.screenshot(path="verification/authors_tabs.png")
    print("Screenshot saved to verification/authors_tabs.png")

    browser.close()

with sync_playwright() as playwright:
    run(playwright)
