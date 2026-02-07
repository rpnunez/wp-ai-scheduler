# Architecture Diagram: Enhanced Feedback & Topic Expansion

## System Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                     WordPress Admin UI                              │
│  (Author Topics Management Interface)                               │
└────────────────┬───────────────────────────────┬────────────────────┘
                 │                               │
                 v                               v
    ┌────────────────────────┐     ┌─────────────────────────┐
    │ Approval/Rejection     │     │ Topic Expansion UI      │
    │ with Reason Category   │     │ (Suggestions, Similar)  │
    └────────┬───────────────┘     └────────┬────────────────┘
             │                               │
             v                               v
┌─────────────────────────────────────────────────────────────────────┐
│              AIPS_Author_Topics_Controller                          │
│  ┌──────────────────────┐  ┌──────────────────────────────────┐   │
│  │ ajax_approve_topic   │  │ ajax_get_similar_topics          │   │
│  │ ajax_reject_topic    │  │ ajax_suggest_related_topics      │   │
│  └──────────┬───────────┘  │ ajax_compute_topic_embeddings    │   │
│             │              └──────────┬───────────────────────┘   │
└─────────────┼─────────────────────────┼───────────────────────────┘
              │                         │
              v                         v
┌──────────────────────────┐  ┌─────────────────────────────────┐
│ AIPS_Feedback_Repository │  │ AIPS_Topic_Expansion_Service    │
│  - record_approval()     │  │  - find_similar_topics()        │
│  - record_rejection()    │  │  - suggest_related_topics()     │
│  - get_by_reason_cat()   │  │  - compute_topic_embedding()    │
│  - get_statistics()      │  │  - get_expanded_context()       │
└────────┬─────────────────┘  └──────────┬──────────────────────┘
         │                               │
         v                               v
┌─────────────────────────┐    ┌────────────────────────────────┐
│ AIPS_Topic_Penalty_     │    │ AIPS_Embeddings_Service        │
│ Service                 │    │  - generate_embedding()        │
│  - apply_penalty()      │    │  - calculate_similarity()      │
│  - apply_reward()       │    │  - find_nearest_neighbors()    │
│  - flag_author()        │    │  - batch_generate()            │
└────────┬────────────────┘    └──────────┬─────────────────────┘
         │                                │
         v                                v
┌─────────────────────────────────────────────────────────────────┐
│                Database Layer (Repositories)                    │
│  ┌─────────────────────┐  ┌────────────────────────────────┐  │
│  │ aips_topic_feedback │  │ aips_author_topics             │  │
│  │  - reason_category  │  │  - metadata (embeddings)       │  │
│  │  - source           │  │  - score (penalties/rewards)   │  │
│  └─────────────────────┘  └────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                              │
                              v
                 ┌────────────────────────┐
                 │ Meow AI Engine         │
                 │  - Embeddings API      │
                 │  - Text Generation     │
                 └────────────────────────┘
```

## Data Flow: Approve Topic with Reason

```
1. User Action (UI)
   ↓
2. AJAX Request
   {
     topic_id: 123,
     reason: "Great topic",
     reason_category: "other",
     source: "UI"
   }
   ↓
3. Controller (ajax_approve_topic)
   ↓
4. Update Status → topics_repository->update_status()
   ↓
5. Record Feedback → feedback_repository->record_approval()
   Database: INSERT INTO aips_topic_feedback
   ↓
6. Apply Reward → penalty_service->apply_reward()
   Database: UPDATE aips_author_topics SET score = score + 10
   ↓
7. Log Action → logs_repository->log_approval()
   ↓
8. Response
   {
     success: true,
     message: "Topic approved successfully"
   }
```

## Data Flow: Find Similar Topics

```
1. User Action (UI) - View topic detail
   ↓
2. AJAX Request
   {
     topic_id: 123,
     author_id: 1,
     limit: 5
   }
   ↓
3. Controller (ajax_get_similar_topics)
   ↓
4. Expansion Service → find_similar_topics()
   ↓
5. Get Target Embedding
   - Check topic metadata
   - If missing, compute via embeddings_service
   ↓
6. Get Candidate Topics → topics_repository->get_by_author()
   ↓
7. Get/Compute Candidate Embeddings
   For each topic:
     - Check metadata
     - If missing, compute via embeddings_service
   ↓
8. Calculate Similarities
   - embeddings_service->calculate_similarity()
   - Cosine similarity between vectors
   ↓
9. Find Nearest Neighbors
   - embeddings_service->find_nearest_neighbors()
   - Sort by similarity score
   - Return top K
   ↓
10. Response
    {
      success: true,
      similar_topics: [
        {id: 456, title: "...", similarity: 0.92},
        {id: 789, title: "...", similarity: 0.88},
        ...
      ]
    }
```

## Data Flow: Reject with Policy Violation

```
1. User Action (UI)
   ↓
2. AJAX Request
   {
     topic_id: 123,
     reason: "Contains prohibited content",
     reason_category: "policy",
     source: "UI"
   }
   ↓
3. Controller (ajax_reject_topic)
   ↓
4. Update Status → topics_repository->update_status()
   ↓
5. Record Feedback → feedback_repository->record_rejection()
   Database: INSERT INTO aips_topic_feedback
   ↓
6. Apply Penalty → penalty_service->apply_penalty()
   - Deduct 50 points from topic score
   - Flag author for policy review
   Database: 
     UPDATE aips_author_topics SET score = score - 50
     UPDATE aips_authors SET details = JSON with policy flag
   ↓
7. Log Action → logs_repository->log_rejection()
   ↓
8. Check Policy Flags
   - If author has 3+ flags → log warning
   ↓
9. Response
   {
     success: true,
     message: "Topic rejected successfully"
   }
```

## Component Dependencies

```
AIPS_Author_Topics_Controller
  ├── AIPS_Author_Topics_Repository
  ├── AIPS_Feedback_Repository
  ├── AIPS_Author_Topic_Logs_Repository
  ├── AIPS_Topic_Penalty_Service
  │   ├── AIPS_Author_Topics_Repository
  │   ├── AIPS_Authors_Repository
  │   └── AIPS_Logger
  └── AIPS_Topic_Expansion_Service
      ├── AIPS_Embeddings_Service
      │   ├── AIPS_AI_Service
      │   └── AIPS_Logger
      ├── AIPS_Author_Topics_Repository
      └── AIPS_Logger
```

## Key Design Patterns

1. **Repository Pattern**: All database access through repository classes
2. **Service Layer**: Business logic in service classes
3. **Dependency Injection**: Services accept dependencies in constructor
4. **Event Hooks**: WordPress actions for extensibility
5. **Error Handling**: WP_Error for error propagation
6. **Caching**: In-memory cache for embeddings
7. **Configuration**: Configurable penalty weights

## Database Schema

```sql
-- Enhanced feedback table
aips_topic_feedback
├── id (PK)
├── author_topic_id (FK)
├── action (approved/rejected)
├── user_id (FK)
├── reason (TEXT)
├── reason_category (duplicate/tone/irrelevant/policy/other) [NEW]
├── source (UI/automation) [NEW]
├── notes (TEXT)
└── created_at

-- Topics table with embeddings
aips_author_topics
├── id (PK)
├── author_id (FK)
├── topic_title
├── topic_prompt
├── status
├── score [UPDATED by penalties/rewards]
├── metadata (JSON) [STORES embeddings]
├── generated_at
├── reviewed_at
└── reviewed_by
```

## Extension Points

1. **Custom Penalty Weights**
   ```php
   $penalty_service->set_penalty_weights([
     'custom_reason' => -30
   ]);
   ```

2. **Custom Embedding Options**
   ```php
   $embeddings_service->generate_embedding($text, [
     'embeddings_env_id' => 'custom-env'
   ]);
   ```

3. **WordPress Hooks**
   ```php
   add_action('aips_topic_approved', function($topic_id) {
     // Custom action after approval
   });
   ```

4. **Filter Results**
   ```php
   add_filter('aips_similar_topics', function($topics) {
     // Filter or modify similar topics
     return $topics;
   });
   ```
