import os
from playwright.sync_api import sync_playwright, expect

def test_refactor(page):
    # Load the mock HTML
    page.goto(f"file://{os.path.abspath('verification/verify_refactor.html')}")

    # Inject scripts in order
    # 1. admin.js (Core)
    with open('ai-post-scheduler/assets/js/admin.js', 'r') as f:
        page.add_script_tag(content=f.read())

    # 2. admin-templates.js
    with open('ai-post-scheduler/assets/js/admin-templates.js', 'r') as f:
        page.add_script_tag(content=f.read())

    # 3. admin-schedules.js
    with open('ai-post-scheduler/assets/js/admin-schedules.js', 'r') as f:
        page.add_script_tag(content=f.read())

    # Verify AIPS object exists
    exists = page.evaluate("typeof window.AIPS !== 'undefined'")
    assert exists, "window.AIPS should exist"

    # Verify Methods are attached
    has_template_method = page.evaluate("typeof window.AIPS.openTemplateModal === 'function'")
    assert has_template_method, "openTemplateModal should be a function on AIPS"

    has_schedule_method = page.evaluate("typeof window.AIPS.openScheduleModal === 'function'")
    assert has_schedule_method, "openScheduleModal should be a function on AIPS"

    has_core_method = page.evaluate("typeof window.AIPS.copyToClipboard === 'function'")
    assert has_core_method, "copyToClipboard should be a function on AIPS (from admin.js)"

    # Test Interaction: Open Template Modal
    page.click('.aips-add-template-btn')
    expect(page.locator('#aips-template-modal')).to_be_visible()

    # Verify form reset (e.g., featured_image_source set to ai_prompt)
    val = page.eval_on_selector('#featured_image_source', 'el => el.value')
    assert val == 'ai_prompt'

    # Close Modal
    page.evaluate("$('#aips-template-modal').hide()")

    # Test Interaction: Open Schedule Modal
    page.click('.aips-add-schedule-btn')
    expect(page.locator('#aips-schedule-modal')).to_be_visible()

    # Test Template Search (debounce might not apply if I moved it, let's check)
    # The listener is on 'keyup search'.
    page.fill('#aips-template-search', 'Another')
    page.keyboard.up('r') # trigger keyup

    # Verify filtering (should hide first row)
    expect(page.locator('.aips-templates-list tr').first).to_be_hidden()
    expect(page.locator('.aips-templates-list tr').nth(1)).to_be_visible()

    # Screenshot
    page.screenshot(path='verification/refactor_verification.png')

if __name__ == "__main__":
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        try:
            test_refactor(page)
            print("Verification Passed")
        except Exception as e:
            print(f"Verification Failed: {e}")
            exit(1)
        finally:
            browser.close()
