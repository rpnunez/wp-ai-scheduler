from playwright.sync_api import sync_playwright
import os

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    page = browser.new_page()

    with open('ai-post-scheduler/templates/admin/research.php', 'r') as f:
        php_content = f.read()

    start = php_content.find('<script>') + 8
    end = php_content.find('</script>')
    js_content = php_content[start:end]

    html = f"""
    <!DOCTYPE html>
    <html>
    <head>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <style>
            .aips-score-high {{ background: green; color: white; }}
            .aips-score-medium {{ background: orange; }}
            .aips-score-low {{ background: gray; color: white; }}
        </style>
    </head>
    <body>
        <div id="topics-container"></div>
        <div id="research-results" style="display:none;">
            <div id="research-results-content"></div>
        </div>
        <div id="bulk-schedule-section" style="display:none;"></div>

        <!-- Elements needed for the script to attach events -->
        <button id="load-topics">Load Topics</button>
        <input id="filter-niche" value="">
        <input id="filter-score" value="">
        <input type="checkbox" id="filter-fresh">
        <input type="hidden" id="aips_nonce" value="test_nonce">

        <script>
            var ajaxurl = '/wp-admin/admin-ajax.php';
            {js_content}
        </script>
    </body>
    </html>
    """

    import re
    html = re.sub(r'<\?php.*?\?>', '""', html)

    with open('verification/test_xss.html', 'w') as f:
        f.write(html)

    page.goto('file://' + os.path.abspath('verification/test_xss.html'))

    # Mock AJAX to return XSS payloads
    # Added check for options and options.data to avoid "Cannot read properties of null"
    page.evaluate("""
        jQuery.ajax = function(options) {
            if (options && options.data && options.data.action === 'aips_get_trending_topics') {
                options.success({
                    success: true,
                    data: {
                        topics: [
                            {
                                id: 1,
                                topic: '<img src=x onerror=document.body.setAttribute("data-xss", "1")>',
                                score: 95,
                                niche: '<b>Niche</b>',
                                reason: 'Reason <script>document.body.setAttribute("data-xss-reason", "1")</script>',
                                keywords: ['<svg/onload=document.body.setAttribute("data-xss-kw", "1")>'],
                                researched_at: new Date().toISOString()
                            }
                        ]
                    }
                });
            }
        };
    """)

    # Trigger the load
    page.click('#load-topics')

    # Wait for the table to populate
    page.wait_for_selector('.aips-topics-table')

    # Take screenshot
    page.screenshot(path='verification/xss_check.png')

    # Verify the HTML content is escaped
    content = page.content()

    img_count = page.locator('img[src="x"]').count()
    print(f"Malicious images found: {img_count}")

    if img_count == 0:
        print("SUCCESS: XSS payload was not executed.")
    else:
        print("FAILURE: XSS payload WAS executed.")

    # Check text content to verify it displays the string safely
    topic_text = page.locator('td').nth(1).text_content()
    print(f"Rendered topic text: {topic_text}")

if __name__ == "__main__":
    with sync_playwright() as p:
        run(p)
