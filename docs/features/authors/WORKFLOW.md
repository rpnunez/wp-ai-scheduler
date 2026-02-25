# Authors Feature Workflow

## Overview

The Authors feature follows a structured workflow to generate, review, and publish content.

## Step-by-Step Process

### 1. Create an Author

Admin creates an author with:
- Name and field/niche (e.g., "PHP Programming")
- Topic generation schedule (e.g., weekly)
- Post generation schedule (e.g., daily)
- Number of topics to generate (e.g., 5)

### 2. Topic Generation (Automated)

On schedule, `AIPS_Author_Topics_Scheduler` runs:
1. Finds authors due for topic generation
2. Calls `AIPS_Author_Topics_Generator::generate_topics()`
3. AI generates topic ideas using:
   - The author's field/niche
   - Custom prompt (if provided)
   - **Feedback loop context**: Previously approved topics (to avoid duplicates) and rejected topics (to avoid similar ideas)
4. Topics are saved with `status='pending'`

### 3. Admin Review

Admin views topics in the UI:
- Sees pending topics grouped by author
- Can **Approve** - marks topic as ready for post generation
- Can **Reject** - marks topic as unwanted
- Can **Edit** - modify the topic title
- Can **Delete** - remove the topic entirely
- Can **Generate Post Now** - immediately generate a post (bypasses schedule)

### 4. Post Generation (Automated)

On schedule, `AIPS_Author_Post_Generator` runs:
1. Finds authors due for post generation
2. Gets one approved topic per author
3. Generates a blog post using the topic title
4. Links the post to the topic in logs
5. Topic remains in "approved" state (can regenerate if needed)

### 5. Feedback Loop

When generating new topics, the system:
- Summarizes the last 10-20 approved topics → "These topics worked well"
- Summarizes the last 10-20 rejected topics → "Avoid topics like these"
- Includes these summaries in the AI prompt for better diversity

## Key Features

### Feedback Loop

The magic happens in `AIPS_Author_Topics_Generator::build_topic_generation_prompt()`:

```php
// Include approved topics for context
$approved_topics = $this->topics_repository->get_approved_summary($author->id, 20);
if (!empty($approved_topics)) {
    $prompt .= "Previously approved topics (for diversity - avoid duplicating):\n";
    foreach ($approved_topics as $topic) {
        $prompt .= "- {$topic}\n";
    }
}

// Include rejected topics for context
$rejected_topics = $this->topics_repository->get_rejected_summary($author->id, 20);
if (!empty($rejected_topics)) {
    $prompt .= "Previously rejected topics (avoid similar ideas):\n";
    foreach ($rejected_topics as $topic) {
        $prompt .= "- {$topic}\n";
    }
}
```

This creates a learning system where the AI improves topic suggestions over time.

### Scheduled Execution

Two cron hooks handle automation:

1. **`aips_generate_author_topics`** (hourly)
   - Checks for authors with `topic_generation_next_run <= NOW()`
   - Generates topics
   - Updates `topic_generation_next_run` based on frequency

2. **`aips_generate_author_posts`** (hourly)
   - Checks for authors with `post_generation_next_run <= NOW()`
   - Gets one approved topic
   - Generates a post
   - Updates `post_generation_next_run` based on frequency
