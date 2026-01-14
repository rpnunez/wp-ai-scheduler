import re

def verify_sections_search():
    try:
        with open('ai-post-scheduler/templates/admin/sections.php', 'r') as f:
            content = f.read()

        checks = {
            'Search Input': 'id="aips-section-search"',
            'Clear Button': 'id="aips-section-search-clear"',
            'Table Class': 'class="wp-list-table widefat fixed striped aips-sections-list"',
            'Empty State': 'id="aips-section-search-no-results"',
            'Copy Button': 'class="button button-small aips-copy-btn"'
        }

        all_passed = True
        for name, pattern in checks.items():
            if pattern in content:
                print(f"✅ {name} found")
            else:
                print(f"❌ {name} NOT found")
                all_passed = False

        if all_passed:
            print("\nSUCCESS: All required elements are present in sections.php")
        else:
            print("\nFAILURE: Some elements are missing in sections.php")
            exit(1)

        # Check JS
        with open('ai-post-scheduler/assets/js/admin.js', 'r') as f:
            js_content = f.read()

        js_checks = {
            'Event Binding': "#aips-section-search",
            'Filter Function': "filterSections: function()",
            'Clear Function': "clearSectionSearch: function(e)"
        }

        for name, pattern in js_checks.items():
            if pattern in js_content or pattern.replace("'", '"') in js_content:
                print(f"✅ JS {name} found")
            else:
                print(f"❌ JS {name} NOT found")
                all_passed = False

        if all_passed:
             print("\nSUCCESS: JS modifications verified")
        else:
             print("\nFAILURE: Some JS elements are missing")
             exit(1)

    except Exception as e:
        print(f"Error during verification: {e}")
        exit(1)

if __name__ == "__main__":
    verify_sections_search()
