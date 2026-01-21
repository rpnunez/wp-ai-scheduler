# Authors Feature Implementation Guide

## Overview

The **Authors Feature** introduces a new workflow for generating blog posts that solves the problem of duplicate content topics. Instead of generating posts directly, it generates topic ideas first, allows admin review, and then generates posts from approved topics. This creates better content diversity and gives editors control over what gets published.

## Problem Statement

When using the current Templates feature with prompts like "write about a popular PHP framework", the AI might generate 10 different posts all about Laravel (the most popular framework), resulting in duplicate content. The Authors feature solves this by:

1. **Generating diverse topics first** - AI creates multiple topic ideas
2. **Admin review** - Editors approve/reject topics before post generation
3. **Feedback loop** - The system learns from approved/rejected topics to improve future suggestions
4. **Scheduled generation** - Topics and posts are generated on separate schedules

## Architecture

### Database Tables

#### `wp_aips_authors`
Stores author configurations for different content verticals.

**Key fields:**
- `name` - Display name (e.g., "PHP Expert")
- `field_niche` - The topic domain (e.g., "PHP Programming")
- `topic_generation_frequency` - How often to generate new topics (daily, weekly, etc.)
- `post_generation_frequency` - How often to generate posts from approved topics
- `topic_generation_quantity` - Number of topics to generate per run
- `article_structure_id` - Optional article structure to use for posts
- `is_active` - Whether this author is currently active

#### `wp_aips_author_topics`
Stores generated topic ideas awaiting review or approved for post generation.

**Key fields:**
- `author_id` - Links to the author
- `topic_title` - The suggested blog post title
- `status` - Current state: `pending`, `approved`, or `rejected`
- `reviewed_at` - When the topic was reviewed
- `reviewed_by` - User ID who reviewed it

#### `wp_aips_author_topic_logs`
Audit trail for all actions on topics and generated posts.

**Key fields:**
- `author_topic_id` - Links to the topic
- `post_id` - WordPress post ID (if a post was generated)
- `action` - Type of action: `approved`, `rejected`, `edited`, `post_generated`
- `user_id` - Who performed the action
- `notes` - Additional context (e.g., old title for edits)
- `metadata` - JSON data for AI calls, etc.

### Code Structure

```
├── Repository Layer (Data Access)
│   ├── AIPS_Authors_Repository - Author CRUD
│   ├── AIPS_Author_Topics_Repository - Topic management
│   └── AIPS_Author_Topic_Logs_Repository - Audit logging
│
├── Service Layer (Business Logic)
│   ├── AIPS_Author_Topics_Generator - Generates topics using AI
│   ├── AIPS_Author_Topics_Scheduler - Schedules topic generation
│   └── AIPS_Author_Post_Generator - Generates posts from approved topics
│
├── Controller Layer (AJAX Endpoints)
│   ├── AIPS_Authors_Controller - Author management endpoints
│   └── AIPS_Author_Topics_Controller - Topic approval workflow endpoints
│
└── UI Layer (Admin Interface)
    └── templates/admin/authors.php - Admin page
```

## Workflow

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

## Implementation Status

### ✅ Complete
- Database schema with 3 new tables
- Repository layer for data access
- Service layer for topic/post generation with feedback loop
- Controller layer with AJAX endpoints
- Basic UI showing authors with stats
- Integration with main plugin (classes loaded, cron schedules set up)
- Authors menu in WordPress admin

### 🚧 Partially Complete
- Admin UI has basic structure but needs:
  - Full AJAX wiring for all buttons
  - Topic review interface (approve/reject/edit inline)
  - Generated posts view
  - View Log functionality

### ⏳ Not Yet Implemented
- Full JavaScript implementation for:
  - Saving authors
  - Loading author data for editing
  - Viewing/filtering topics by status
  - Bulk approve/reject actions
  - Inline topic editing
  - Post regeneration
  - Detailed logging views
- Tests for repositories, services, and controllers
- Error handling and validation improvements
- Documentation for end users

## Next Steps for Completion

### Priority 1: Core Functionality
1. **Wire up Author CRUD** - Make save/edit/delete actually work
2. **Implement Topic Review UI** - Full interface for approving/rejecting topics
3. **Test End-to-End** - Create author → generate topics → approve → generate posts

### Priority 2: Enhanced Features
1. **Topic Detail Modal** - Show all topic details with action buttons
2. **Generated Posts View** - List all posts with links and actions
3. **View Log Feature** - Show complete audit trail

### Priority 3: Polish
1. **Better UI/UX** - Enhance styling, add loading states, error messages
2. **Bulk Actions** - Select multiple topics to approve/reject at once
3. **Filters and Search** - Find topics/authors easily
4. **Documentation** - User guide and developer docs

## Usage Example

### Creating an Author for PHP Content

1. Go to **AI Post Scheduler → Authors**
2. Click **Add New Author**
3. Fill in:
   - Name: "PHP Expert"
   - Field/Niche: "PHP Programming"
   - Topic Generation: Weekly, 10 topics
   - Post Generation: Daily
4. Save and activate

### Workflow After Creation

- **Week 1**: System generates 10 PHP topic ideas (e.g., "Best PHP Frameworks in 2024", "PHP 8.3 New Features", etc.)
- **Admin reviews**: Approves 8, rejects 2 (too similar to existing content)
- **Daily**: System generates one post per day from approved topics
- **Week 2**: System generates 10 NEW topics, using approved/rejected history to avoid duplicates
- **Result**: Diverse content covering different aspects of PHP

## Feature: Reassigning Topics to Different Authors

### Overview

The **Topic Reassignment** feature allows administrators to move topics from one author to another. This is useful when:
- A topic is better suited for a different content vertical
- An author's niche has changed
- You want to reorganize topics across authors

### How to Use

1. Navigate to **AI Post Scheduler → Authors**
2. Click **View Topics** on an author
3. In any tab (Pending, Approved, or Rejected), find the topic you want to reassign
4. Click the **Reassign** button next to the topic
5. In the modal:
   - Select the new author from the dropdown
   - Optionally provide a reason for the reassignment
6. Click **Reassign Topic**

### What Happens

- The topic's `author_id` is updated to the new author
- A log entry is created with action type `reassigned`
- The log includes:
  - Old and new author IDs
  - New author's name
  - Reason (if provided)
  - User who performed the action
- The topic maintains its current status (pending/approved/rejected)

### Database Changes

- **Table**: `wp_aips_author_topics`
- **Column**: `author_id` - Updated to new author's ID
- **Table**: `wp_aips_author_topic_logs`
- **New entry**: Action = `reassigned`, includes full context

### Use Cases

**Example 1: Better Fit**
- Topic: "Understanding JavaScript Closures" 
- Originally assigned to: PHP Expert
- Reassigned to: JavaScript Guru
- Reason: "Topic is about JavaScript, not PHP"

**Example 2: Niche Expansion**
- Topic: "Building REST APIs"
- Originally assigned to: Frontend Developer
- Reassigned to: Backend Developer
- Reason: "Better aligned with backend content strategy"

---

## Feature: One-Click Post Regeneration

### Overview

The **Post Regeneration** feature allows administrators to regenerate a post from the same approved topic with a single click. This is useful when:
- The generated content quality is poor
- You want a different take on the same topic
- The AI produced off-topic content

### How to Use

1. Navigate to **AI Post Scheduler → Authors**
2. Click **View Topics** on an author
3. Go to the **Approved** tab
4. Click the post count badge on any topic (shows number like "📄 3")
5. In the **Posts Generated from Topic** modal, you'll see all posts
6. Click the **Regenerate** button next to any post
7. Confirm the action in the dialog

### What Happens

- The existing post is set to `draft` status (preserving original publish status)
- The AI generates a completely new post from the same topic
- Both posts remain in the system:
  - Old post: Draft (can be manually published if preferred)
  - New post: Created with author's default post status
- A new log entry is created for the regeneration

### Important Notes

- **Old post is preserved**: The original post is NOT deleted, only changed to draft
- **Topic remains approved**: The topic stays in approved status for future use
- **New content**: The AI generates completely fresh content, not a revision
- **Same topic**: Uses the exact same topic title and prompt

### Database Changes

- **WordPress**: Old post's `post_status` changed to `draft`
- **Meta**: Old post status stored in post meta `_aips_original_post_status`
- **Table**: `wp_aips_author_topic_logs`
- **New entry**: Action = `post_generated` for the new post

### Use Cases

**Example 1: Quality Issues**
```
Topic: "Best PHP Frameworks in 2024"
Issue: First generation focused only on Laravel
Action: Regenerate to get broader framework coverage
Result: New post covers Laravel, Symfony, Slim, etc.
```

**Example 2: Outdated Content**
```
Topic: "PHP 8.3 New Features"
Issue: Generated before official release, missing details
Action: Regenerate after release
Result: Complete, accurate coverage of final feature set
```

**Example 3: Wrong Tone**
```
Topic: "Understanding Database Indexes"
Issue: Too technical for target audience
Action: Regenerate
Result: More beginner-friendly explanation
```

---

## Approval Queue Enhancements

The approval queue system has been enhanced with these new features integrated seamlessly:

### Workflow

1. **Topic Generation** → Topics created with `status = 'pending'`
2. **Review** → Admin approves/rejects/reassigns topics
3. **Post Generation** → System creates posts from approved topics
4. **Quality Control** → Admin can regenerate if needed
5. **Reassignment** → Topics can be moved to different authors at any stage

### Admin Interface

**Topics Modal Tabs:**
- **Pending Review**: Approve, Reject, Edit, Reassign, Delete
- **Approved**: Generate Post Now, Edit, Reassign, Delete, View Posts
- **Rejected**: Edit, Reassign, Delete
- **Feedback History**: View all approval/rejection decisions

**Actions Available:**
- Individual actions: Approve, Reject, Edit title, Reassign, Delete
- Bulk actions: Approve multiple, Reject multiple, Delete multiple
- Post management: Generate, Regenerate, View, Edit

### Best Practices

1. **Review Regularly**: Check pending topics weekly to maintain content flow
2. **Use Reassignment**: Move misaligned topics instead of rejecting them
3. **Regenerate Wisely**: Use for quality issues, not minor tweaks (edit post instead)
4. **Provide Reasons**: When approving/rejecting/reassigning, add context for future reference
5. **Monitor Posts**: Check generated posts before regenerating - editing may be faster

---

### Manual Test Plan

1. **Test Author Creation**
   ```
   - Create an author
   - Verify it appears in the list
   - Edit the author
   - Verify changes save
   ```

2. **Test Topic Generation**
   ```
   - Manually trigger: call AJAX endpoint for author
   - Verify topics appear with "pending" status
   - Check feedback loop: topics should be diverse
   ```

3. **Test Topic Approval**
   ```
   - Approve a topic
   - Verify status changes to "approved"
   - Check logs table for approval record
   ```

4. **Test Post Generation**
   ```
   - Manually trigger post generation
   - Verify post is created in WordPress
   - Check logs table links topic to post
   ```

### Automated Tests (To Be Created)

```php
// Example: Test feedback loop
public function test_feedback_loop_includes_approved_topics() {
    $author = $this->create_author();
    $this->create_approved_topics($author->id, ['Topic A', 'Topic B']);
    
    $generator = new AIPS_Author_Topics_Generator();
    $prompt = $generator->build_topic_generation_prompt($author);
    
    $this->assertStringContainsString('Topic A', $prompt);
    $this->assertStringContainsString('Topic B', $prompt);
}
```

## API Reference

### AJAX Endpoints

#### Author Management

- **`wp_ajax_aips_save_author`** - Create/update author
- **`wp_ajax_aips_get_author`** - Get author by ID
- **`wp_ajax_aips_delete_author`** - Delete author
- **`wp_ajax_aips_get_author_topics`** - Get topics for author
- **`wp_ajax_aips_get_author_posts`** - Get generated posts
- **`wp_ajax_aips_generate_topics_now`** - Manual topic generation

#### Topic Management

- **`wp_ajax_aips_approve_topic`** - Approve a topic
- **`wp_ajax_aips_reject_topic`** - Reject a topic
- **`wp_ajax_aips_edit_topic`** - Edit topic title
- **`wp_ajax_aips_delete_topic`** - Delete topic
- **`wp_ajax_aips_generate_post_from_topic`** - Generate post now
- **`wp_ajax_aips_get_topic_logs`** - View topic audit trail
- **`wp_ajax_aips_bulk_approve_topics`** - Bulk approve
- **`wp_ajax_aips_bulk_reject_topics`** - Bulk reject
- **`wp_ajax_aips_regenerate_post`** - Regenerate existing post
- **`wp_ajax_aips_delete_generated_post`** - Delete generated post

### Repository Methods

#### AIPS_Authors_Repository

```php
get_all($active_only = false) // Get all authors
get_by_id($id) // Get single author
create($data) // Create author
update($id, $data) // Update author
delete($id) // Delete author
get_due_for_topic_generation() // Get authors due for topics
get_due_for_post_generation() // Get authors due for posts
```

#### AIPS_Author_Topics_Repository

```php
get_by_author($author_id, $status = null) // Get author's topics
get_by_id($id) // Get single topic
create($data) // Create topic
update($id, $data) // Update topic
delete($id) // Delete topic
update_status($id, $status, $user_id) // Change topic status
get_approved_for_generation($author_id, $limit = 1) // Get approved topics
get_approved_summary($author_id, $limit = 20) // For feedback loop
get_rejected_summary($author_id, $limit = 20) // For feedback loop
get_status_counts($author_id) // Count by status
```

## Troubleshooting

### Topics Not Generating

1. Check cron is running: `wp cron event list`
2. Check author has `topic_generation_next_run` in the past
3. Check author is active: `is_active = 1`
4. Check AI Engine plugin is installed and configured

### Posts Not Generating

1. Check approved topics exist for the author
2. Check `post_generation_next_run` is in the past
3. Check cron for `aips_generate_author_posts` event
4. Check logs for errors

### Feedback Loop Not Working

1. Verify approved/rejected topics exist in database
2. Check `get_approved_summary()` returns data
3. Review generated prompts in logs
4. Ensure AI context isn't truncated (summarization working)

## Future Enhancements

### Possible Additions

1. **Topic Scoring** - Let AI rate topics by relevance/quality
2. **Auto-Approval** - Automatically approve high-scoring topics
3. **Topic Categories** - Group topics by subtopics
4. **Content Calendar** - Visual calendar for scheduled posts
5. **Multi-Author Support** - Multiple authors for same niche
6. **Analytics** - Track which topics perform best
7. **Topic Templates** - Pre-defined topic generation templates
8. **Collaboration** - Multiple admins can review topics
9. **Export/Import** - Share author configurations

## Conclusion

The Authors Feature provides a sophisticated workflow for generating diverse, reviewed content. The feedback loop ensures continuous improvement, and the separation of topic generation from post generation gives editors full control over what gets published.

The foundation is complete and functional. The remaining work is primarily UI polish and JavaScript implementation to connect the backend to the frontend.
