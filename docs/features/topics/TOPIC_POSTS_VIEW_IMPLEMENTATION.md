# Topic Posts View Feature Implementation

## Overview
This document describes the implementation of the **Topic Posts View** feature, which allows users to see how many posts have been generated from each topic and view detailed information about those posts.

## User Story
As a user managing AI-generated content, I want to:
1. See at a glance how many posts have been generated from each topic
2. Click on the post count to view detailed information about those posts
3. Access the generated posts directly from the topics view

## Feature Components

### 1. Post Count Badge
**Location**: Topics Modal → Topic Title Column

**Appearance**:
- Blue badge with white text
- Displays the number of posts generated from that topic
- Icon: WordPress post dashicon
- Only appears when post count > 0

**Interaction**:
- Clickable badge that opens the Topic Posts Modal
- Hover effect for better UX
- Tooltip: "Click to view posts generated from this topic"

### 2. Topic Posts Modal
**Location**: Opens as overlay modal

**Content**:
- **Title**: "Posts Generated from Topic: [Topic Name]"
- **Table Columns**:
  - Post ID
  - Post Title
  - Date Generated
  - Date Published (or "Not published" if still draft)
  - Actions (Edit/View buttons)

**Actions**:
- **Edit**: Opens WordPress post editor in new tab
- **View**: Opens published post in new tab (only for published posts)

## Technical Implementation

### Backend Changes

#### 1. Modified `ajax_get_author_topics()` in `class-aips-authors-controller.php`
**Purpose**: Include post count for each topic

**Logic**:
```php
foreach ($topics as &$topic) {
    $logs = $this->logs_repository->get_by_topic($topic->id);
    $post_count = 0;
    foreach ($logs as $log) {
        if ($log->action === 'post_generated' && $log->post_id) {
            $post_count++;
        }
    }
    $topic->post_count = $post_count;
}
```

**Why**: The topics list now includes a `post_count` field that the frontend can display.

#### 2. Added `ajax_get_topic_posts()` in `class-aips-authors-controller.php`
**Purpose**: Fetch all posts generated from a specific topic

**Request Parameters**:
- `topic_id`: The ID of the topic

**Response Data**:
```json
{
  "success": true,
  "data": {
    "topic": {
      "id": 123,
      "topic_title": "How AI is Transforming Healthcare"
    },
    "posts": [
      {
        "post_id": 456,
        "post_title": "AI Revolution in Medical Diagnosis",
        "post_status": "publish",
        "date_generated": "2024-01-15 10:30:00",
        "date_published": "2024-01-16 08:00:00",
        "post_url": "https://example.com/ai-medical-diagnosis",
        "edit_url": "https://example.com/wp-admin/post.php?post=456&action=edit"
      }
    ]
  }
}
```

**Database Query**:
1. Get all logs for the topic from `author_topic_logs` table
2. Filter for logs with action = 'post_generated' and valid post_id
3. Fetch WordPress post data for each post ID
4. Return enriched data with post metadata

### Frontend Changes

#### 1. Updated `renderTopics()` in `assets/js/authors.js`
**Purpose**: Display post count badge next to topic titles

**Changes**:
```javascript
// Add post count badge if there are any posts
if (topic.post_count && topic.post_count > 0) {
    html += ' <span class="aips-post-count-badge" data-topic-id="' + topic.id + '" title="' + aipsAuthorsL10n.viewPosts + '">';
    html += '<span class="dashicons dashicons-admin-post"></span> ' + topic.post_count;
    html += '</span>';
}
```

#### 2. Added `viewTopicPosts()` in `assets/js/authors.js`
**Purpose**: Handle click on post count badge

**Flow**:
1. Extract topic ID from clicked badge
2. Show loading state
3. Open Topic Posts Modal
4. Call `loadTopicPosts()` to fetch data

#### 3. Added `loadTopicPosts()` in `assets/js/authors.js`
**Purpose**: Fetch posts from backend via AJAX

**AJAX Call**:
- Action: `aips_get_topic_posts`
- Data: `topic_id`, `nonce`
- Success: Call `renderTopicPosts()` with response data
- Error: Display error message

#### 4. Added `renderTopicPosts()` in `assets/js/authors.js`
**Purpose**: Render posts table in modal

**Table Structure**:
```html
<table class="wp-list-table widefat fixed striped">
  <thead>
    <tr>
      <th>Post ID</th>
      <th>Post Title</th>
      <th>Date Generated</th>
      <th>Date Published</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <!-- Post rows -->
  </tbody>
</table>
```

#### 5. Added Modal HTML in `templates/admin/authors.php`
**Structure**:
```html
<div id="aips-topic-posts-modal" class="aips-modal">
    <div class="aips-modal-content aips-modal-large">
        <span class="aips-modal-close">&times;</span>
        <h2 id="aips-topic-posts-modal-title">Posts Generated from Topic</h2>
        <div id="aips-topic-posts-content">
            <!-- Content loaded via JavaScript -->
        </div>
    </div>
</div>
```

#### 6. Added CSS Styles in `assets/css/authors.css`
**Badge Styling**:
```css
.aips-post-count-badge {
    display: inline-block;
    padding: 2px 8px;
    margin-left: 8px;
    background-color: #2271b1;
    color: #fff;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.aips-post-count-badge:hover {
    background-color: #135e96;
}
```

### Localization

Added 20+ new translation strings in `class-aips-settings.php`:

| Key | Default Value |
|-----|---------------|
| `viewPosts` | "Click to view posts generated from this topic" |
| `loadingPosts` | "Loading posts..." |
| `errorLoadingPosts` | "Error loading posts." |
| `noPostsFound` | "No posts have been generated from this topic yet." |
| `postsGeneratedFrom` | "Posts Generated from Topic" |
| `postId` | "Post ID" |
| `postTitle` | "Post Title" |
| `dateGenerated` | "Date Generated" |
| `datePublished` | "Date Published" |
| `notPublished` | "Not published" |
| `editPost` | "Edit" |
| `viewPost` | "View" |

## User Flow

### Viewing Topics
1. User clicks "View Topics" button for an author
2. Topics modal opens showing pending/approved/rejected topics
3. Each topic now shows a blue badge with post count (if > 0)
4. Example: "AI in Healthcare **[📄 3]**"

### Viewing Posts from a Topic
1. User clicks on the post count badge
2. Topic Posts Modal opens with loading state
3. Posts table loads showing:
   - All posts generated from that topic
   - Post ID, Title, Generation Date, Publish Date
   - Edit and View buttons
4. User can click "Edit" to modify the post
5. User can click "View" to see the published post
6. User closes modal to return to topics view

## Data Flow

```
User Action (Click Badge)
    ↓
JavaScript: viewTopicPosts()
    ↓
AJAX Request: aips_get_topic_posts
    ↓
PHP: ajax_get_topic_posts()
    ↓
Database Query: author_topic_logs table
    ↓
Enrich with WordPress post data
    ↓
JSON Response
    ↓
JavaScript: renderTopicPosts()
    ↓
Display in Modal
```

## Database Relationships

```
authors (author_id)
    ↓
author_topics (topic_id)
    ↓
author_topic_logs (log entries)
    ├─ action: 'post_generated'
    └─ post_id → WordPress posts table
```

## Testing Checklist

- [ ] Post count badge appears when topics have generated posts
- [ ] Badge shows correct count (1, 2, 3, etc.)
- [ ] Badge does not appear for topics with 0 posts
- [ ] Clicking badge opens Topic Posts Modal
- [ ] Modal title shows topic name correctly
- [ ] Posts table displays all columns
- [ ] Date Generated is populated correctly
- [ ] Date Published shows "Not published" for drafts
- [ ] Date Published shows actual date for published posts
- [ ] Edit button opens WordPress editor in new tab
- [ ] View button only appears for published posts
- [ ] View button opens post in new tab
- [ ] Modal close button works
- [ ] Multiple topics can have different post counts
- [ ] Error handling works if topic or posts not found

## Performance Considerations

### Current Implementation
- Post counts are calculated on-demand when loading topics
- One query per topic to count posts
- For N topics: N+1 queries (1 for topics, N for counts)

### Future Optimization (if needed)
If performance becomes an issue with many topics:
1. Add a `post_count` column to `author_topics` table
2. Increment/decrement on post generation/deletion
3. Use a single JOIN query to get all counts at once

## Security

All endpoints are protected:
- Nonce verification: `check_ajax_referer('aips_ajax_nonce', 'nonce')`
- Capability check: `current_user_can('manage_options')`
- Input sanitization: `absint()` for IDs
- Output escaping: `esc_html()`, `esc_attr()` in templates

## Browser Compatibility

The feature uses standard web technologies:
- JavaScript: ES6 (supported by all modern browsers)
- CSS: Flexbox and standard properties
- WordPress Dashicons for icons
- jQuery (bundled with WordPress)

## Accessibility

- Semantic HTML table structure
- Clear labels and headings
- Keyboard navigation support (tab, enter, escape)
- ARIA labels for modal
- Focus management when opening/closing modals

## Future Enhancements

Potential improvements:
1. Add filtering/sorting to posts table
2. Add pagination for topics with many posts
3. Show post status badge (draft, published, scheduled)
4. Add bulk actions for posts
5. Add post analytics (views, engagement)
6. Export posts list to CSV
7. Add post preview thumbnail
8. Show post excerpt in table
