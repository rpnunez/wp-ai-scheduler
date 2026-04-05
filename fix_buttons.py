import re

files = [
    "ai-post-scheduler/templates/admin/sections.php",
    "ai-post-scheduler/templates/admin/structures.php",
    "ai-post-scheduler/templates/admin/voices.php",
    "ai-post-scheduler/templates/admin/templates.php",
    "ai-post-scheduler/templates/admin/taxonomy.php",
    "ai-post-scheduler/templates/admin/author-topics.php",
    "ai-post-scheduler/templates/admin/schedule.php",
    "ai-post-scheduler/templates/admin/history.php",
    "ai-post-scheduler/templates/admin/planner.php",
    "ai-post-scheduler/templates/admin/research.php",
    "ai-post-scheduler/templates/admin/sources.php",
    "ai-post-scheduler/templates/admin/authors.php"
]

for filepath in files:
    try:
        with open(filepath, 'r') as f:
            content = f.read()

        # We find buttons that have an id like *search-clear* and class containing aips-btn-secondary
        new_content = re.sub(
            r'(<button[^>]+id="[^"]*search-clear[^"]*"[^>]*class="[^"]*)aips-btn-secondary([^"]*"[^>]*>)',
            r'\1aips-btn-ghost\2',
            content
        )

        if new_content != content:
            with open(filepath, 'w') as f:
                f.write(new_content)
            print(f"Updated {filepath}")
    except FileNotFoundError:
        pass
