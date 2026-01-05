
from playwright.sync_api import Page, expect, sync_playwright
import os

def test_mock_planner(page: Page):
    # Load the mock HTML file
    cwd = os.getcwd()
    page.goto(f"file://{cwd}/verification/mock_planner.html")

    # Inject JS files (admin.js and admin-planner.js)
    # We read them from disk to inject them into the page
    with open('ai-post-scheduler/assets/js/admin.js', 'r') as f:
        admin_js = f.read()
    with open('ai-post-scheduler/assets/js/admin-planner.js', 'r') as f:
        planner_js = f.read()

    page.add_script_tag(content=admin_js)
    page.add_script_tag(content=planner_js)

    # 1. Test "Clear" button in Planner
    # Setup some state
    page.fill('#planner-niche', 'My Niche')
    page.evaluate("document.getElementById('planner-results').style.display = 'block'")

    # Click Clear button
    clear_btn = page.locator('#btn-clear-form')

    # First click: Soft confirm
    clear_btn.click()
    expect(clear_btn).to_have_text('Click again to confirm')

    # Second click: Confirm
    clear_btn.click()

    # Assert form is cleared
    expect(page.locator('#planner-niche')).to_have_value('')
    expect(page.locator('#planner-results')).not_to_be_visible()

    # 2. Test "Edit Schedule" button
    # Click Edit
    edit_btn = page.locator('.aips-edit-schedule').first
    edit_btn.click()

    # Assert Modal Open
    modal = page.locator('#aips-schedule-modal')
    expect(modal).to_be_visible()

    # Assert Title is "Edit Schedule"
    expect(page.locator('#aips-schedule-modal-title')).to_have_text('Edit Schedule')

    # Assert fields populated
    expect(page.locator('#schedule_id')).to_have_value('1')
    expect(page.locator('#schedule_topic')).to_have_value('My Topic')

    # Assert start time is cleared
    expect(page.locator('#schedule_start_time')).to_have_value('')

    # Screenshot
    page.screenshot(path="verification/planner_edit_verify.png")

if __name__ == "__main__":
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        try:
            test_mock_planner(page)
            print("Frontend verification passed!")
        except Exception as e:
            print(f"Frontend verification failed: {e}")
        finally:
            browser.close()
