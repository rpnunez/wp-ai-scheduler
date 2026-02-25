# Authors Feature Feedback Loop

## Overview

The feedback loop is a core component of the Authors feature. It ensures that the AI learns from user preferences and avoids generating duplicate or unwanted content.

## How It Works

When generating new topics, the system:

1. **Retrieves Approved Topics**: Fetches a summary of the last 20 approved topics for the author.
2. **Retrieves Rejected Topics**: Fetches a summary of the last 20 rejected topics for the author.
3. **Builds Prompt**: Constructs a prompt for the AI that includes these summaries.
4. **Generates Topics**: Calls the AI API to generate new topic ideas based on the prompt.

## Prompt Structure

The prompt sent to the AI looks something like this:

```
Generate 5 unique blog post topics about: PHP Programming

Previously approved topics (for diversity - avoid duplicating):
- Best PHP Frameworks in 2024
- PHP 8.3 New Features Explained
- Building RESTful APIs with PHP

Previously rejected topics (avoid similar ideas):
- Yet Another PHP Framework Comparison
- PHP vs Python (too broad)

Requirements:
- Each topic should be specific and actionable
- Topics should cover different aspects of PHP Programming
- Avoid duplicating previously approved or rejected topics
```

## Benefits

- **Avoids Duplicates**: By showing the AI what has already been approved, we prevent it from suggesting the same topics again.
- **Improves Quality**: By showing the AI what has been rejected, we guide it away from unwanted ideas.
- **Learns Preferences**: Over time, the AI gets better at suggesting topics that align with the author's niche and style.

## Implementation Details

The feedback loop logic is implemented in `AIPS_Author_Topics_Generator::build_topic_generation_prompt()`. It uses the `AIPS_Author_Topics_Repository` to fetch the approved and rejected topic summaries.
