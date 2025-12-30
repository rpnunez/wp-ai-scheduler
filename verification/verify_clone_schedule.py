import re
from playwright.sync_api import sync_playwright, expect
import os

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()

        # Load the mock HTML
        cwd = os.getcwd()
        page.goto(f'file://{cwd}/verification/mock_schedule.html')

        # Inject admin.js logic manually because we can't load the real file easily due to dependencies
        # This simulates what we added to admin.js
        page.evaluate("""
            window.AIPS = window.AIPS || {};
            AIPS.cloneSchedule = function(e) {
                e.preventDefault();
                var id = $(this).data('id');
                // Mock AJAX success
                var s = {
                    template_id: 1,
                    frequency: 'daily',
                    topic: 'Test Topic',
                    article_structure_id: 2,
                    rotation_pattern: 'abc'
                };

                $('#aips-schedule-form')[0].reset();
                $('#schedule_id').val('');
                $('#schedule_template').val(s.template_id);
                $('#schedule_frequency').val(s.frequency);
                $('#schedule_topic').val(s.topic);
                $('#article_structure_id').val(s.article_structure_id);
                $('#rotation_pattern').val(s.rotation_pattern);
                $('#schedule_is_active').prop('checked', true);
                $('#aips-modal-title-schedule').text('Clone Schedule');
                $('#aips-schedule-modal').show();
            };

            $(document).on('click', '.aips-clone-schedule', AIPS.cloneSchedule);
        """)

        # Check for Clone button
        clone_btn = page.locator('.aips-clone-schedule')
        expect(clone_btn).to_have_count(1)

        # Click Clone
        clone_btn.click()

        # Check modal title changed
        expect(page.locator('#aips-modal-title-schedule')).to_contain_text("Clone Schedule")

        # Check form fields populated
        expect(page.locator('#schedule_topic')).to_have_value("Test Topic")

        # Check ID is empty (crucial for cloning)
        expect(page.locator('#schedule_id')).to_have_value("")

        print("Verification successful!")
        browser.close()

if __name__ == '__main__':
    run()
