
import pytest
from playwright.sync_api import sync_playwright
import os

MOCK_HTML_PATH = os.path.join(os.getcwd(), 'verification/mock_structures.html')

def create_mock_html():
    html_content = """
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Mock Structures Search</title>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <style>
            .screen-reader-text { display: none; }
            .aips-empty-state { display: none; text-align: center; padding: 20px; }
            .button { display: inline-block; padding: 5px 10px; cursor: pointer; }
        </style>
    </head>
    <body>
        <div class="wrap aips-wrap">
            <div class="aips-structures-container">
                <div class="aips-search-box" style="margin-bottom: 10px; text-align: right;">
                    <label class="screen-reader-text" for="aips-structure-search">Search Structures:</label>
                    <input type="search" id="aips-structure-search" class="regular-text" placeholder="Search structures...">
                    <button type="button" id="aips-structure-search-clear" class="button" style="display: none;">Clear</button>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="column-name">Name</th>
                            <th class="column-description">Description</th>
                            <th>Active</th>
                            <th>Default</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr data-structure-id="1">
                            <td class="column-name">Standard Blog Post</td>
                            <td class="column-description">A standard blog post structure with intro, body, and conclusion.</td>
                            <td>Yes</td>
                            <td>Yes</td>
                            <td><button>Edit</button></td>
                        </tr>
                        <tr data-structure-id="2">
                            <td class="column-name">Listicle</td>
                            <td class="column-description">Top 10 list format.</td>
                            <td>Yes</td>
                            <td>No</td>
                            <td><button>Edit</button></td>
                        </tr>
                        <tr data-structure-id="3">
                            <td class="column-name">How-To Guide</td>
                            <td class="column-description">Step-by-step instructions.</td>
                            <td>No</td>
                            <td>No</td>
                            <td><button>Edit</button></td>
                        </tr>
                    </tbody>
                </table>

                <div id="aips-structure-search-no-results" class="aips-empty-state" style="display: none;">
                    <h3>No Structures Found</h3>
                    <p>No structures match your search criteria.</p>
                    <button type="button" class="button button-primary aips-clear-structure-search-btn">
                        Clear Search
                    </button>
                </div>
            </div>
        </div>
    </body>
    </html>
    """
    with open(MOCK_HTML_PATH, 'w') as f:
        f.write(html_content)

def test_structure_search():
    create_mock_html()

    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()
        page.goto(f'file://{MOCK_HTML_PATH}')

        # Inject the JS logic
        # We need to read admin.js but it has many dependencies and is wrapped in an IIFE.
        # It's better to just inject the relevant logic for this test to verify the algorithm
        # OR better yet, inject the actual JS file content if we can mocking window.AIPS

        # Let's try to load the actual JS file content
        with open('ai-post-scheduler/assets/js/admin.js', 'r') as f:
            js_content = f.read()

        # We need to mock aipsAjax and aipsAdminL10n
        page.add_script_tag(content="""
            window.aipsAjax = { ajaxUrl: '/wp-admin/admin-ajax.php', nonce: '123' };
            window.aipsAdminL10n = {};
            window.wp = { media: function() {} };
        """)

        page.add_script_tag(content=js_content)

        # 1. Test Search for "Standard"
        page.fill('#aips-structure-search', 'Standard')
        page.keyboard.up('d') # Trigger keyup

        # Check visibility
        assert page.is_visible('tr[data-structure-id="1"]')
        assert not page.is_visible('tr[data-structure-id="2"]')
        assert not page.is_visible('tr[data-structure-id="3"]')
        assert page.is_visible('#aips-structure-search-clear')

        # 2. Test Search for "Listicle"
        page.fill('#aips-structure-search', 'Listicle')
        page.keyboard.up('e')

        assert not page.is_visible('tr[data-structure-id="1"]')
        assert page.is_visible('tr[data-structure-id="2"]')
        assert not page.is_visible('tr[data-structure-id="3"]')

        # 3. Test Search for Description "Step-by-step"
        page.fill('#aips-structure-search', 'Step-by-step')
        page.keyboard.up('p')

        assert not page.is_visible('tr[data-structure-id="1"]')
        assert not page.is_visible('tr[data-structure-id="2"]')
        assert page.is_visible('tr[data-structure-id="3"]')

        # 4. Test No Results
        page.fill('#aips-structure-search', 'XYZ123')
        page.keyboard.up('3')

        assert not page.is_visible('tr[data-structure-id="1"]')
        assert not page.is_visible('tr[data-structure-id="2"]')
        assert not page.is_visible('tr[data-structure-id="3"]')
        assert not page.is_visible('.aips-structures-container table')
        assert page.is_visible('#aips-structure-search-no-results')

        # 5. Test Clear Button in Search Box
        page.click('#aips-structure-search-clear')

        assert page.input_value('#aips-structure-search') == ''
        assert page.is_visible('tr[data-structure-id="1"]')
        assert page.is_visible('tr[data-structure-id="2"]')
        assert page.is_visible('tr[data-structure-id="3"]')
        assert not page.is_visible('#aips-structure-search-clear')

        # 6. Test Clear Button in Empty State
        page.fill('#aips-structure-search', 'XYZ123')
        page.keyboard.up('3')
        page.click('.aips-clear-structure-search-btn')

        assert page.input_value('#aips-structure-search') == ''
        assert page.is_visible('tr[data-structure-id="1"]')
        assert page.is_visible('.aips-structures-container table')
        assert not page.is_visible('#aips-structure-search-no-results')

        browser.close()
        print("All tests passed!")

if __name__ == "__main__":
    test_structure_search()
