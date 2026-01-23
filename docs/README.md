# Feature Scanner and Agent

## Overview

This directory contains the automated feature documentation system for the AI Post Scheduler WordPress plugin.

## Components

### 1. Feature Scanner (`scripts/feature_scanner.py`)

A Python script that automatically analyzes the plugin's PHP codebase to generate comprehensive feature documentation.

**What it does:**
- Scans all PHP classes in `ai-post-scheduler/includes/`
- Extracts class information, methods, hooks, and dependencies
- Generates detailed feature profiles for each class
- Creates Mermaid flowcharts showing component relationships
- Identifies missing functionality and suggests improvements
- Produces statistics and analysis

**Output:**
- Generates `docs/feature-report.md` with complete documentation

### 2. Feature Agent Workflow (`.github/workflows/agents/feature-agent.yml`)

A GitHub Actions workflow that automatically maintains the feature documentation.

**Schedule:**
- Runs weekly on Mondays at 00:00 UTC
- Can be triggered manually via workflow_dispatch

**Process:**
1. Checks out the repository
2. Sets up Python environment
3. Runs the feature scanner
4. Detects if `docs/feature-report.md` has changes
5. If changes detected:
   - Creates a new branch: `feature-agent/update-feature-report-<timestamp>`
   - Commits the updated report
   - Creates a Pull Request for review

**Note:** The workflow creates PRs instead of committing directly to main, allowing for review before merging.

## Feature Report Contents

The generated `docs/feature-report.md` includes:

### 1. Overview
- Total statistics (classes, lines of code, categories)
- High-level architecture diagram

### 2. Feature Categories
- Organized grouping of related classes
- Category-specific flowcharts showing relationships

### 3. Feature Profiles
For each class, includes:
- **Feature Name**: Human-readable name
- **High-level Summary**: Class description
- **Files Involved**: File path and class name
- **Technical Details**: Methods, dependencies, hooks, database operations
- **Missing Functionality**: Identified gaps or missing features
- **Recommended Improvements**: Actionable suggestions for enhancement

### 4. Summary Statistics
- Classes by category
- Largest classes by lines of code
- Most connected classes by dependencies

## Usage

### Running Manually

```bash
# From repository root
python3 scripts/feature_scanner.py
```

This will generate/update `docs/feature-report.md`.

### Triggering the Workflow

1. Go to the repository on GitHub
2. Click "Actions" tab
3. Select "Feature Agent - Update Feature Report" workflow
4. Click "Run workflow"
5. Select the branch and click "Run workflow"

The workflow will create a PR if changes are detected.

## Development

### Modifying the Scanner

The scanner is modular and can be extended:

- `scan_all_files()`: Main scanning logic
- `analyze_file()`: Per-file analysis
- `extract_*()`: Various extraction methods for different code elements
- `identify_missing_functionality()`: Logic for finding gaps
- `suggest_improvements()`: Logic for recommendations
- `generate_mermaid_flowchart()`: Flowchart generation
- `generate_report()`: Report formatting

### Adding New Analysis

To add new analysis features:

1. Add extraction method in the scanner
2. Update the feature data structure
3. Update the report generation to display the new data

### Customizing Categories

Edit the `categorize_features()` method to adjust how classes are grouped.

## Benefits

- **Automated Documentation**: Keeps feature documentation up to date automatically
- **Comprehensive Analysis**: Provides deep insights into codebase structure
- **Visual Diagrams**: Mermaid flowcharts show component relationships
- **Actionable Insights**: Identifies missing functionality and improvement opportunities
- **Review Process**: PR-based workflow ensures documentation changes are reviewed
- **Historical Tracking**: Git history tracks how features evolve over time

## Requirements

- Python 3.11+
- GitHub Actions (for automated workflow)
- Repository write access (for creating branches and PRs)

## Troubleshooting

### Scanner Fails to Run

- Ensure Python 3.11+ is installed
- Check that `ai-post-scheduler/includes/` directory exists
- Verify file permissions on the scanner script

### Workflow Fails to Create PR

- Check workflow permissions (needs `contents: write` and `pull-requests: write`)
- Verify GitHub token has necessary permissions
- Check workflow logs for specific error messages

### Flowcharts Don't Render

- Verify Mermaid syntax is valid
- Check that GitHub supports Mermaid rendering (enabled by default in markdown files)
- Test locally with a Mermaid preview tool
