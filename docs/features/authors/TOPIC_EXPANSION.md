# Topic Expansion Feature

## Overview

The **Topic Expansion** feature allows users to generate new, related topic ideas from an existing topic. This is useful when a particular topic resonates well and you want to explore it further.

## User Story

As a content manager, I want to:
1. Select an existing topic that I like.
2. Ask the AI to generate 5-10 new topic ideas based on that one.
3. Review and approve/reject these new sub-topics.

## Workflow

1. **Select Topic**: In the Topics list, click the "Expand" button next to a topic.
2. **Configure**: A modal appears asking how many new topics to generate (default: 5).
3. **Generate**: The system calls the AI with a prompt like:
   > "Generate 5 new blog post topics related to '[Original Topic]'. The new topics should be specific sub-topics or related angles."
4. **Review**: The new topics appear in the "Pending" list, linked to the original topic (optional parent-child relationship).

## Technical Implementation

### Backend

- **Endpoint**: `wp_ajax_aips_expand_topic`
- **Logic**:
  - Fetch the original topic.
  - Construct a prompt using the original topic as the seed.
  - Call the AI API.
  - Save the new topics to `wp_aips_author_topics`.

### Frontend

- **UI**: Add an "Expand" button to the topic actions.
- **Modal**: Simple input for the number of topics.

## Future Enhancements

- **Tree View**: Visualize the relationship between parent and child topics.
- **Deep Expansion**: Recursively expand topics to create a content cluster.
