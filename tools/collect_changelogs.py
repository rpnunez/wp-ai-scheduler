import subprocess
import re
import datetime

def run_command(command):
    try:
        result = subprocess.run(command, shell=True, check=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
        return result.stdout.strip()
    except subprocess.CalledProcessError as e:
        return None

def get_remote_branches():
    # Get all remote branches
    output = run_command("git branch -r")
    if not output:
        return []
    branches = [b.strip() for b in output.split('\n')]
    # Filter out HEAD and main
    branches = [b for b in branches if "HEAD" not in b and "origin/main" not in b]
    return branches

def get_last_commit_date(branch):
    # Returns date in ISO 8601-like format: 2024-05-22 14:30:00 -0400
    return run_command(f"git show -s --format=%ci {branch}")

def get_changelog_content(branch):
    # Try root CHANGELOG.md
    content = run_command(f"git show {branch}:CHANGELOG.md")
    if content is None:
        # Try ai-post-scheduler/CHANGELOG.md
        content = run_command(f"git show {branch}:ai-post-scheduler/CHANGELOG.md")
    return content

def parse_changelog(content):
    if not content:
        return None

    lines = content.split('\n')
    extracted_lines = []
    capture = False
    found_start = False

    for line in lines:
        stripped = line.strip()

        # Detect start of relevant section
        if stripped.lower().startswith("## [unreleased]") or (stripped.startswith("## ") and not found_start):
            capture = True
            found_start = True
            continue # Skip the header line itself

        # Stop if we hit another version header
        if capture and stripped.startswith("## ") and not stripped.lower().startswith("###"):
            break

        if capture:
            extracted_lines.append(line)

    # Fallback for list-only files
    if not extracted_lines and not found_start:
        for line in lines:
            if line.strip().startswith("-") or line.strip().startswith("*"):
                extracted_lines.append(line)

    if not extracted_lines:
        return None

    result = "\n".join(extracted_lines).strip()

    # Remove standard boilerplate if caught
    if "All notable changes" in result or "The format is based on" in result:
         return None

    return result

def main():
    branches = get_remote_branches()
    entries = []

    # Get main content for comparison (baseline)
    main_raw = get_changelog_content("origin/main")
    main_parsed = parse_changelog(main_raw) if main_raw else ""
    # Create a set of lines from main to filter out
    main_lines = set(line.strip() for line in main_parsed.split('\n') if line.strip()) if main_parsed else set()

    print(f"Found {len(branches)} branches to process.")

    for branch in branches:
        branch_name = branch.replace('origin/', '')

        date_str = get_last_commit_date(branch)
        content = get_changelog_content(branch)

        if content:
            parsed = parse_changelog(content)
            if parsed:
                # Deduplication logic: Remove lines that exist in main
                branch_lines = parsed.split('\n')
                unique_lines = []
                for line in branch_lines:
                    # Keep headers (starting with ###) even if they duplicate, as long as they have unique children
                    # But simplifying: if the line content (stripped) is in main_lines, skip it.
                    # EXCEPT if it's a header like "### Fixed", we might want to keep it if there are items below it.
                    # Simple heuristic: Only filter bullet points.
                    if (line.strip().startswith('-') or line.strip().startswith('*')) and line.strip() in main_lines:
                        continue
                    unique_lines.append(line)

                # Clean up empty headers (e.g. "### Fixed" with no items)
                # This requires a second pass or smarter loop.
                # Let's do a simple pass: remove headers if they are the last thing or followed by another header
                cleaned_lines = []
                for i, line in enumerate(unique_lines):
                    if line.strip().startswith('###'):
                        # Check if next line is a bullet
                        has_content = False
                        for next_line in unique_lines[i+1:]:
                            if next_line.strip().startswith('-') or next_line.strip().startswith('*'):
                                has_content = True
                                break
                            if next_line.strip().startswith('###'):
                                break
                        if not has_content:
                            continue
                    cleaned_lines.append(line)

                # Remove empty lines
                final_branch_content = "\n".join([l for l in cleaned_lines if l.strip()]).strip()

                if not final_branch_content:
                    # Branch has no unique content
                    continue

                # Parse date object for sorting
                try:
                    clean_date_str = (date_str or "")[:19]
                    date_obj = datetime.datetime.strptime(clean_date_str, "%Y-%m-%d %H:%M:%S")
                except Exception as e:
                    print(f"Error parsing date '{date_str}' for {branch}: {e}")
                    date_obj = datetime.datetime.min

                # Safely derive a display date string (may be empty if date_str is None/invalid)
                date_str_value = date_str.split(' ')[0] if date_str else ""

                entries.append({
                    'branch': branch_name,
                    'date': date_obj,
                    'date_str': date_str_value,
                    'content': final_branch_content
                })

    # Sort by date descending
    entries.sort(key=lambda x: x['date'], reverse=True)

    # Build final content
    final_content = "# Change Log\n\nAll notable changes to this project will be documented in this file.\n\n"

    for entry in entries:
        final_content += f"## [{entry['branch']}] - {entry['date_str']}\n"
        final_content += f"{entry['content']}\n\n"

    with open("ai-post-scheduler/CHANGELOG.md", "w") as f:
        f.write(final_content)

    print("Successfully created ai-post-scheduler/CHANGELOG.md")

if __name__ == "__main__":
    main()
