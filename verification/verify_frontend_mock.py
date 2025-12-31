from playwright.sync_api import sync_playwright, expect
import os

# Read the HTML content
with open('verification/mock_schedule.html', 'r') as f:
    html_content = f.read()

# Read the JS content
with open('ai-post-scheduler/assets/js/admin.js', 'r') as f:
    js_content = f.read()

# Inject JS into HTML
full_html = html_content.replace('<!-- We will inject admin.js content here in the python script -->', f'<script>{js_content}</script>')

# Write the final HTML file
final_html_path = os.path.abspath('verification/final_mock_schedule.html')
with open(final_html_path, 'w') as f:
    f.write(full_html)

def test_clone_schedule():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Load the mock page
        page.goto(f'file://{final_html_path}')

        # Click the Clone button
        page.click('.aips-clone-schedule')

        # Wait for modal to appear
        expect(page.locator('#aips-schedule-modal')).to_be_visible()

        # Check if fields are populated
        expect(page.locator('#schedule_id')).to_have_value('') # Should be empty
        expect(page.locator('#schedule_template')).to_have_value('5')
        expect(page.locator('#schedule_frequency')).to_have_value('weekly')
        expect(page.locator('#schedule_topic')).to_have_value('Test Topic')
        expect(page.locator('#schedule_is_active')).to_be_checked()

        # Check modal title
        expect(page.locator('#aips-schedule-modal .aips-modal-header h2')).to_have_text('Clone Schedule')

        # Take screenshot
        page.screenshot(path='verification/clone_schedule.png')

        print("Verification successful!")
        browser.close()

if __name__ == "__main__":
    test_clone_schedule()
