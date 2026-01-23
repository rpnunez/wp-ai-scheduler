# Post Topic Generation Workflow

## Question: When is a Post Scheduled After Topic Approval?

This document explains how the Post Topic Generation system works and specifically answers when a post is scheduled to generate after an Admin approves a topic.

---

## Quick Answer

**When an Admin approves a topic, the post is NOT immediately scheduled.** Instead:

1. The approved topic is marked as `status='approved'` and made available in a pool of approved topics
2. Posts are generated on a **separate schedule** controlled by the Author's `post_generation_frequency` setting
3. When the scheduled time arrives (based on `post_generation_next_run`), the system automatically:
   - Picks the NEXT available approved topic for that author
   - Generates a post from that topic
   - Schedules the next post generation based on the frequency

**Key Point:** Topic approval and post generation are **decoupled**. Approval doesn't trigger immediate scheduling; it makes the topic available for the next scheduled post generation run.

---

## Detailed Workflow

### 1. Topic Generation (First Phase)

Topics are generated automatically by the WordPress cron system:

- **Cron Hook:** `aips_generate_author_topics` (runs hourly)
- **Scheduler Class:** `AIPS_Author_Topics_Scheduler`
- **Process:**
  1. Finds all active authors where `topic_generation_next_run <= NOW()`
  2. Generates multiple topics per author (quantity set in Author config)
  3. All new topics start with `status='pending'`
  4. Updates `topic_generation_next_run` based on author's `topic_generation_frequency`

**File:** `includes/class-aips-author-topics-scheduler.php`

```php
// Hook at line 59
add_action('aips_generate_author_topics', array($this, 'process_topic_generation'));

// Process at line 67
public function process_topic_generation() {
    // Get all authors due for topic generation
    $due_authors = $this->authors_repository->get_due_for_topic_generation();
    
    foreach ($due_authors as $author) {
        $this->generate_topics_for_author($author);
    }
}
```

---

### 2. Admin Approval (Manual Step)

An administrator reviews pending topics and makes decisions:

- **Actions Available:**
  - **Approve:** Changes `status='approved'`, logs the approval
  - **Reject:** Changes `status='rejected'`, logs the rejection
  - **Edit:** Updates topic title
  - **Delete:** Removes the topic
  - **Generate Now:** Immediately creates a post (bypasses normal schedule)

- **AJAX Endpoint:** `wp_ajax_aips_approve_topic`
- **Controller Class:** `AIPS_Author_Topics_Controller`

**File:** `includes/class-aips-author-topics-controller.php`

```php
// Approval process at line 84-140
public function ajax_approve_topic() {
    // Validate and get topic
    $topic = $this->topics_repository->get_by_id($topic_id);
    
    // Update status to 'approved'
    $this->topics_repository->update_status($topic_id, 'approved', $user_id);
    
    // Log the approval
    $this->logs_repository->create(array(
        'author_topic_id' => $topic_id,
        'action' => 'approved',
        'user_id' => $user_id,
        // ...
    ));
    
    // Apply reward via penalty service (learning system)
    $this->penalty_service->apply_reward($topic_id, $feedback);
    
    // NO POST SCHEDULING HAPPENS HERE - just marks topic as available
}
```

**Important:** Approving a topic does NOT schedule a post. It only:
1. Changes the topic status to `approved`
2. Records the approval in the logs
3. Applies feedback to the learning system

---

### 3. Post Generation (Second Phase - The Answer!)

Posts are generated on a **completely separate schedule** from topic generation:

- **Cron Hook:** `aips_generate_author_posts` (runs hourly)
- **Generator Class:** `AIPS_Author_Post_Generator`
- **Timing:** Based on each author's `post_generation_frequency` and `post_generation_next_run`

**File:** `includes/class-aips-author-post-generator.php`

```php
// Hook at line 83
add_action('aips_generate_author_posts', array($this, 'process_post_generation'));

// Process at line 91-110
public function process_post_generation() {
    // Get all authors where post_generation_next_run <= NOW()
    $due_authors = $this->authors_repository->get_due_for_post_generation();
    
    if (empty($due_authors)) {
        $this->logger->log('No authors due for post generation', 'info');
        return;
    }
    
    // Process each author
    foreach ($due_authors as $author) {
        $this->generate_post_for_author($author);
    }
}

// Generate post for one author at lines 118-141
public function generate_post_for_author($author) {
    // Get the NEXT approved topic for this author (just ONE)
    $topics = $this->topics_repository->get_approved_for_generation($author->id, 1);
    
    if (empty($topics)) {
        $this->logger->log("No approved topics available for author {$author->id}", 'warning');
        // Still update schedule to avoid getting stuck
        $this->update_author_schedule($author);
        return new WP_Error('no_topics', 'No approved topics available');
    }
    
    $topic = $topics[0]; // Take the first approved topic
    
    // Generate the post using the topic
    $result = $this->generate_post_from_topic($topic, $author);
    
    // Update the author's next run time
    $this->update_author_schedule($author);
    
    return $result;
}

// Schedule update at lines 274-281
private function update_author_schedule($author) {
    // Calculate next run time based on author's frequency
    $next_run = $this->interval_calculator->calculate_next_run($author->post_generation_frequency);
    
    $this->authors_repository->update_post_generation_schedule($author->id, $next_run);
    
    $this->logger->log("Updated post generation schedule for author {$author->id}. Next run: {$next_run}", 'info');
}
```

---

## Scheduling Logic Details

### Author Configuration Fields

Each Author has two separate scheduling configurations:

| Field | Purpose | Default | Options |
|-------|---------|---------|---------|
| `topic_generation_frequency` | How often to generate NEW topics | `weekly` | hourly, every_4_hours, daily, weekly, monthly, etc. |
| `post_generation_frequency` | How often to generate posts from approved topics | `daily` | hourly, every_4_hours, daily, weekly, monthly, etc. |
| `topic_generation_next_run` | When to run topic generation next | Auto-calculated | MySQL datetime |
| `post_generation_next_run` | When to run post generation next | Auto-calculated | MySQL datetime |

### How Scheduling Works

**Database Query for Due Authors:**

```php
// File: includes/class-aips-authors-repository.php
// Lines 139-149
public function get_due_for_post_generation() {
    $current_time = current_time('mysql');
    return $this->wpdb->get_results($this->wpdb->prepare(
        "SELECT * FROM {$this->table_name} 
        WHERE is_active = 1 
        AND post_generation_next_run IS NOT NULL 
        AND post_generation_next_run <= %s
        ORDER BY post_generation_next_run ASC",
        $current_time
    ));
}
```

**Calculation of Next Run Time:**

```php
// File: includes/class-aips-interval-calculator.php
// Lines 122-165

public function calculate_next_run($frequency, $base_time = null) {
    $base_time = $base_time ?: time();
    
    switch ($frequency) {
        case 'hourly':
            return strtotime('+1 hour', $base_time);
        case 'every_4_hours':
            return strtotime('+4 hours', $base_time);
        case 'daily':
            return strtotime('+1 day', $base_time);
        case 'weekly':
            return strtotime('+1 week', $base_time);
        case 'monthly':
            return strtotime('+1 month', $base_time);
        // ... more options
    }
}
```

---

## Example Timeline

Let's say you create an Author with these settings:

- **Name:** PHP Expert
- **Topic Generation Frequency:** Weekly
- **Post Generation Frequency:** Daily
- **Topic Generation Quantity:** 10

### Timeline:

**Monday, 9:00 AM** - Author created
- `topic_generation_next_run` = Monday, 9:00 AM (immediately)
- `post_generation_next_run` = Monday, 9:00 AM (immediately)

**Monday, 9:00 AM** - First cron run
- ✅ **Topic Generation:** Creates 10 pending topics
- `topic_generation_next_run` = Next Monday, 9:00 AM (weekly)
- ✅ **Post Generation:** No approved topics yet, skips
- `post_generation_next_run` = Tuesday, 9:00 AM (daily)

**Monday, 2:00 PM** - Admin reviews topics
- 👍 Approves 8 topics
- 👎 Rejects 2 topics
- Now 8 topics with `status='approved'` exist

**Tuesday, 9:00 AM** - Second cron run
- ⏭️ **Topic Generation:** Skipped (not due until next Monday)
- ✅ **Post Generation:** Generates post from first approved topic
- `post_generation_next_run` = Wednesday, 9:00 AM (daily)

**Wednesday, 9:00 AM** - Third cron run
- ⏭️ **Topic Generation:** Skipped
- ✅ **Post Generation:** Generates post from second approved topic
- `post_generation_next_run` = Thursday, 9:00 AM (daily)

**...continues daily...**

**Next Monday, 9:00 AM** - Topic generation week
- ✅ **Topic Generation:** Creates 10 NEW pending topics (using feedback from previous approved/rejected topics)
- `topic_generation_next_run` = Following Monday, 9:00 AM
- ✅ **Post Generation:** Generates post from seventh approved topic (still working through original batch)
- `post_generation_next_run` = Next Tuesday, 9:00 AM

---

## Key Takeaways

### ❌ What Does NOT Happen When You Approve a Topic:
- Post is NOT immediately created
- Post is NOT scheduled for a specific time
- No WordPress post exists yet
- No cron job is created for that specific topic

### ✅ What DOES Happen When You Approve a Topic:
- Topic status changes from `pending` to `approved`
- Topic becomes available in the pool of approved topics
- Approval is logged in `wp_aips_author_topic_logs`
- Feedback is recorded for the learning system
- Topic waits for the next scheduled post generation run

### 📅 When the Post Actually Gets Created:
- The next time `aips_generate_author_posts` cron runs
- AND the author's `post_generation_next_run` has arrived
- AND there are approved topics available
- The system takes ONE approved topic and generates a post
- Then schedules the next post generation based on `post_generation_frequency`

---

## Manual Override Option

If you want to generate a post immediately without waiting for the schedule:

**File:** `includes/class-aips-author-topics-controller.php`

```php
// Lines 268-332
public function ajax_generate_post_from_topic() {
    // Admin clicks "Generate Post Now" button
    // This bypasses the normal schedule
    $post_id = $this->post_generator->generate_now($topic_id);
    
    // Post is created immediately
    // Normal schedule is NOT affected
}
```

This is useful when you want to publish a post right away for a timely topic, without waiting for the next scheduled run.

---

## Summary Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                     TOPIC GENERATION                         │
│  (Runs based on topic_generation_frequency)                  │
│                                                              │
│  Cron: aips_generate_author_topics                          │
│  Creates: Multiple pending topics                            │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│                   ADMIN APPROVAL                             │
│               (Manual, anytime)                              │
│                                                              │
│  Action: Admin clicks "Approve"                              │
│  Result: status='pending' → status='approved'                │
│  Scheduling: NONE - just marks as available                  │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│                     POST GENERATION                          │
│  (Runs based on post_generation_frequency)                   │
│                                                              │
│  Cron: aips_generate_author_posts                           │
│  Takes: ONE approved topic (FIFO)                            │
│  Creates: WordPress post                                     │
│  Schedules: Next post based on frequency                     │
└─────────────────────────────────────────────────────────────┘
```

---

## Related Files

### Core Logic
- `class-aips-author-topics-controller.php` - Approval endpoints
- `class-aips-author-post-generator.php` - Post generation logic
- `class-aips-author-topics-scheduler.php` - Topic generation logic
- `class-aips-interval-calculator.php` - Scheduling math

### Data Access
- `class-aips-authors-repository.php` - Author queries including `get_due_for_post_generation()`
- `class-aips-author-topics-repository.php` - Topic queries including `get_approved_for_generation()`

### Configuration
- `class-aips-db-manager.php` - Database schema (lines 214-231 for authors table)

---

## Frequently Asked Questions

**Q: Can I schedule a specific topic to generate at a specific time?**

A: Not directly. The system uses a FIFO (First In, First Out) approach for approved topics. However, you can:
1. Use "Generate Post Now" for immediate generation
2. Adjust the `post_generation_frequency` to control how often posts are created
3. Approve topics in the order you want them published

**Q: What happens if there are no approved topics when the post generation cron runs?**

A: The system logs a warning, updates the schedule anyway (to avoid getting stuck), and waits for the next run. Once you approve topics, they'll be picked up on the next scheduled run.

**Q: Can I change the post generation frequency after creating an author?**

A: Yes, you can edit the author and change `post_generation_frequency`. The next run time will be recalculated based on the new frequency after the next post is generated.

**Q: Does the order of approval matter?**

A: The order matters in that topics are selected for post generation in the order they were approved (based on database insertion order). Topics approved first will typically be used first.

---

## Conclusion

**To directly answer the original question:**

When an Admin approves a topic, **the post is NOT scheduled at that moment**. Instead:

1. The topic is marked as `approved` and added to a pool
2. The post will be created at the **next scheduled post generation time**
3. The schedule is determined by the Author's `post_generation_frequency` setting (daily, weekly, etc.)
4. The `post_generation_next_run` field in the database controls when the next post will be generated
5. Posts are created one at a time, at regular intervals, pulling from the pool of approved topics

This design provides maximum flexibility:
- Admins can review and approve topics in batches
- Posts are published at predictable, regular intervals
- No need to manually schedule each individual post
- Full control over publishing frequency per author
