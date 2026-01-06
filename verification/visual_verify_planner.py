
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
    <title>Planner Visual Verification</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { font-family: sans-serif; padding: 20px; }
        .screen-reader-text {
            clip: rect(1px, 1px, 1px, 1px);
            height: 1px;
            overflow: hidden;
            position: absolute;
            width: 1px;
            word-wrap: normal !important;
        }
        /* VISUAL FEEDBACK FOR VERIFICATION */
        :focus {
            outline: 5px solid red !important;
            background-color: yellow !important;
        }
        .aips-card {
            border: 1px solid #ccc;
            padding: 20px;
            margin-bottom: 20px;
            background: #fff;
        }
        /* Debug visualization of aria-live region */
        #aips-planner-a11y-status {
            clip: auto !important;
            height: auto !important;
            width: auto !important;
            position: static !important;
            background: #eee;
            border: 1px dashed blue;
            padding: 10px;
            margin-bottom: 10px;
            color: blue;
        }
        #aips-planner-a11y-status::before {
            content: "ARIA-LIVE REGION (Visualized for Debug): ";
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>Planner Accessibility Verification</h1>
    <p>The blue box represents the screen-reader-only live region. The red outline shows focus.</p>

    <div class="aips-planner-container">
        <!-- This is the element we added -->
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
            <!-- This is the element we modified -->
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

def visual_verify_planner():
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

        # Mock $.ajax
        page.evaluate("""
            $.ajax = function(options) {
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
                    }, 500);
                }
            };
        """)

        # 1. Trigger generation
        page.click("#btn-generate-topics")

        # Wait for "Generating..." text
        page.wait_for_function("document.getElementById('aips-planner-a11y-status').innerText.includes('Generating')")
        page.screenshot(path="verification/step1_generating.png")

        # Wait for success and animation
        page.wait_for_selector("#planner-results", state="visible")
        page.wait_for_timeout(600) # Wait for animation callback

        # Take screenshot showing focus on H3 and updated text
        page.screenshot(path="verification/step2_success_focus.png")

        print("Screenshots taken: verification/step1_generating.png, verification/step2_success_focus.png")

        browser.close()

if __name__ == "__main__":
    try:
        visual_verify_planner()
    except Exception as e:
        print(f"Verification failed: {e}")
