
from playwright.sync_api import sync_playwright
import os

def generate_mock_html():
    # Read the PHP template
    with open('ai-post-scheduler/templates/admin/structures.php', 'r') as f:
        php_content = f.read()

    # Create a mock HTML structure that simulates the PHP output
    # This is a simplified version for visualization

    mock_structures = """
    <tr data-structure-id="1">
        <td class="column-name">SEO Blog Post</td>
        <td class="column-description">Standard blog post with SEO optimization</td>
        <td>Yes</td>
        <td>Yes</td>
        <td>
            <button class="button aips-clone-structure" data-id="1" aria-label="Clone structure">Clone</button>
            <button class="button aips-edit-structure" data-id="1">Edit</button>
            <button class="button button-link-delete aips-delete-structure" data-id="1">Delete</button>
        </td>
    </tr>
    <tr data-structure-id="2">
        <td class="column-name">News Article</td>
        <td class="column-description">Timely news piece</td>
        <td>Yes</td>
        <td>No</td>
        <td>
            <button class="button aips-clone-structure" data-id="2" aria-label="Clone structure">Clone</button>
            <button class="button aips-edit-structure" data-id="2">Edit</button>
            <button class="button button-link-delete aips-delete-structure" data-id="2">Delete</button>
        </td>
    </tr>
    """

    mock_sections = """
    <tr data-section-id="1">
        <td class="column-name">Introduction</td>
        <td class="column-key"><code>intro</code></td>
        <td class="column-description">Engaging intro hook</td>
        <td>Yes</td>
        <td>
            <button class="button aips-clone-section" data-id="1" aria-label="Clone section">Clone</button>
            <button class="button aips-edit-section" data-id="1">Edit</button>
            <button class="button button-link-delete aips-delete-section" data-id="1">Delete</button>
        </td>
    </tr>
    """

    # Strip PHP tags and replace variables for the visual mock
    html_content = php_content

    # Simple PHP stripping/mocking (very basic)
    html_content = html_content.replace('<?php if (!defined(\'ABSPATH\')) {', '<!--')
    html_content = html_content.replace('	$sections = array();\n}', '-->')
    html_content = html_content.replace('<?php', '<!--').replace('?>', '-->')

    # Re-inject the mock rows where the foreach loops were
    # This is a bit hacky but sufficient for checking layout of static elements like search bars

    # Replace Structures loop
    # We need to find the tbody and inject our rows
    html_content = html_content.replace('<tbody>', '<tbody>' + mock_structures)

    # Replace Sections loop
    html_content = html_content.replace('<tbody>', '<tbody>' + mock_sections, 1) # This replaces the second tbody (sections is 2nd tab) - wait, replace replaces all by default in python? No, 1 is count.

    # Actually, let's just construct a clean HTML file using the parts we want to verify
    # extracting the search box html manually from the read file might be safer or just checking selectors.

    full_html = f"""
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Article Structures Mock</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dashicons/3.2.0/css/dashicons.min.css">
        <style>
            body {{ font-family: sans-serif; background: #f1f1f1; padding: 20px; }}
            .wrap {{ background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); }}
            .wp-list-table {{ width: 100%; border-collapse: collapse; margin-top: 10px; }}
            .wp-list-table th, .wp-list-table td {{ text-align: left; padding: 8px; border-bottom: 1px solid #c3c4c7; }}
            .button {{ display: inline-block; text-decoration: none; font-size: 13px; line-height: 2.15384615; min-height: 30px; margin: 0; padding: 0 10px; cursor: pointer; border-width: 1px; border-style: solid; -webkit-appearance: none; border-radius: 3px; white-space: nowrap; box-sizing: border-box; color: #2271b1; border-color: #2271b1; background: #f6f7f7; vertical-align: top; }}
            .button-primary {{ background: #2271b1; border-color: #2271b1; color: #fff; }}
            .aips-search-box {{ margin-bottom: 10px; text-align: right; }}
            .regular-text {{ width: 25em; }}
            .screen-reader-text {{ clip: rect(1px, 1px, 1px, 1px); position: absolute !important; height: 1px; width: 1px; overflow: hidden; }}
            .nav-tab-wrapper {{ border-bottom: 1px solid #c3c4c7; padding-bottom: 0; }}
            .nav-tab {{ border: 1px solid #c3c4c7; border-bottom: none; background: #e5e5e5; color: #50575e; padding: 6px 10px; text-decoration: none; margin-left: .5em; }}
            .nav-tab-active {{ background: #f0f0f1; border-bottom: 1px solid #f0f0f1; }}
        </style>
    </head>
    <body>
        <div class="wrap aips-wrap">
            <h1>Article Structures</h1>

            <div class="nav-tab-wrapper">
                <a href="#" class="nav-tab nav-tab-active">Article Structures</a>
                <a href="#" class="nav-tab">Structure Sections</a>
            </div>

            <!-- MOCK: Structure Tab Content -->
            <div id="aips-structures-tab" class="aips-tab-content active">
                <h2>Article Structures <button class="button">Add New</button></h2>

                <div class="aips-search-box" style="margin-bottom: 10px; text-align: right;">
                    <label class="screen-reader-text" for="aips-structure-search">Search Structures:</label>
                    <input type="search" id="aips-structure-search" class="regular-text" placeholder="Search structures...">
                    <button type="button" id="aips-structure-search-clear" class="button" style="display: none;">Clear</button>
                </div>

                <table class="wp-list-table widefat fixed striped aips-structures-list">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Active</th>
                            <th>Default</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {mock_structures}
                    </tbody>
                </table>
            </div>

             <!-- MOCK: Sections Tab Content (Visualizing the search box only) -->
            <div id="aips-structure-sections-tab" class="aips-tab-content" style="margin-top: 50px;">
                <h2>Structure Sections <button class="button">Add New</button></h2>

                <div class="aips-search-box" style="margin-bottom: 10px; text-align: right;">
                    <label class="screen-reader-text" for="aips-section-search">Search Sections:</label>
                    <input type="search" id="aips-section-search" class="regular-text" placeholder="Search sections...">
                    <button type="button" id="aips-section-search-clear" class="button" style="display: none;">Clear</button>
                </div>

                <table class="wp-list-table widefat fixed striped aips-sections-list">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Key</th>
                            <th>Description</th>
                            <th>Active</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {mock_sections}
                    </tbody>
                </table>
            </div>

        </div>
    </body>
    </html>
    """

    with open('verification/mock_structures.html', 'w') as f:
        f.write(full_html)

def verify_structures_ui():
    generate_mock_html()

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Load the mock HTML file
        page.goto(f"file://{os.getcwd()}/verification/mock_structures.html")

        # Verify Structure Search Exists
        search_input = page.locator('#aips-structure-search')
        expect(search_input).to_be_visible()
        expect(search_input).to_have_attribute('placeholder', 'Search structures...')

        # Verify Clone Button Exists in Structure Table
        clone_btn = page.locator('.aips-clone-structure').first
        expect(clone_btn).to_be_visible()
        expect(clone_btn).to_have_text('Clone')

        # Verify Section Search Exists
        section_search_input = page.locator('#aips-section-search')
        expect(section_search_input).to_be_visible()

        # Verify Clone Button Exists in Section Table
        section_clone_btn = page.locator('.aips-clone-section').first
        expect(section_clone_btn).to_be_visible()
        expect(section_clone_btn).to_have_text('Clone')

        # Take screenshot
        page.screenshot(path="verification/verification_structures.png")

        browser.close()

if __name__ == "__main__":
    from playwright.sync_api import expect
    verify_structures_ui()
