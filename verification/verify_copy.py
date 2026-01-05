
import os
from playwright.sync_api import sync_playwright

def verify_copy_button():
    # Create a mock HTML file to test the research table logic
    html_content = """
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Research Page Verification</title>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <style>
            .button { padding: 5px 10px; border: 1px solid #ccc; background: #f0f0f1; cursor: pointer; }
            .button:disabled { opacity: 0.5; cursor: default; }
            .dashicons { font-family: monospace; }
        </style>
    </head>
    <body>
        <div id="topics-container"></div>
        <div id="bulk-schedule-section" style="display: none;">Bulk Schedule</div>
        <div id="aips-research-bulk-actions" style="display: none;">
            <button type="button" class="button" id="copy-selected-topics" disabled>
                <span class="dashicons dashicons-admin-page"></span>
                Copy Selected
            </button>
        </div>

        <script>
            // Mock AIPS object and other globals
            window.aipsResearchL10n = {
                deleteTopicConfirm: "Are you sure?",
                selectTopicSchedule: "Please select topics",
                schedulingError: "Error",
                delete: "Delete"
            };
            window.ajaxurl = "/wp-admin/admin-ajax.php";

            // Injected JS content from admin-research.js (simulated)
            // We will load the actual JS file in the test
        </script>
    </body>
    </html>
    """

    os.makedirs("verification", exist_ok=True)
    with open("verification/mock_research.html", "w") as f:
        f.write(html_content)

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        # Enable clipboard permissions
        context = browser.new_context(permissions=['clipboard-read', 'clipboard-write'])
        page = context.new_page()

        # Load the mock page
        page.goto(f"file://{os.getcwd()}/verification/mock_research.html")

        # Inject the modified JS file
        with open("ai-post-scheduler/assets/js/admin-research.js", "r") as f:
            js_content = f.read()
            page.add_script_tag(content=js_content)

        # Simulate loading topics (mock the AJAX response handling)
        page.evaluate("""
            const topics = [
                { id: 1, topic: 'AI Trends 2024', score: 95, niche: 'Tech', researched_at: '2024-05-27' },
                { id: 2, topic: 'Machine Learning Basics', score: 85, niche: 'Tech', researched_at: '2024-05-27' }
            ];
            // Access the displayTopicsTable function directly if exposed, or trigger the AJAX success handler logic
            // Since functions are local to the IIFE, we need to replicate the DOM update or expose the function.
            // For verification, we can just manually trigger the DOM structure that the JS expects.

            // Re-implement displayTopicsTable logic for the test since it is inside a closure
            // OR simpler: we can just manually build the table to match what we expect, then test the listeners.

            let html = '<table class="aips-topics-table">';
            html += '<thead><tr><th><input type="checkbox" id="select-all-topics"></th><th>Topic</th><th>Score</th></tr></thead><tbody>';

            topics.forEach(function(topic) {
                html += '<tr>';
                html += '<td><input type="checkbox" class="topic-checkbox" value="' + topic.id + '"></td>';
                html += '<td><strong>' + topic.topic + '</strong></td>';
                html += '<td>' + topic.score + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table>';

            $('#topics-container').html(html);
            $('#bulk-schedule-section').show();
            $('#aips-research-bulk-actions').show();
        """)

        # 1. Verify Bulk Actions visible
        print("Verifying Bulk Actions visibility...")
        assert page.is_visible("#aips-research-bulk-actions")
        assert page.is_disabled("#copy-selected-topics")

        # 2. Select a topic
        print("Selecting a topic...")
        page.check("input[value='1']")

        # 3. Verify button enabled
        assert page.is_enabled("#copy-selected-topics")

        # 4. Click Copy
        print("Clicking Copy...")
        page.click("#copy-selected-topics")

        # 5. Verify Clipboard Content
        # Note: Playwright clipboard read might be tricky in headless without permissions,
        # but we enabled them.
        clipboard_text = page.evaluate("navigator.clipboard.readText()")
        print(f"Clipboard text: {clipboard_text}")
        assert "AI Trends 2024" in clipboard_text

        # 6. Verify Button Feedback
        print("Verifying feedback...")
        assert "Copied!" in page.inner_text("#copy-selected-topics")

        # Take screenshot
        page.screenshot(path="verification/research_copy_verified.png")
        print("Verification successful. Screenshot saved.")

        browser.close()

if __name__ == "__main__":
    verify_copy_button()
