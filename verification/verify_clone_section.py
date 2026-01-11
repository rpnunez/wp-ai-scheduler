from playwright.sync_api import sync_playwright

def verify_clone_button(page):
    # Load the mock HTML file
    # Note: In a real environment, we'd load the PHP template rendered by WP.
    # Here we mock the HTML structure found in `templates/admin/sections.php`
    # and inject the JS from `assets/js/admin.js` (mocked behavior).

    html_content = """
    <!DOCTYPE html>
    <html>
    <head>
        <title>Mock Prompt Sections</title>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <style>
            .aips-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
            .aips-modal-content { background: white; margin: 10% auto; padding: 20px; width: 50%; }
        </style>
    </head>
    <body>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr data-section-id="123">
                    <td>Test Section</td>
                    <td>
                        <button class="button aips-edit-section" data-id="123">Edit</button>
                        <button class="button aips-clone-section" data-id="123" aria-label="Clone Section">Clone</button>
                        <button class="button button-link-delete aips-delete-section" data-id="123">Delete</button>
                    </td>
                </tr>
            </tbody>
        </table>

        <div id="aips-section-modal" class="aips-modal">
            <div class="aips-modal-content">
                <h2 id="aips-section-modal-title">Title</h2>
                <form id="aips-section-form">
                    <input type="hidden" id="section_id" value="">
                    <input type="text" id="section_name" value="">
                    <input type="text" id="section_key" value="">
                    <textarea id="section_description"></textarea>
                    <textarea id="section_content"></textarea>
                    <input type="checkbox" id="section_is_active">
                </form>
            </div>
        </div>

        <script>
            var aipsAjax = { ajaxUrl: '/wp-admin/admin-ajax.php', nonce: '123' };
            var aipsAdminL10n = {};

            // Mock jQuery ajax
            $.post = function(url, data, callback) {
                console.log('Mock AJAX call:', data.action);
                if (data.action === 'aips_get_prompt_section') {
                    callback({
                        success: true,
                        data: {
                            section: {
                                id: 123,
                                name: 'Test Section',
                                section_key: 'test_key',
                                description: 'Desc',
                                content: 'Content',
                                is_active: 1
                            }
                        }
                    });
                }
                return { fail: function(){} };
            };

            $(document).on('click', '.aips-clone-section', function(){
                var id = $(this).data('id');
                $.post(aipsAjax.ajaxUrl, {action: 'aips_get_prompt_section', nonce: aipsAjax.nonce, section_id: id}, function(response){
                    if (response.success) {
                        var s = response.data.section;

                        $('#section_id').val('');
                        $('#section_name').val(s.name + ' (Copy)');
                        $('#section_key').val(s.section_key + '_copy');
                        $('#section_description').val(s.description);
                        $('#section_content').val(s.content);
                        $('#section_is_active').prop('checked', s.is_active == 1);

                        $('#aips-section-modal-title').text('Clone Prompt Section');
                        $('#aips-section-modal').show();
                    }
                });
            });
        </script>
    </body>
    </html>
    """

    page.set_content(html_content)

    # Click clone
    page.click('.aips-clone-section')

    # Wait for modal
    page.wait_for_selector('#aips-section-modal', state='visible')

    # Take screenshot
    page.screenshot(path='verification/verify_clone_section.png')

with sync_playwright() as p:
    browser = p.chromium.launch()
    page = browser.new_page()
    verify_clone_button(page)
    browser.close()
