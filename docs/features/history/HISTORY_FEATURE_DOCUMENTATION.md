# History Feature - Complete Documentation

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Core Components](#core-components)
4. [History Types](#history-types)
5. [History Containers](#history-containers)
6. [Usage Patterns](#usage-patterns)
7. [Database Schema](#database-schema)
8. [Data Flow](#data-flow)
9. [Integration Points](#integration-points)

---

## Overview

The History Feature is a unified logging and tracking system introduced in version 2.0.0. It consolidates what were previously three separate systems (Activity, History Log, and Generation Session) into a single, thread-safe, UUID-based tracking mechanism.

### Key Benefits

1. **Thread-Safe**: UUID-based containers eliminate race conditions in multi-cron environments
2. **Unified Interface**: Single `record()` method for all logging needs
3. **Complete Transparency**: Tracks every step of post generation with full context
4. **Flexible Categorization**: Type-based system allows filtering and display customization
5. **Rich Context**: Separate input, output, and context fields for detailed tracking

### Problem Solved

**Before**: 
- Data scattered across 3 tables (Activity, History, Generation Session)
- Race conditions when multiple crons ran simultaneously
- Inconsistent logging patterns across codebase
- No unified view of post generation process
- Exposed logic for session completion

**After**:
- Single unified history system with consistent patterns
- UUID-based tracking ensures correct association
- Expressive API with `record()` method
- Complete drill-down capability in "View Session" modal
- Encapsulated session/history management

---

## Architecture

### System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                     Application Layer                            │
│  (Generator, Scheduler, Author Post Generator, etc.)             │
└─────────────┬───────────────────────────────────────────────────┘
              │
              │ creates containers
              ▼
┌─────────────────────────────────────────────────────────────────┐
│                   AIPS_History_Service                           │
│                    (Factory Pattern)                             │
│                                                                   │
│  • create(type, metadata) → AIPS_History_Container              │
│  • get_activity_feed() → filtered history entries               │
└─────────────┬───────────────────────────────────────────────────┘
              │
              │ instantiates
              ▼
┌─────────────────────────────────────────────────────────────────┐
│              AIPS_History_Container                              │
│               (UUID-based Container)                             │
│                                                                   │
│  Properties:                                                      │
│    • uuid (unique identifier)                                    │
│    • history_id (database ID)                                    │
│    • type (e.g., 'post_generation')                              │
│    • metadata (context data)                                     │
│    • session (optional Generation_Session)                       │
│                                                                   │
│  Methods:                                                         │
│    • record(type, message, input, output, context)              │
│    • complete_success(result_data)                               │
│    • complete_failure(error_message, error_data)                 │
│    • with_session(context, voice)                                │
└─────────────┬───────────────────────────────────────────────────┘
              │
              │ uses
              ▼
┌─────────────────────────────────────────────────────────────────┐
│              AIPS_History_Repository                             │
│                (Database Layer)                                   │
│                                                                   │
│  • create(data) → creates history record                         │
│  • add_log_entry(history_id, type, details, type_id)            │
│  • update(history_id, data)                                      │
│  • get_by_id(history_id)                                         │
└─────────────┬───────────────────────────────────────────────────┘
              │
              │ persists to
              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Database Tables                               │
│                                                                   │
│  aips_history:                                                   │
│    • id (primary key)                                            │
│    • uuid (unique, indexed)                                      │
│    • status (processing/completed/failed)                        │
│    • post_id, template_id                                        │
│    • created_at, completed_at                                    │
│                                                                   │
│  aips_history_log:                                               │
│    • id (primary key)                                            │
│    • history_id (foreign key)                                    │
│    • log_type (string label)                                     │
│    • history_type_id (AIPS_History_Type constant)                │
│    • details (JSON: message, input, output, context)             │
│    • timestamp                                                    │
└──────────────────────────────────────────────────────────────────┘
```

### Component Responsibilities

| Component | Responsibility |
|-----------|---------------|
| **AIPS_History_Service** | Factory for creating containers; provides utility methods |
| **AIPS_History_Container** | Encapsulates a single process/session; manages lifecycle |
| **AIPS_History_Repository** | Database operations; abstracts SQL queries |
| **AIPS_History_Type** | Defines type constants; provides type utilities |
| **Application Code** | Creates containers; calls record() with rich context |

---

## Core Components

### 1. AIPS_History_Type

**Purpose**: Defines constants for categorizing history entries.

**Constants**:
```php
const LOG = 1;              // General log entry
const ERROR = 2;            // Error entry
const WARNING = 3;          // Warning entry
const INFO = 4;             // Info entry
const AI_REQUEST = 5;       // AI prompt/request
const AI_RESPONSE = 6;      // AI response/result
const DEBUG = 7;            // Debug information
const ACTIVITY = 8;         // High-level activity event
const SESSION_METADATA = 9; // Session-related metadata
```

**Key Methods**:
- `get_label($type)` - Returns human-readable label
- `get_all_types()` - Returns all types as array
- `is_activity_type($type)` - Checks if type should show in Activity feed

### 2. AIPS_History_Service

**Purpose**: Factory service for creating history containers.

**API**:
```php
// Create a new history container
$history = $service->create('post_generation', [
    'template_id' => 5,
    'author_id' => 1
]);

// Get activity feed (filtered to ACTIVITY types)
$activities = $service->get_activity_feed($limit, $offset, $filters);

// Utility methods
$service->post_has_history_and_completed($post_id);
$service->get_by_id($history_id);
$service->update_history_record($history_id, $data);
```

### 3. AIPS_History_Container

**Purpose**: Represents a single process or session with its associated logs.

**Lifecycle**:
```php
// 1. Create (automatically persists to DB with UUID)
$history = new AIPS_History_Container($repository, 'post_generation', $metadata);

// 2. Record events
$history->record('ai_request', 'Generating title', $prompt, null, ['component' => 'title']);
$history->record('ai_response', 'Title generated', null, $response, ['component' => 'title']);
$history->record('activity', 'Post created', $input, $output, $context);
$history->record('error', 'API timeout', null, null, ['retries' => 3]);

// 3. Complete
$history->complete_success(['post_id' => 123, 'title' => 'My Post']);
// or
$history->complete_failure('Connection timeout', ['api' => 'openai']);
```

**Key Properties**:
- `uuid` - Unique identifier (generated via wp_generate_uuid4())
- `history_id` - Database ID after persistence
- `type` - Container type (e.g., 'post_generation')
- `metadata` - Initial context data
- `session` - Optional Generation_Session tracker

**Key Methods**:
- `record($log_type, $message, $input, $output, $context)` - Main logging method
- `complete_success($result_data)` - Mark as successfully completed
- `complete_failure($error_message, $error_data)` - Mark as failed
- `with_session($context, $voice)` - Attach session tracker (for AI call counting)

### 4. AIPS_History_Repository

**Purpose**: Database abstraction layer for history operations.

**Key Methods**:
- `create($data)` - Creates new history record
- `add_log_entry($history_id, $log_type, $details, $type_id)` - Adds log entry
- `update($history_id, $data)` - Updates history record
- `get_by_id($history_id)` - Retrieves history record
- `get_logs_by_history_id($history_id)` - Retrieves all logs for a history

---

## History Types

### Type Categories

| Type | ID | Purpose | Shown in Activity Feed | Example Use Case |
|------|-----|---------|----------------------|------------------|
| **LOG** | 1 | General logging | No | Debug messages, info statements |
| **ERROR** | 2 | Error conditions | Yes | API failures, validation errors |
| **WARNING** | 3 | Warning conditions | No | Deprecated feature usage, non-critical issues |
| **INFO** | 4 | Informational | No | Status updates, progress indicators |
| **AI_REQUEST** | 5 | AI prompts | No | Before calling AI service with prompt |
| **AI_RESPONSE** | 6 | AI results | No | After receiving AI response |
| **DEBUG** | 7 | Debug info | No | Development/troubleshooting data |
| **ACTIVITY** | 8 | High-level events | Yes | Post published, schedule completed |
| **SESSION_METADATA** | 9 | Session data | No | Session configuration, context |

### Type Mapping

The `record()` method automatically maps string types to constants:

```php
// String mapping (case-insensitive)
'activity' → AIPS_History_Type::ACTIVITY
'ai_request' → AIPS_History_Type::AI_REQUEST
'ai_response' → AIPS_History_Type::AI_RESPONSE
'error' → AIPS_History_Type::ERROR
'warning' → AIPS_History_Type::WARNING
'info' → AIPS_History_Type::INFO
'debug' → AIPS_History_Type::DEBUG
'log' → AIPS_History_Type::LOG
```

### Activity Feed Filtering

Only types marked as "activity types" appear in the Activity feed:
- **ACTIVITY** (8) - Always shown
- **ERROR** (2) - Always shown

Other types are stored but filtered out from the Activity page view.

---

## History Containers

### Container Types in Use

| Container Type | Created By | Purpose | Key Metadata Fields |
|----------------|-----------|---------|---------------------|
| **post_generation** | AIPS_Generator | Tracks single post generation | template_id, voice_id, scheduled |
| **topic_post_generation** | AIPS_Author_Post_Generator | Tracks post from author topic | topic_id, author_id |
| **schedule_execution** | AIPS_Scheduler | Tracks schedule run | schedule_id, template_id |
| **topic_generation** | AIPS_Author_Topics_Controller | Tracks topic generation | author_id, count |
| **topic_scheduling** | AIPS_Author_Topics_Scheduler | Tracks topic scheduling | author_id, topic_count |
| **post_review** | AIPS_Post_Review | Tracks post review/publish | post_id, reviewer_id |
| **notification** | AIPS_Post_Review_Notifications | Tracks notification sending | post_id, user_id |

### Container Lifecycle States

```
┌─────────────┐
│   Created   │ (uuid assigned, persisted with status='processing')
└──────┬──────┘
       │
       ▼
┌─────────────┐
│ Processing  │ (record() calls add log entries)
└──────┬──────┘
       │
       ├──── complete_success() ────┐
       │                              ▼
       │                      ┌───────────────┐
       │                      │   Completed   │ (status='completed')
       │                      └───────────────┘
       │
       └──── complete_failure() ───┐
                                    ▼
                            ┌───────────────┐
                            │    Failed     │ (status='failed', error logged)
                            └───────────────┘
```

---

## Usage Patterns

### Pattern 1: Simple Activity Logging

Used for standalone events without AI interaction.

```php
// Create container for single event
$history = $this->history_service->create('schedule_execution', [
    'schedule_id' => $schedule_id,
    'template_id' => $template_id
]);

// Log the activity
$history->record(
    'activity',                                    // Type
    'Schedule executed successfully',               // Message
    ['schedule_id' => $schedule_id],               // Input
    ['posts_created' => 3],                        // Output
    ['template_name' => 'Daily News']              // Context
);

// Complete immediately
$history->complete_success(['posts_created' => 3]);
```

### Pattern 2: Post Generation with AI Tracking

Used in AIPS_Generator for complete post generation tracking.

```php
// Create container with session tracking
$this->current_history = $this->history_service->create('post_generation', [
    'template_id' => $template->id,
    'voice_id' => $voice->id
])->with_session($context, $voice);

// Log AI request before calling AI
$this->current_history->record(
    'ai_request',
    'Requesting AI generation for title',
    ['prompt' => $title_prompt, 'options' => $options],
    null,
    ['component' => 'title']  // Component tracking for UI grouping
);

// Get AI response
$title = $this->ai_service->generate_text($title_prompt, $options);

// Log AI response
$this->current_history->record(
    'ai_response',
    'AI generation successful for title',
    null,
    $title,  // Automatically base64-encoded if >500 chars
    ['component' => 'title']
);

// Repeat for content, excerpt, featured image...

// Complete with results
if ($post_id) {
    $this->current_history->complete_success([
        'post_id' => $post_id,
        'generated_title' => $title,
        'generated_content' => $content
    ]);
} else {
    $this->current_history->complete_failure('Failed to create post', [
        'reason' => 'Database error'
    ]);
}
```

### Pattern 3: Error Handling

```php
try {
    // Create history
    $history = $this->history_service->create('topic_post_generation', [
        'topic_id' => $topic->id,
        'author_id' => $author->id
    ]);
    
    // Attempt generation
    $result = $this->generate_post_from_topic($topic, $author);
    
    // Success
    $history->complete_success(['post_id' => $result]);
    
} catch (Exception $e) {
    // Log error with full context
    $history->record(
        'error',
        sprintf('Exception: %s', $e->getMessage()),
        ['topic_id' => $topic->id],
        null,
        [
            'exception_class' => get_class($e),
            'trace' => $e->getTraceAsString(),
            'line' => $e->getLine()
        ]
    );
    
    // Mark as failed
    $history->complete_failure($e->getMessage(), [
        'exception' => get_class($e)
    ]);
}
```

### Pattern 4: Multi-Step Process

```php
// Create container
$history = $this->history_service->create('topic_generation', [
    'author_id' => $author_id,
    'count' => 10
]);

// Step 1
$history->record('activity', 'Starting topic generation', ['count' => 10], null, []);

// Step 2
$history->record('ai_request', 'Requesting topics', ['prompt' => $prompt], null, []);
$topics = $this->ai_service->generate($prompt);
$history->record('ai_response', 'Topics received', null, $topics, []);

// Step 3
$history->record('activity', 'Saving topics to database', null, ['saved' => count($topics)], []);

// Complete
$history->complete_success(['topics_generated' => count($topics)]);
```

---

## Database Schema

### Table: aips_history

Stores the main history container records.

```sql
CREATE TABLE wp_aips_history (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    uuid varchar(36) DEFAULT NULL,              -- Unique identifier (UUID v4)
    post_id bigint(20) DEFAULT NULL,            -- Associated post (if applicable)
    template_id bigint(20) DEFAULT NULL,        -- Associated template (if applicable)
    status varchar(50) NOT NULL DEFAULT 'pending',  -- processing, completed, failed
    prompt text,                                 -- Original prompt (legacy)
    generated_title varchar(500),               -- Generated title (legacy)
    generated_content longtext,                 -- Generated content (legacy)
    generation_log longtext,                    -- Generation log (legacy)
    error_message text,                         -- Error message if failed
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    completed_at datetime DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uuid (uuid),                     -- Ensures UUID uniqueness
    KEY post_id (post_id),
    KEY template_id (template_id),
    KEY status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: aips_history_log

Stores individual log entries associated with history containers.

```sql
CREATE TABLE wp_aips_history_log (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    history_id bigint(20) NOT NULL,             -- Foreign key to aips_history
    log_type varchar(50) NOT NULL,              -- String label (e.g., 'title_request')
    history_type_id int DEFAULT 1,              -- AIPS_History_Type constant
    timestamp datetime DEFAULT CURRENT_TIMESTAMP,
    details longtext,                           -- JSON-encoded details
    PRIMARY KEY (id),
    KEY history_id (history_id),                -- Fast lookups by history
    KEY history_type_id (history_type_id)       -- Fast filtering by type
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Details JSON Structure

The `details` field in `aips_history_log` stores JSON with the following structure:

```json
{
  "message": "Human-readable message",
  "timestamp": "2026-01-27 12:34:56",
  "input": {
    "prompt": "Generate a blog post about...",
    "options": {"temperature": 0.7}
  },
  "output": "Base64-encoded string or object",
  "output_encoded": true,
  "context": {
    "component": "title",
    "template_id": 5,
    "voice_id": 2
  }
}
```

**Field Descriptions**:

| Field | Type | Description |
|-------|------|-------------|
| `message` | string | Human-readable description of the log entry |
| `timestamp` | datetime | When the log entry was created |
| `input` | object/array | Input data (e.g., prompt, parameters) |
| `output` | string/object/array | Output data (AI response, results) |
| `output_encoded` | boolean | True if output is base64-encoded (for large strings >500 chars) |
| `context` | object | Additional context (component, IDs, metadata) |

---

## Data Flow

### Post Generation Flow

This diagram shows how history is tracked during a complete post generation:

```
┌─────────────────────────────────────────────────────────────────────┐
│ 1. AIPS_Generator::generate_post_from_context()                     │
└───────────────────┬─────────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│ 2. Create History Container                                          │
│    $this->current_history = $this->history_service->create(         │
│        'post_generation',                                            │
│        ['template_id' => $template->id, 'voice_id' => $voice->id]   │
│    )->with_session($context, $voice);                                │
│                                                                       │
│    Database: INSERT INTO aips_history                                │
│    Result: uuid='abc-123', history_id=45, status='processing'        │
└───────────────────┬─────────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│ 3. Generate Title                                                    │
│                                                                       │
│ a) record('ai_request', ...)                                         │
│    Database: INSERT INTO aips_history_log                            │
│    Fields: history_id=45, log_type='title_request',                 │
│            history_type_id=5 (AI_REQUEST),                           │
│            details=JSON{message, input:{prompt, options},            │
│                        context:{component:'title'}}                  │
│                                                                       │
│ b) Call AI Service                                                   │
│    $title = $this->ai_service->generate_text($prompt, $options);    │
│                                                                       │
│ c) record('ai_response', ...)                                        │
│    Database: INSERT INTO aips_history_log                            │
│    Fields: history_id=45, log_type='title_response',                │
│            history_type_id=6 (AI_RESPONSE),                          │
│            details=JSON{message, output:$title,                      │
│                        context:{component:'title'}}                  │
└───────────────────┬─────────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│ 4. Generate Content (same pattern as Title)                          │
│    - record('ai_request', ..., context:{component:'content'})        │
│    - Call AI                                                         │
│    - record('ai_response', ..., context:{component:'content'})       │
└───────────────────┬─────────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│ 5. Generate Excerpt (same pattern)                                  │
│    - record('ai_request', ..., context:{component:'excerpt'})        │
│    - Call AI                                                         │
│    - record('ai_response', ..., context:{component:'excerpt'})       │
└───────────────────┬─────────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│ 6. Generate Featured Image (if enabled)                             │
│    - record('ai_request', ..., context:{component:'featured_image'}) │
│    - Call AI/Unsplash                                                │
│    - record('ai_response', ..., context:{component:'featured_image'})│
└───────────────────┬─────────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│ 7. Create WordPress Post                                             │
│    $post_id = wp_insert_post([...]);                                │
└───────────────────┬─────────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│ 8. Complete History                                                  │
│    if ($post_id) {                                                   │
│        $this->current_history->complete_success([                    │
│            'post_id' => $post_id,                                    │
│            'generated_title' => $title,                              │
│            'generated_content' => $content                           │
│        ]);                                                            │
│                                                                       │
│        Database: UPDATE aips_history                                 │
│        SET status='completed', post_id=$post_id,                     │
│            completed_at=NOW()                                        │
│        WHERE id=45                                                   │
│    }                                                                 │
└──────────────────────────────────────────────────────────────────────┘
```

### Result in Database

After a successful post generation, the database contains:

**aips_history** (1 record):
```
id: 45
uuid: 'abc-123-def-456'
post_id: 789
template_id: 5
status: 'completed'
created_at: '2026-01-27 10:00:00'
completed_at: '2026-01-27 10:05:30'
```

**aips_history_log** (multiple records):
```
id: 100, history_id: 45, log_type: 'title_request', history_type_id: 5, details: {...}
id: 101, history_id: 45, log_type: 'title_response', history_type_id: 6, details: {...}
id: 102, history_id: 45, log_type: 'content_request', history_type_id: 5, details: {...}
id: 103, history_id: 45, log_type: 'content_response', history_type_id: 6, details: {...}
id: 104, history_id: 45, log_type: 'excerpt_request', history_type_id: 5, details: {...}
id: 105, history_id: 45, log_type: 'excerpt_response', history_type_id: 6, details: {...}
id: 106, history_id: 45, log_type: 'featured_image_request', history_type_id: 5, details: {...}
id: 107, history_id: 45, log_type: 'featured_image_response', history_type_id: 6, details: {...}
```

---

## Integration Points

### Where History Containers are Created

| File | Method | Container Type | Purpose |
|------|--------|----------------|---------|
| `class-aips-generator.php` | `generate_post_from_context()` | `post_generation` | Main post generation |
| `class-aips-author-post-generator.php` | `generate_post_from_topic()` | `topic_post_generation` | Post from author topic |
| `class-aips-scheduler.php` | `execute_schedule()` | `schedule_execution` | Schedule run tracking |
| `class-aips-author-topics-controller.php` | `ajax_generate_topics()` | `topic_generation` | Topic generation |
| `class-aips-author-topics-scheduler.php` | `process_due_topic_schedulers()` | `topic_scheduling` | Topic scheduling |
| `class-aips-post-review.php` | `ajax_publish_post()` | `post_review` | Post review/publish |
| `class-aips-post-review-notifications.php` | `send_notifications()` | `notification` | Notification sending |

### UI Integration

#### Generated Posts Admin Page

**Location**: `templates/admin/generated-posts.php`

**Features**:
- Lists all posts with completed history
- Shows: Title, Date Scheduled, Date Published, Date Generated
- Search and pagination
- "View Session" button opens modal

#### View Session Modal

**Tabs**:

1. **Logs Tab**:
   - Shows ALL log entries chronologically
   - Color-coded by type (errors in red, warnings in yellow)
   - Expandable JSON viewer for details
   - Timestamp for each entry

2. **AI Tab**:
   - Groups AI requests and responses by component
   - Shows: Title, Content, Excerpt, Featured Image
   - Click component to expand request/response
   - Syntax-highlighted JSON display
   - Decodes base64-encoded responses automatically

**JavaScript**:
- Constants mirror PHP `AIPS_History_Type` values
- AJAX loads session data by history_id
- Component extraction from `context.component` field

---

## Expected History Records

### Post Generation (Standard Template)

**History Container**:
```json
{
  "uuid": "abc-123-def-456",
  "type": "post_generation",
  "status": "completed",
  "metadata": {
    "template_id": 5,
    "voice_id": 2,
    "scheduled": false
  }
}
```

**Log Entries** (8-10 entries typical):

1. **Title Request** (AI_REQUEST, component: title)
2. **Title Response** (AI_RESPONSE, component: title)
3. **Content Request** (AI_REQUEST, component: content)
4. **Content Response** (AI_RESPONSE, component: content)
5. **Excerpt Request** (AI_REQUEST, component: excerpt)
6. **Excerpt Response** (AI_RESPONSE, component: excerpt)
7. **Featured Image Request** (AI_REQUEST, component: featured_image) [if enabled]
8. **Featured Image Response** (AI_RESPONSE, component: featured_image) [if enabled]
9. **Post Created** (ACTIVITY) [optional]

### Schedule Execution

**History Container**:
```json
{
  "uuid": "schedule-uuid-789",
  "type": "schedule_execution",
  "status": "completed",
  "metadata": {
    "schedule_id": 12,
    "template_id": 5
  }
}
```

**Log Entries** (2-4 entries):

1. **Schedule Started** (ACTIVITY)
   - Input: `{schedule_id, template_id}`
   - Output: null
   - Context: `{schedule_name, frequency}`

2. **Posts Generated** (ACTIVITY)
   - Input: null
   - Output: `{posts_created: 3, post_ids: [10, 11, 12]}`
   - Context: `{template_name}`

3. **Schedule Completed** (ACTIVITY)
   - Input: null
   - Output: `{success: true, posts_count: 3}`
   - Context: `{next_run: '2026-01-28 10:00:00'}`

### Topic Post Generation

**History Container**:
```json
{
  "uuid": "topic-post-uuid",
  "type": "topic_post_generation",
  "status": "completed",
  "metadata": {
    "topic_id": 45,
    "author_id": 3
  }
}
```

**Log Entries** (10-12 entries):

1. **Generation Started** (ACTIVITY)
2. **Voice Retrieved** (INFO)
3. **Title AI Request** (AI_REQUEST, component: title)
4. **Title AI Response** (AI_RESPONSE, component: title)
5. **Content AI Request** (AI_REQUEST, component: content)
6. **Content AI Response** (AI_RESPONSE, component: content)
7. **Excerpt AI Request** (AI_REQUEST, component: excerpt)
8. **Excerpt AI Response** (AI_RESPONSE, component: excerpt)
9. **Post Created** (ACTIVITY)
10. **Topic Marked Used** (INFO)

### Error Scenario

**History Container**:
```json
{
  "uuid": "failed-gen-uuid",
  "type": "post_generation",
  "status": "failed",
  "error_message": "AI service timeout after 3 retries",
  "metadata": {
    "template_id": 5
  }
}
```

**Log Entries**:

1. **Title Request** (AI_REQUEST, component: title)
2. **Title Response** (AI_RESPONSE, component: title)
3. **Content Request** (AI_REQUEST, component: content)
4. **API Timeout** (ERROR)
   - Input: `{prompt, options, retries: 1}`
   - Output: null
   - Context: `{error_code: 'TIMEOUT', duration: 30}`
5. **Content Request Retry** (AI_REQUEST, component: content)
6. **API Timeout** (ERROR)
   - Context: `{retries: 2}`
7. **Generation Failed** (ERROR)
   - Input: null
   - Output: null
   - Context: `{reason: 'Max retries exceeded', component: 'content'}`

---

## Summary

The History Feature provides:

1. **Unified Tracking**: All events in one system
2. **Thread Safety**: UUID prevents race conditions
3. **Complete Visibility**: Drill-down to every AI call
4. **Flexible Classification**: Type-based filtering
5. **Rich Context**: Component tracking for UI grouping
6. **Clean API**: Single `record()` method
7. **Encapsulation**: Container manages lifecycle

This system enables complete transparency and debugging capability for all plugin operations, with special focus on AI-powered post generation.
