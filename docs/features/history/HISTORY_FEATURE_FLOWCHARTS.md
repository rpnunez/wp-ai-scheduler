# History Feature - Visual Flowcharts

## Table of Contents

1. [History Container Creation Flow](#history-container-creation-flow)
2. [Record() Method Flow](#record-method-flow)
3. [Complete Post Generation Flow](#complete-post-generation-flow)
4. [Activity Feed Retrieval Flow](#activity-feed-retrieval-flow)
5. [View Session Modal Flow](#view-session-modal-flow)
6. [Error Handling Flow](#error-handling-flow)

---

## History Container Creation Flow

```
┌─────────────────────────────────────────────────────────────┐
│ Application Code                                             │
│ (Generator, Scheduler, etc.)                                 │
└───────────────────┬──────────────────────────────────────────┘
                    │
                    │ $history_service->create('post_generation', $metadata)
                    ▼
┌─────────────────────────────────────────────────────────────┐
│ AIPS_History_Service::create()                               │
│                                                               │
│ 1. Instantiate AIPS_History_Container                        │
│    pass: $repository, $type, $metadata                       │
└───────────────────┬──────────────────────────────────────────┘
                    │
                    │ new AIPS_History_Container(...)
                    ▼
┌─────────────────────────────────────────────────────────────┐
│ AIPS_History_Container::__construct()                        │
│                                                               │
│ 1. Generate UUID                                             │
│    $this->uuid = wp_generate_uuid4()                         │
│    Result: 'abc-123-def-456-789'                             │
│                                                               │
│ 2. Set properties                                            │
│    $this->type = 'post_generation'                           │
│    $this->metadata = ['template_id' => 5, ...]               │
│    $this->is_persisted = false                               │
│                                                               │
│ 3. Call persist()                                            │
└───────────────────┬──────────────────────────────────────────┘
                    │
                    │ $this->persist()
                    ▼
┌─────────────────────────────────────────────────────────────┐
│ AIPS_History_Container::persist()                            │
│                                                               │
│ 1. Merge data                                                │
│    $data = array_merge([                                     │
│        'uuid' => $this->uuid,                                │
│        'status' => 'processing'                              │
│    ], $this->metadata)                                       │
│                                                               │
│ 2. Create in database                                        │
│    $this->history_id = $repository->create($data)            │
└───────────────────┬──────────────────────────────────────────┘
                    │
                    │ $repository->create($data)
                    ▼
┌─────────────────────────────────────────────────────────────┐
│ AIPS_History_Repository::create()                            │
│                                                               │
│ Execute SQL:                                                 │
│ INSERT INTO wp_aips_history (                                │
│     uuid,                                                    │
│     status,                                                  │
│     template_id,                                             │
│     created_at                                               │
│ ) VALUES (                                                   │
│     'abc-123-def-456-789',                                   │
│     'processing',                                            │
│     5,                                                       │
│     '2026-01-27 10:00:00'                                    │
│ )                                                            │
│                                                               │
│ Return: $wpdb->insert_id  (e.g., 45)                         │
└───────────────────┬──────────────────────────────────────────┘
                    │
                    │ Return history_id
                    ▼
┌─────────────────────────────────────────────────────────────┐
│ AIPS_History_Container                                       │
│                                                               │
│ $this->history_id = 45                                       │
│ $this->is_persisted = true                                   │
│                                                               │
│ Container is now ready for logging                           │
└───────────────────┬──────────────────────────────────────────┘
                    │
                    │ Return container object
                    ▼
┌─────────────────────────────────────────────────────────────┐
│ Application Code                                             │
│                                                               │
│ $history now holds:                                          │
│   - uuid: 'abc-123-def-456-789'                              │
│   - history_id: 45                                           │
│   - type: 'post_generation'                                  │
│   - Ready to call record()                                   │
└──────────────────────────────────────────────────────────────┘
```

---

## Record() Method Flow

```
┌─────────────────────────────────────────────────────────────┐
│ Application Code                                             │
│                                                               │
│ $history->record(                                            │
│     'ai_request',              // log_type                   │
│     'Generating title',        // message                    │
│     ['prompt' => $prompt],     // input                      │
│     null,                      // output                     │
│     ['component' => 'title']   // context                    │
│ )                                                            │
└───────────────────┬──────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────────┐
│ AIPS_History_Container::record()                             │
│                                                               │
│ 1. Check if persisted                                        │
│    if (!$this->is_persisted) return false;                   │
│                                                               │
│ 2. Map log_type to history_type_id                           │
│    $history_type_id = map_log_type_to_history_type(          │
│        'ai_request'                                          │
│    )                                                         │
│    Result: 5 (AIPS_History_Type::AI_REQUEST)                 │
└───────────────────┬──────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────────┐
│ AIPS_History_Container::record() (continued)                 │
│                                                               │
│ 3. Build details array                                       │
│    $details = [                                              │
│        'message' => 'Generating title',                      │
│        'timestamp' => '2026-01-27 10:00:15',                 │
│    ]                                                         │
│                                                               │
│ 4. Add input if provided                                     │
│    if ($input !== null) {                                    │
│        $details['input'] = ['prompt' => $prompt]             │
│    }                                                         │
│                                                               │
│ 5. Add output if provided                                    │
│    if ($output !== null) {                                   │
│        if (strlen($output) > 500) {                          │
│            $details['output'] = base64_encode($output)       │
│            $details['output_encoded'] = true                 │
│        } else {                                              │
│            $details['output'] = $output                      │
│        }                                                     │
│    }                                                         │
│                                                               │
│ 6. Merge context                                             │
│    if (!empty($context)) {                                   │
│        $details['context'] = ['component' => 'title']        │
│    }                                                         │
└───────────────────┬──────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────────┐
│ AIPS_History_Container::record() (continued)                 │
│                                                               │
│ 7. Track in session (if applicable)                          │
│    if ($this->session && $log_type === 'ai_request') {      │
│        $this->session->log_ai_call()                         │
│    }                                                         │
│                                                               │
│ 8. Add log entry to database                                 │
│    return $repository->add_log_entry(                        │
│        $this->history_id,     // 45                          │
│        'ai_request',           // log_type                   │
│        $details,               // JSON data                  │
│        5                       // history_type_id            │
│    )                                                         │
└───────────────────┬──────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────────┐
│ AIPS_History_Repository::add_log_entry()                     │
│                                                               │
│ Execute SQL:                                                 │
│ INSERT INTO wp_aips_history_log (                            │
│     history_id,                                              │
│     log_type,                                                │
│     history_type_id,                                         │
│     details,                                                 │
│     timestamp                                                │
│ ) VALUES (                                                   │
│     45,                                                      │
│     'ai_request',                                            │
│     5,                                                       │
│     '{"message":"Generating title",...}',                    │
│     '2026-01-27 10:00:15'                                    │
│ )                                                            │
│                                                               │
│ Return: $wpdb->insert_id  (e.g., 100)                        │
└───────────────────┬──────────────────────────────────────────┘
                    │
                    │ Return log entry id
                    ▼
┌─────────────────────────────────────────────────────────────┐
│ Application Code                                             │
│                                                               │
│ Log entry created with id: 100                               │
│ Can now call record() again for next event                   │
└──────────────────────────────────────────────────────────────┘
```

---

## Complete Post Generation Flow

```
START: AIPS_Generator::generate_post_from_context()
│
├─► 1. CREATE HISTORY CONTAINER
│   ├─► $history_service->create('post_generation', metadata)
│   ├─► UUID: 'abc-123', history_id: 45, status: 'processing'
│   └─► $history->with_session($context, $voice)
│
├─► 2. GENERATE TITLE
│   ├─► record('ai_request', ..., context: {component: 'title'})
│   │   └─► DB: history_log id=100, type_id=5 (AI_REQUEST)
│   ├─► $ai_service->generate_text($title_prompt)
│   └─► record('ai_response', ..., output: $title, context: {component: 'title'})
│       └─► DB: history_log id=101, type_id=6 (AI_RESPONSE)
│
├─► 3. GENERATE CONTENT
│   ├─► record('ai_request', ..., context: {component: 'content'})
│   │   └─► DB: history_log id=102, type_id=5 (AI_REQUEST)
│   ├─► $ai_service->generate_text($content_prompt)
│   └─► record('ai_response', ..., output: $content, context: {component: 'content'})
│       └─► DB: history_log id=103, type_id=6 (AI_RESPONSE)
│       └─► Output base64-encoded (length > 500 chars)
│
├─► 4. GENERATE EXCERPT
│   ├─► record('ai_request', ..., context: {component: 'excerpt'})
│   │   └─► DB: history_log id=104, type_id=5 (AI_REQUEST)
│   ├─► $ai_service->generate_text($excerpt_prompt)
│   └─► record('ai_response', ..., output: $excerpt, context: {component: 'excerpt'})
│       └─► DB: history_log id=105, type_id=6 (AI_RESPONSE)
│
├─► 5. GENERATE FEATURED IMAGE (if enabled)
│   ├─► record('ai_request', ..., context: {component: 'featured_image'})
│   │   └─► DB: history_log id=106, type_id=5 (AI_REQUEST)
│   ├─► $image_service->generate_featured_image()
│   └─► record('ai_response', ..., output: $image_url, context: {component: 'featured_image'})
│       └─► DB: history_log id=107, type_id=6 (AI_RESPONSE)
│
├─► 6. CREATE WORDPRESS POST
│   ├─► $post_id = wp_insert_post([...])
│   └─► Set featured image, categories, tags
│
└─► 7. COMPLETE HISTORY
    ├─► IF SUCCESS:
    │   └─► $history->complete_success([
    │       │   'post_id' => $post_id,
    │       │   'generated_title' => $title,
    │       │   'generated_content' => $content
    │       └─► ])
    │       └─► DB: UPDATE history SET status='completed', post_id=$post_id,
    │                                   completed_at='2026-01-27 10:05:30'
    │
    └─► IF ERROR:
        └─► $history->complete_failure($error_message, $error_data)
            ├─► record('error', $error_message, ...)
            │   └─► DB: history_log id=108, type_id=2 (ERROR)
            └─► DB: UPDATE history SET status='failed',
                                       error_message=$error_message,
                                       completed_at='2026-01-27 10:02:15'

END: Post generation complete with full history tracking
```

---

## Activity Feed Retrieval Flow

```
┌─────────────────────────────────────────────────────────────┐
│ WordPress Admin: Activity Page                               │
│ templates/admin/activity.php                                 │
└───────────────────┬──────────────────────────────────────────┘
                    │
                    │ Request activity feed
                    ▼
┌─────────────────────────────────────────────────────────────┐
│ AIPS_Settings::display_activity_page()                       │
│                                                               │
│ 1. Get filters from request                                  │
│    $filters = [                                              │
│        'event_type' => $_GET['event_type'],                  │
│        'event_status' => $_GET['event_status'],              │
│        'search' => $_GET['search']                           │
│    ]                                                         │
│                                                               │
│ 2. Call history service                                      │
│    $activities = $history_service->get_activity_feed(        │
│        $limit, $offset, $filters                             │
│    )                                                         │
└───────────────────┬──────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────────┐
│ AIPS_History_Service::get_activity_feed()                    │
│                                                               │
│ 1. Build WHERE clause                                        │
│    $where_clauses = ["history_type_id = %d"]                 │
│    $where_args = [AIPS_History_Type::ACTIVITY]  // 8         │
│                                                               │
│ 2. Add filters                                               │
│    if ($filters['event_type']) {                             │
│        $where_clauses[] = "details LIKE %s"                  │
│        $where_args[] = '%"event_type":"schedule_executed"%'  │
│    }                                                         │
│                                                               │
│ 3. Build SQL query                                           │
│    SELECT hl.*, h.post_id, h.template_id                     │
│    FROM wp_aips_history_log hl                               │
│    LEFT JOIN wp_aips_history h ON hl.history_id = h.id      │
│    WHERE history_type_id = 8                                 │
│      AND details LIKE '%schedule_executed%'                  │
│    ORDER BY hl.timestamp DESC                                │
│    LIMIT 50 OFFSET 0                                         │
│                                                               │
│ 4. Execute query                                             │
│    $results = $wpdb->get_results($wpdb->prepare($sql, ...))  │
└───────────────────┬──────────────────────────────────────────┘
                    │
                    │ Return activity entries
                    ▼
┌─────────────────────────────────────────────────────────────┐
│ AIPS_Settings::display_activity_page()                       │
│                                                               │
│ 3. Render activity entries                                   │
│    foreach ($activities as $activity) {                      │
│        $details = json_decode($activity->details)            │
│        echo $details->message                                │
│        echo $details->context->schedule_name                 │
│    }                                                         │
└──────────────────────────────────────────────────────────────┘

RESULT: Activity page shows only high-level ACTIVITY type events
        (filters out AI_REQUEST, AI_RESPONSE, LOG, etc.)
```

---

## View Session Modal Flow

```
┌─────────────────────────────────────────────────────────────┐
│ Generated Posts Page                                         │
│ User clicks "View Session" button                            │
│ data-history-id="45"                                         │
└───────────────────┬──────────────────────────────────────────┘
                    │
                    │ JavaScript: openSessionModal(45)
                    ▼
┌─────────────────────────────────────────────────────────────┐
│ JavaScript Function: openSessionModal()                      │
│                                                               │
│ 1. Show modal                                                │
│    $('#session-modal').show()                                │
│                                                               │
│ 2. Make AJAX request                                         │
│    $.post(ajaxurl, {                                         │
│        action: 'aips_get_session_data',                      │
│        history_id: 45,                                       │
│        nonce: nonce                                          │
│    })                                                        │
└───────────────────┬──────────────────────────────────────────┘
                    │
                    │ AJAX Request
                    ▼
┌─────────────────────────────────────────────────────────────┐
│ AIPS_Generated_Posts_Controller::ajax_get_session_data()     │
│                                                               │
│ 1. Verify nonce                                              │
│ 2. Check permissions                                         │
│ 3. Get history record                                        │
│    $history = $repository->get_by_id(45)                     │
│                                                               │
│ 4. Get all log entries                                       │
│    $logs = $repository->get_logs_by_history_id(45)           │
│                                                               │
│ 5. Process logs                                              │
│    foreach ($logs as $log) {                                 │
│        $details = json_decode($log->details)                 │
│        $component = $details->context->component ?? null     │
│    }                                                         │
│                                                               │
│ 6. Group AI calls by component                               │
│    $ai_interactions = [                                      │
│        'title' => [                                          │
│            'request' => [...],                               │
│            'response' => [...]                               │
│        ],                                                    │
│        'content' => [...],                                   │
│        'excerpt' => [...],                                   │
│        'featured_image' => [...]                             │
│    ]                                                         │
│                                                               │
│ 7. Return JSON                                               │
│    wp_send_json_success([                                    │
│        'history' => $history,                                │
│        'logs' => $logs,                                      │
│        'ai_interactions' => $ai_interactions                 │
│    ])                                                        │
└───────────────────┬──────────────────────────────────────────┘
                    │
                    │ JSON Response
                    ▼
┌─────────────────────────────────────────────────────────────┐
│ JavaScript: AJAX Success Handler                             │
│                                                               │
│ 1. Parse response data                                       │
│                                                               │
│ 2. Render LOGS TAB                                           │
│    for each log in response.logs:                            │
│        ├─► Check log.type_id                                 │
│        ├─► Set CSS class (error=red, warning=yellow)         │
│        ├─► Decode base64 if output_encoded=true              │
│        └─► Render HTML with JSON viewer                      │
│                                                               │
│ 3. Render AI TAB                                             │
│    for each component in ai_interactions:                    │
│        ├─► Create accordion item (Title, Content, etc.)      │
│        ├─► Add request details (prompt, options)             │
│        ├─► Add response details (output)                     │
│        └─► Syntax highlight JSON                             │
│                                                               │
│ 4. Attach click handlers                                     │
│    $('.ai-component').click(function() {                     │
│        toggleExpand(this)                                    │
│    })                                                        │
└──────────────────────────────────────────────────────────────┘

RESULT: Modal displays two tabs:
        • Logs: All entries with timestamps, color-coded
        • AI: Grouped by component with request/response pairs
```

---

## Error Handling Flow

```
┌─────────────────────────────────────────────────────────────┐
│ AIPS_Generator::generate_post_from_context()                 │
│                                                               │
│ 1. Create history container                                  │
│    $history = $history_service->create(...)                  │
│    UUID: 'error-uuid-123', history_id: 50                    │
└───────────────────┬──────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. Start generation - Title success                          │
│    record('ai_request', ..., component: 'title')             │
│    └─► DB: log id=200, type_id=5                             │
│    $title = $ai_service->generate_text(...)                  │
│    record('ai_response', ..., component: 'title')            │
│    └─► DB: log id=201, type_id=6                             │
└───────────────────┬──────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. Generate content - FAILURE                                │
│    record('ai_request', ..., component: 'content')           │
│    └─► DB: log id=202, type_id=5                             │
│                                                               │
│    $content = $ai_service->generate_text(...)                │
│    └─► Returns: WP_Error('api_timeout', 'Timeout after 30s') │
│                                                               │
│    if (is_wp_error($content)) {                              │
│        ├─► Extract error message                             │
│        │   $error = $content->get_error_message()            │
│        │                                                      │
│        ├─► Record error                                      │
│        │   $history->record(                                 │
│        │       'error',                                      │
│        │       'API timeout while generating content',       │
│        │       null,                                         │
│        │       null,                                         │
│        │       [                                             │
│        │           'component' => 'content',                 │
│        │           'error_code' => 'api_timeout',            │
│        │           'duration' => 30                          │
│        │       ]                                             │
│        │   )                                                 │
│        │   └─► DB: log id=203, type_id=2 (ERROR)             │
│        │                                                      │
│        └─► Complete with failure                             │
│            $history->complete_failure(                       │
│                'Failed to generate content: Timeout after 30s',│
│                ['component' => 'content']                    │
│            )                                                 │
│    }                                                         │
└───────────────────┬──────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────────┐
│ AIPS_History_Container::complete_failure()                   │
│                                                               │
│ 1. Complete session (if exists)                              │
│    $this->session->complete([                                │
│        'success' => false,                                   │
│        'error' => $error_message                             │
│    ])                                                        │
│                                                               │
│ 2. Record error in history log                               │
│    $this->record('error', $error_message, null, null,        │
│                  $error_data)                                │
│    └─► DB: log id=204, type_id=2 (ERROR)                     │
│                                                               │
│ 3. Update history status                                     │
│    $repository->update(50, [                                 │
│        'status' => 'failed',                                 │
│        'error_message' => $error_message,                    │
│        'completed_at' => '2026-01-27 10:02:15'               │
│    ])                                                        │
│    └─► DB: UPDATE history SET status='failed', ...           │
└───────────────────┬──────────────────────────────────────────┘
                    │
                    │ Return false
                    ▼
┌─────────────────────────────────────────────────────────────┐
│ AIPS_Generator::generate_post_from_context()                 │
│                                                               │
│ 4. Return error to caller                                    │
│    return new WP_Error(...)                                  │
└──────────────────────────────────────────────────────────────┘

RESULT IN DATABASE:
┌──────────────────────────────────────────────────────────────┐
│ wp_aips_history (id: 50)                                     │
│   uuid: 'error-uuid-123'                                     │
│   status: 'failed'                                           │
│   error_message: 'Failed to generate content: Timeout...'    │
│   completed_at: '2026-01-27 10:02:15'                        │
└──────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────┐
│ wp_aips_history_log (for history_id: 50)                     │
│   200: ai_request (title) - success                          │
│   201: ai_response (title) - success                         │
│   202: ai_request (content) - attempted                      │
│   203: error - API timeout (recorded by record())            │
│   204: error - failure message (recorded by complete_failure│
└──────────────────────────────────────────────────────────────┘

USER VIEW:
• Generated Posts page: Shows failed generation
• View Session modal:
  - Logs tab: Shows all 5 log entries, errors in red
  - AI tab: Shows Title (success), Content (failed with error)
```

---

## Summary

These flowcharts illustrate:

1. **Container Creation**: UUID generation → database persistence → ready for logging
2. **Record Method**: Type mapping → details building → database insertion
3. **Post Generation**: Complete lifecycle from creation to completion
4. **Activity Feed**: Filtered query retrieving only ACTIVITY type entries
5. **View Session Modal**: AJAX data retrieval → tab rendering → interactive display
6. **Error Handling**: Error detection → logging → graceful failure completion

The system ensures:
- **Thread safety** through UUID-based tracking
- **Complete visibility** with every step logged
- **Flexible UI** with type-based filtering
- **Graceful degradation** with comprehensive error tracking
