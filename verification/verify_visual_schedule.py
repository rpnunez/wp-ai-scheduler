from playwright.sync_api import sync_playwright
import os

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Mock HTML content
        html_content = """
        <!DOCTYPE html>
        <html>
        <head>
            <title>Schedule Test</title>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        </head>
        <body>
            <div id="aips-schedule-modal-title">Original Title</div>
            <input id="schedule_id" type="hidden">
            <input id="schedule_template" type="text">
            <input id="schedule_frequency" type="text">
            <input id="schedule_start_time" type="text">
            <input id="schedule_topic" type="text">
            <input id="article_structure_id" type="text">
            <input id="rotation_pattern" type="text">
            <div id="aips-schedule-modal" style="display:none; border: 1px solid black; padding: 20px;">
                Modal Content
            </div>

            <table>
                <tr data-schedule-id="123"
                    data-template-id="5"
                    data-frequency="daily"
                    data-next-run="2024-05-30T10:00"
                    data-topic="My Topic"
                    data-article-structure-id="2"
                    data-rotation-pattern="random">
                    <td>
                        <button class="aips-edit-schedule">Edit</button>
                    </td>
                </tr>
            </table>

            <script>
                // Mock AIPS object
                window.AIPS = {
                    editSchedule: function(e) {
                        e.preventDefault();
                        var $row = $(this).closest('tr');
                        $('#schedule_id').val($row.data('schedule-id'));
                        $('#schedule_template').val($row.data('template-id'));
                        $('#schedule_frequency').val($row.data('frequency'));
                        $('#schedule_start_time').val($row.data('next-run'));
                        $('#schedule_topic').val($row.data('topic'));
                        $('#article_structure_id').val($row.data('article-structure-id'));
                        $('#rotation_pattern').val($row.data('rotation-pattern'));
                        $('#aips-schedule-modal-title').text('Edit Schedule');
                        $('#aips-schedule-modal').show();
                    }
                };
                $(document).on('click', '.aips-edit-schedule', window.AIPS.editSchedule);
            </script>
        </body>
        </html>
        """

        # Write to temporary file
        with open("verification/temp_schedule.html", "w") as f:
            f.write(html_content)

        page.goto("file://" + os.path.abspath("verification/temp_schedule.html"))

        # Click edit button
        page.click(".aips-edit-schedule")

        # Screenshot
        page.screenshot(path="verification/schedule_edit.png")

        # Verify values
        assert page.input_value("#schedule_id") == "123"
        assert page.input_value("#schedule_start_time") == "2024-05-30T10:00"

        browser.close()

if __name__ == "__main__":
    run()
