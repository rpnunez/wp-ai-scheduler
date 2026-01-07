import re
import os

def check_aria_label(filepath, element_id):
    if not os.path.exists(filepath):
        print(f"File not found: {filepath}")
        return False

    with open(filepath, 'r') as f:
        content = f.read()

    # Regex to find the element by ID and check for aria-label
    # Use dotall to match across newlines
    pattern = re.compile(f'<select[^>]*id=["\']{element_id}["\'][^>]*>', re.DOTALL)
    match = pattern.search(content)

    if match:
        tag = match.group(0)
        if 'aria-label=' in tag:
            print(f"[PASS] {element_id} in {os.path.basename(filepath)} has aria-label.")
            return True
        else:
            print(f"[FAIL] {element_id} in {os.path.basename(filepath)} MISSING aria-label.")
            return False
    else:
        print(f"[ERROR] Element {element_id} not found in {os.path.basename(filepath)}.")
        return False

def main():
    print("Verifying ARIA labels on filter dropdowns...")

    research_php = 'ai-post-scheduler/templates/admin/research.php'
    history_php = 'ai-post-scheduler/templates/admin/history.php'

    checks = [
        (research_php, 'filter-niche'),
        (research_php, 'filter-score'),
        (history_php, 'aips-filter-status')
    ]

    all_passed = True
    for filepath, element_id in checks:
        if not check_aria_label(filepath, element_id):
            all_passed = False

    if all_passed:
        print("\nAll checks passed!")
    else:
        print("\nSome checks failed.")

if __name__ == "__main__":
    main()
