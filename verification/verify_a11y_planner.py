
import pytest
from playwright.sync_api import sync_playwright
import os
import time

# Mock HTML content simulating the Planner page
MOCK_HTML = """
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Planner Test</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .screen-reader-text {
            clip: rect(1px, 1px, 1px, 1px);
            height: 1px;
            overflow: hidden;
            position: absolute;
            width: 1px;
            word-wrap: normal !important;
        }
    </style>
</head>
<body>
    <div class="aips-planner-container">
        <div id="aips-planner-a11y-status" class="screen-reader-text" aria-live="polite"></div>
        <div class="aips-card">
            <h3>Topic Brainstorming</h3>
            <div class="aips-form-row">
                <label for="planner-niche">Niche / Topic</label>
                <input type="text" id="planner-niche" class="regular-text" value="Test Niche">
            </div>
            <div class="aips-form-row">
                <label for="planner-count">Number of Topics</label>
                <input type="number" id="planner-count" class="small-text" value="2">
            </div>
            <div class="aips-form-actions">
                <button type="button" id="btn-generate-topics" class="button button-primary">Generate Topics</button>
                <span class="spinner"></span>
            </div>
        </div>

        <div id="planner-results" class="aips-card" style="display:none; margin-top: 20px;">
            <h3 tabindex="-1">Review & Schedule</h3>
            <div id="topics-list"></div>
        </div>
    </div>

    <script>
        // Mock WordPress AJAX object
        window.aipsAjax = {
            ajaxUrl: '/wp-admin/admin-ajax.php',
            nonce: 'mock_nonce'
        };
    </script>
</body>
</html>
"""

def test_planner_a11y_improvements():
    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()

        # Load mock HTML
        page.set_content(MOCK_HTML)

        # Inject the actual JS logic (mocking AJAX)
        with open('ai-post-scheduler/assets/js/admin-planner.js', 'r') as f:
            js_content = f.read()

        # Mock window.AIPS if not exists
        page.evaluate("window.AIPS = window.AIPS || {}")

        # Inject the script
        page.add_script_tag(content=js_content)

        # Mock $.ajax with debugging
        page.evaluate("""
            $.ajax = function(options) {
                console.log('AJAX called', options);
                if (options && options.data && options.data.action === 'aips_generate_topics') {
                    // Simulate async delay
                    setTimeout(function() {
                        options.success({
                            success: true,
                            data: {
                                topics: ['Topic 1', 'Topic 2']
                            }
                        });
                        if (options.complete) options.complete();
                    }, 100);
                }
            };
        """)

        # 1. Trigger generation
        page.click("#btn-generate-topics")

        # Wait for success
        page.wait_for_selector("#planner-results", state="visible")

        # Wait for animation callback (400ms + buffer)
        page.wait_for_timeout(600)

        # 3. Check for Success Status update
        status_text = page.inner_text("#aips-planner-a11y-status")
        print(f"Final status text: {status_text}")

        # 4. Check focus management
        focused_tag = page.evaluate("document.activeElement.tagName")
        focused_text = page.evaluate("document.activeElement.textContent")
        print(f"Focused element: {focused_tag} ('{focused_text}')")

        # Assertions
        if "topics generated" not in status_text:
            print("FAILURE: Status text incorrect")

        if focused_tag != "H3":
             print("FAILURE: Focus not on H3")
        else:
             print("SUCCESS: Focus is on H3")

        browser.close()

if __name__ == "__main__":
    try:
        test_planner_a11y_improvements()
    except Exception as e:
        print(f"Test failed: {e}")
