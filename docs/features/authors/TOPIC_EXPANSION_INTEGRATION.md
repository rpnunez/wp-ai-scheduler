# Topic Expansion Integration

## Overview

This document outlines how the Topic Expansion feature integrates with the existing Authors feature.

## Integration Points

### 1. UI Integration

- **Topics List**: Add an "Expand" button to the actions column for each topic.
- **Modal**: Create a new modal for configuring the expansion (number of topics).

### 2. Backend Integration

- **Controller**: Add `wp_ajax_aips_expand_topic` to `AIPS_Author_Topics_Controller`.
- **Service**: Add `expand_topic()` method to `AIPS_Author_Topics_Generator`.
- **Repository**: Update `AIPS_Author_Topics_Repository` to support parent-child relationships (optional).

### 3. Database Changes (Optional)

- Add `parent_topic_id` column to `wp_aips_author_topics` to track relationships.

## Workflow

1. User clicks "Expand" on a topic.
2. Modal appears asking for the number of topics (default: 5).
3. User confirms.
4. AJAX request is sent to `wp_ajax_aips_expand_topic`.
5. Backend calls AI to generate new topics based on the selected one.
6. New topics are saved as "pending".
7. UI refreshes to show the new topics.

## Example Prompt

```
Generate 5 new blog post topics related to: "Advanced PHP Performance Tuning"

The new topics should be specific sub-topics or related angles that dive deeper into the subject.

Avoid duplicating these existing topics:
- PHP Memory Management
- Optimizing Database Queries

Format the output as a list of titles.
```
