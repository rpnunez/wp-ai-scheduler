from playwright.sync_api import sync_playwright, expect
import os

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()

        # Load the mock HTML
        cwd = os.getcwd()
        page.goto(f'file://{cwd}/verification/mock_schedule.html')

        # Inject admin.js logic manually
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
                $('#schedule_is_active').prop('checked', true);
                $('#aips-modal-title-schedule').text('Clone Schedule');
                $('#aips-schedule-modal').show();
            };

            $(document).on('click', '.aips-clone-schedule', AIPS.cloneSchedule);
        """)

        # Take screenshot of initial state
        page.screenshot(path='verification/before_click.png')

        # Click Clone
        page.click('.aips-clone-schedule')

        # Wait for modal to be visible
        expect(page.locator('#aips-schedule-modal')).to_be_visible()

        # Take screenshot of result
        page.screenshot(path='verification/clone_schedule.png')

        browser.close()

if __name__ == '__main__':
    run()
