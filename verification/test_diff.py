from playwright.sync_api import sync_playwright
# ... skipping playwright for now, just checking file content
with open("ai-post-scheduler/assets/js/admin.js", "r") as f:
    content = f.read()
    if "copyToClipboard" in content:
        print("Found copyToClipboard")
    else:
        print("NOT FOUND copyToClipboard")
