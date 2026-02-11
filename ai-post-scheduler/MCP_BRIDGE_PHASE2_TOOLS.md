# MCP Bridge - Phase 2 Tools Documentation

This document describes the Phase 2 tools added to the MCP Bridge for history management, author management, and component regeneration.

## New Tools (6)

### 1. get_history

Get detailed history record by history ID or post ID, including optional detailed logs.

**Parameters:**
- `history_id` (integer, optional): History record ID
- `post_id` (integer, optional): WordPress post ID to find history for
- `include_logs` (boolean, optional): Include detailed log entries. Default: true

**Note:** Must provide either `history_id` OR `post_id`.

**Request with history_id:**
```json
{
  "jsonrpc": "2.0",
  "method": "get_history",
  "params": {
    "history_id": 123,
    "include_logs": true
  },
  "id": 1
}
```

**Request with post_id:**
```json
{
  "jsonrpc": "2.0",
  "method": "get_history",
  "params": {
    "post_id": 456
  },
  "id": 1
}
```

**Response:**
```json
{
  "jsonrpc": "2.0",
  "result": {
    "success": true,
    "history": {
      "id": 123,
      "uuid": "abc123-def456-ghi789",
      "post_id": 456,
      "template_id": 1,
      "author_id": null,
      "topic_id": null,
      "status": "completed",
      "generated_title": "AI Generated Post Title",
      "generated_content": "Full content...",
      "error_message": null,
      "creation_method": "manual",
      "created_at": "2024-01-15 10:00:00",
      "completed_at": "2024-01-15 10:05:00",
      "post_url": "https://site.com/ai-generated-post-title/",
      "edit_url": "https://site.com/wp-admin/post.php?post=456&action=edit",
      "logs": [
        {
          "id": 1,
          "log_type": "ai_request",
          "history_type_id": 1,
          "details": {"prompt": "...", "options": {}},
          "timestamp": "2024-01-15 10:00:10"
        }
      ],
      "log_count": 1
    }
  },
  "id": 1
}
```

**Use Cases:**
- Debug failed generations
- Analyze generation process
- Track generation history for a post
- Access detailed AI interaction logs

---

### 2. list_authors

Get all authors with optional filtering.

**Parameters:**
- `active_only` (boolean, optional): Return only active authors. Default: false

**Request:**
```json
{
  "jsonrpc": "2.0",
  "method": "list_authors",
  "params": {
    "active_only": true
  },
  "id": 1
}
```

**Response:**
```json
{
  "jsonrpc": "2.0",
  "result": {
    "success": true,
    "authors": [
      {
        "id": 1,
        "name": "Tech Expert",
        "bio": "Senior technology analyst with 10 years experience",
        "expertise": "AI, Machine Learning, Cloud Computing",
        "tone": "Professional, informative",
        "is_active": true,
        "created_at": "2024-01-10 09:00:00"
      }
    ],
    "count": 1
  },
  "id": 1
}
```

**Use Cases:**
- Discover available authors
- List active authors for content generation
- Browse author profiles
- Author management

---

### 3. get_author

Get detailed author information by ID.

**Parameters:**
- `author_id` (integer, required): Author ID

**Request:**
```json
{
  "jsonrpc": "2.0",
  "method": "get_author",
  "params": {
    "author_id": 1
  },
  "id": 1
}
```

**Response:**
```json
{
  "jsonrpc": "2.0",
  "result": {
    "success": true,
    "author": {
      "id": 1,
      "name": "Tech Expert",
      "bio": "Senior technology analyst with 10 years experience",
      "expertise": "AI, Machine Learning, Cloud Computing",
      "tone": "Professional, informative",
      "is_active": true,
      "created_at": "2024-01-10 09:00:00",
      "updated_at": "2024-01-15 10:00:00"
    }
  },
  "id": 1
}
```

**Use Cases:**
- Get author details before topic generation
- View author profile
- Verify author configuration

---

### 4. list_author_topics

Get topics for an author with filtering and pagination.

**Parameters:**
- `author_id` (integer, required): Author ID
- `status` (string, optional): Filter by status (pending, approved, rejected)
- `limit` (integer, optional): Maximum topics to return (1-500). Default: 50

**Request:**
```json
{
  "jsonrpc": "2.0",
  "method": "list_author_topics",
  "params": {
    "author_id": 1,
    "status": "approved",
    "limit": 20
  },
  "id": 1
}
```

**Response:**
```json
{
  "jsonrpc": "2.0",
  "result": {
    "success": true,
    "topics": [
      {
        "id": 42,
        "author_id": 1,
        "topic_title": "The Future of AI in Healthcare",
        "topic_prompt": "Explore how AI is transforming healthcare...",
        "status": "approved",
        "score": 85,
        "keywords": "AI, healthcare, machine learning, diagnosis",
        "metadata": "",
        "generated_at": "2024-01-14 15:00:00",
        "approved_at": "2024-01-14 16:00:00"
      }
    ],
    "count": 1,
    "total_available": 15
  },
  "id": 1
}
```

**Use Cases:**
- Browse topics for an author
- Find approved topics for generation
- Filter topics by status
- Review pending topics

---

### 5. get_author_topic

Get detailed information about a specific topic.

**Parameters:**
- `topic_id` (integer, required): Topic ID

**Request:**
```json
{
  "jsonrpc": "2.0",
  "method": "get_author_topic",
  "params": {
    "topic_id": 42
  },
  "id": 1
}
```

**Response:**
```json
{
  "jsonrpc": "2.0",
  "result": {
    "success": true,
    "topic": {
      "id": 42,
      "author_id": 1,
      "topic_title": "The Future of AI in Healthcare",
      "topic_prompt": "Explore how AI is transforming healthcare...",
      "status": "approved",
      "score": 85,
      "keywords": "AI, healthcare, machine learning, diagnosis",
      "metadata": "",
      "generated_at": "2024-01-14 15:00:00",
      "approved_at": "2024-01-14 16:00:00",
      "feedback": "Excellent topic, very timely"
    }
  },
  "id": 1
}
```

**Use Cases:**
- Review topic details before generation
- Check topic status and score
- Access topic metadata and feedback

---

### 6. regenerate_post_component

Regenerate individual post components (title, excerpt, content, or featured_image) using the original generation context.

**Parameters:**
- `post_id` (integer, required): WordPress post ID
- `history_id` (integer, required): History record ID for context
- `component` (string, required): Component to regenerate (title, excerpt, content, featured_image)
- `save` (boolean, optional): Automatically save to post. Default: false (preview only)

**Request (Preview Mode):**
```json
{
  "jsonrpc": "2.0",
  "method": "regenerate_post_component",
  "params": {
    "post_id": 456,
    "history_id": 123,
    "component": "title",
    "save": false
  },
  "id": 1
}
```

**Response (Preview):**
```json
{
  "jsonrpc": "2.0",
  "result": {
    "success": true,
    "component": "title",
    "post_id": 456,
    "history_id": 123,
    "saved": false,
    "new_value": "New AI Generated Title",
    "message": "Title regenerated (preview only, not saved)"
  },
  "id": 1
}
```

**Request (Save Mode):**
```json
{
  "jsonrpc": "2.0",
  "method": "regenerate_post_component",
  "params": {
    "post_id": 456,
    "history_id": 123,
    "component": "excerpt",
    "save": true
  },
  "id": 1
}
```

**Response (Saved):**
```json
{
  "jsonrpc": "2.0",
  "result": {
    "success": true,
    "component": "excerpt",
    "post_id": 456,
    "history_id": 123,
    "saved": true,
    "new_value": "New excerpt content...",
    "message": "Excerpt regenerated and saved successfully"
  },
  "id": 1
}
```

**Use Cases:**
- Regenerate individual components without full post regeneration
- A/B test different titles or excerpts
- Fix or improve specific parts of generated content
- Update featured images

**Supported Components:**
- `title` - Post title
- `excerpt` - Post excerpt/summary
- `content` - Full post content
- `featured_image` - Featured image (returns attachment_id and URL)

---

## Workflow Examples

### Example 1: Debug Failed Generation

```bash
#!/bin/bash
# Find and analyze a failed generation

# Get recent failed generations
HISTORY=$(curl -s -X POST $MCP_URL -u admin:$PASSWORD \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc":"2.0",
    "method":"get_generation_history",
    "params":{"status":"failed","per_page":5},
    "id":1
  }')

# Extract first failed history ID
HISTORY_ID=$(echo "$HISTORY" | jq -r '.result.items[0].id')

# Get detailed history with logs
curl -X POST $MCP_URL -u admin:$PASSWORD \
  -H "Content-Type: application/json" \
  -d "{
    \"jsonrpc\":\"2.0\",
    \"method\":\"get_history\",
    \"params\":{\"history_id\":$HISTORY_ID,\"include_logs\":true},
    \"id\":1
  }" | jq '.result.history'
```

### Example 2: Browse Author Topics and Generate

```python
import requests

def generate_from_approved_topics(url, username, password, author_id):
    """Find approved topics and generate posts"""
    
    # Get approved topics
    response = requests.post(
        url,
        auth=(username, password),
        json={
            "jsonrpc": "2.0",
            "method": "list_author_topics",
            "params": {
                "author_id": author_id,
                "status": "approved",
                "limit": 10
            },
            "id": 1
        }
    )
    
    topics = response.json()['result']['topics']
    
    # Generate post from each topic
    for topic in topics:
        gen_response = requests.post(
            url,
            auth=(username, password),
            json={
                "jsonrpc": "2.0",
                "method": "generate_post",
                "params": {
                    "author_topic_id": topic['id']
                },
                "id": 2
            }
        )
        
        result = gen_response.json()
        if 'result' in result:
            print(f"Generated post {result['result']['post_id']} from topic: {topic['topic_title']}")
        else:
            print(f"Failed to generate from topic: {topic['topic_title']}")

# Usage
generate_from_approved_topics(MCP_URL, "admin", "password", author_id=1)
```

### Example 3: Regenerate Components Until Satisfied

```python
def regenerate_until_satisfied(url, username, password, post_id, history_id):
    """Regenerate title multiple times, preview each, then save best one"""
    
    titles = []
    
    # Generate 3 variations
    for i in range(3):
        response = requests.post(
            url,
            auth=(username, password),
            json={
                "jsonrpc": "2.0",
                "method": "regenerate_post_component",
                "params": {
                    "post_id": post_id,
                    "history_id": history_id,
                    "component": "title",
                    "save": False  # Preview only
                },
                "id": i + 1
            }
        )
        
        result = response.json()
        if 'result' in result:
            titles.append(result['result']['new_value'])
    
    # Show titles to user
    print("Generated titles:")
    for i, title in enumerate(titles):
        print(f"{i+1}. {title}")
    
    # User selects best one (or generate more)
    # ... selection logic ...
    
    # Save selected title
    # Would need to regenerate with save=true or manually update

# Usage
regenerate_until_satisfied(MCP_URL, "admin", "password", post_id=456, history_id=123)
```

### Example 4: Author Management

```bash
#!/bin/bash
# List all authors and their active topics

AUTHORS=$(curl -s -X POST $MCP_URL -u admin:$PASSWORD \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"list_authors","params":{"active_only":true},"id":1}')

echo "$AUTHORS" | jq -r '.result.authors[] | "\(.id): \(.name) - \(.expertise)"'

# For each author, get their approved topic count
for AUTHOR_ID in $(echo "$AUTHORS" | jq -r '.result.authors[].id'); do
  TOPICS=$(curl -s -X POST $MCP_URL -u admin:$PASSWORD \
    -H "Content-Type: application/json" \
    -d "{\"jsonrpc\":\"2.0\",\"method\":\"list_author_topics\",\"params\":{\"author_id\":$AUTHOR_ID,\"status\":\"approved\"},\"id\":1}")
  
  COUNT=$(echo "$TOPICS" | jq -r '.result.count')
  NAME=$(echo "$AUTHORS" | jq -r ".result.authors[] | select(.id == $AUTHOR_ID) | .name")
  
  echo "Author: $NAME has $COUNT approved topics"
done
```

## Error Handling

### Common Errors

**get_history - Missing Parameter:**
```json
{
  "error": {
    "code": -32000,
    "message": "Must provide history_id or post_id"
  }
}
```

**get_author - Not Found:**
```json
{
  "error": {
    "code": -32000,
    "message": "Author not found"
  }
}
```

**regenerate_post_component - Invalid Component:**
```json
{
  "error": {
    "code": -32000,
    "message": "Invalid component. Must be one of: title, excerpt, content, featured_image"
  }
}
```

**regenerate_post_component - Context Mismatch:**
```json
{
  "error": {
    "code": -32000,
    "message": "History record does not belong to this post"
  }
}
```

## Integration Tips

### 1. History Analysis

Use `get_history` with `include_logs: true` to debug failed generations and understand the AI interaction flow.

### 2. Author-Based Workflows

Combine `list_authors`, `list_author_topics`, and `generate_post` to create author-centric content pipelines.

### 3. Component Refinement

Use `regenerate_post_component` with `save: false` to preview multiple variations before committing.

### 4. Batch Operations

Generate posts from multiple approved topics in parallel using the author topic tools.

## Version History

- **v1.2.0** (2026-02-10): Added Phase 2 tools
  - `get_history` - Detailed history access
  - `list_authors` - Author discovery
  - `get_author` - Author details
  - `list_author_topics` - Topic management
  - `get_author_topic` - Topic details
  - `regenerate_post_component` - Component regeneration
