# MCP Bridge - Content Generation Tools

This document describes the new content generation and management tools added to the MCP Bridge.

## Available Tools

### 12. generate_post

Generate a single AI-powered post immediately using a template, author topic, or schedule configuration.

**Parameters:**
- `template_id` (integer, optional): Template ID to use for generation
- `author_topic_id` (integer, optional): Author topic ID for topic-based generation
- `schedule_id` (integer, optional): Schedule ID to use schedule configuration
- `overrides` (object, optional): Post creation overrides
  - `title` (string): Override AI-generated title
  - `category_ids` (array): Array of category IDs
  - `tag_ids` (array): Array of tag IDs
  - `post_status` (string): Post status (draft, publish, pending, private)
  - `post_author` (integer): WordPress user ID

**Note:** You must provide at least one of: `template_id`, `author_topic_id`, or `schedule_id`.

**Request:**
```json
{
  "jsonrpc": "2.0",
  "method": "generate_post",
  "params": {
    "template_id": 1,
    "overrides": {
      "post_status": "draft",
      "category_ids": [5],
      "tag_ids": [10, 15]
    }
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
    "post_id": 123,
    "history_id": 456,
    "post": {
      "id": 123,
      "title": "AI Generated Article Title",
      "status": "draft",
      "url": "https://site.com/ai-generated-article-title/",
      "edit_url": "https://site.com/wp-admin/post.php?post=123&action=edit"
    }
  },
  "id": 1
}
```

**Use Cases:**
- Generate a post on demand from a template
- Generate posts from author topics in bulk
- Trigger post generation with custom configuration
- Override post settings programmatically

**Example with schedule:**
```json
{
  "jsonrpc": "2.0",
  "method": "generate_post",
  "params": {
    "schedule_id": 5,
    "overrides": {
      "post_status": "publish",
      "title": "Custom Title Override"
    }
  },
  "id": 1
}
```

**Example with author topic:**
```json
{
  "jsonrpc": "2.0",
  "method": "generate_post",
  "params": {
    "author_topic_id": 42,
    "overrides": {
      "post_author": 2
    }
  },
  "id": 1
}
```

### 13. list_templates

Get all available templates with optional filtering.

**Parameters:**
- `active_only` (boolean, optional): Return only active templates. Default: false
- `search` (string, optional): Search term to filter templates by name

**Request:**
```json
{
  "jsonrpc": "2.0",
  "method": "list_templates",
  "params": {
    "active_only": true,
    "search": "blog"
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
    "templates": [
      {
        "id": 1,
        "name": "Blog Post Template",
        "is_active": true,
        "prompt_template": "Write a blog post about {{topic}}...",
        "title_prompt": "Generate a catchy title...",
        "excerpt_prompt": "Write a brief summary...",
        "post_status": "draft",
        "post_category": 5,
        "post_author": 1,
        "voice_id": 2,
        "article_structure_id": 3,
        "created_at": "2024-01-15 10:00:00"
      }
    ],
    "count": 1
  },
  "id": 1
}
```

**Use Cases:**
- Discover available templates
- Find templates by name
- Get template configuration for generation
- Check which templates are active

**Example - Get all active templates:**
```bash
curl -X POST https://site.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php \
  -u admin:password \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "method": "list_templates",
    "params": {"active_only": true},
    "id": 1
  }'
```

### 14. get_generation_history

Retrieve past post generations with filtering and pagination.

**Parameters:**
- `per_page` (integer, optional): Number of items per page (1-100). Default: 20
- `page` (integer, optional): Page number. Default: 1
- `status` (string, optional): Filter by status (completed, failed, pending)
- `template_id` (integer, optional): Filter by template ID
- `search` (string, optional): Search term for post title

**Request:**
```json
{
  "jsonrpc": "2.0",
  "method": "get_generation_history",
  "params": {
    "per_page": 10,
    "page": 1,
    "status": "completed",
    "template_id": 1
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
    "items": [
      {
        "id": 456,
        "uuid": "abc123-def456-ghi789",
        "post_id": 123,
        "template_id": 1,
        "template_name": "Blog Post Template",
        "status": "completed",
        "generated_title": "AI Generated Article Title",
        "error_message": null,
        "created_at": "2024-01-15 10:00:00",
        "completed_at": "2024-01-15 10:05:00",
        "post_url": "https://site.com/ai-generated-article-title/",
        "edit_url": "https://site.com/wp-admin/post.php?post=123&action=edit"
      }
    ],
    "pagination": {
      "total": 50,
      "pages": 5,
      "current_page": 1,
      "per_page": 10
    }
  },
  "id": 1
}
```

**Use Cases:**
- Monitor generation success rates
- Troubleshoot failed generations
- Track generation history over time
- Find posts generated from specific templates

**Example - Get recent failures:**
```json
{
  "jsonrpc": "2.0",
  "method": "get_generation_history",
  "params": {
    "status": "failed",
    "per_page": 20
  },
  "id": 1
}
```

**Example - Search by title:**
```json
{
  "jsonrpc": "2.0",
  "method": "get_generation_history",
  "params": {
    "search": "marketing",
    "status": "completed"
  },
  "id": 1
}
```

## Workflow Examples

### Example 1: Generate Posts from Multiple Templates

```bash
#!/bin/bash
# Generate one post from each active template

# Get all active templates
TEMPLATES=$(curl -s -X POST $MCP_URL -u admin:$PASSWORD \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"list_templates","params":{"active_only":true},"id":1}' \
  | jq -r '.result.templates[].id')

# Generate a post from each template
for TEMPLATE_ID in $TEMPLATES; do
  curl -X POST $MCP_URL -u admin:$PASSWORD \
    -H "Content-Type: application/json" \
    -d "{\"jsonrpc\":\"2.0\",\"method\":\"generate_post\",\"params\":{\"template_id\":$TEMPLATE_ID},\"id\":1}"
  
  echo "Generated post from template $TEMPLATE_ID"
  sleep 5 # Rate limiting
done
```

### Example 2: Monitor Generation Success Rate

```python
import requests
import json

def get_success_rate(url, username, password):
    """Calculate generation success rate from history"""
    
    # Get all history
    response = requests.post(
        url,
        auth=(username, password),
        json={
            "jsonrpc": "2.0",
            "method": "get_generation_history",
            "params": {"per_page": 100},
            "id": 1
        }
    )
    
    data = response.json()['result']
    items = data['items']
    
    total = len(items)
    successful = len([i for i in items if i['status'] == 'completed'])
    failed = len([i for i in items if i['status'] == 'failed'])
    
    success_rate = (successful / total * 100) if total > 0 else 0
    
    print(f"Total generations: {total}")
    print(f"Successful: {successful}")
    print(f"Failed: {failed}")
    print(f"Success rate: {success_rate:.1f}%")
    
    return success_rate

# Usage
get_success_rate(MCP_URL, "admin", "password")
```

### Example 3: Bulk Generate with Custom Settings

```python
import requests
import json
import time

def bulk_generate(url, username, password, template_id, count=5):
    """Generate multiple posts from a template with custom settings"""
    
    for i in range(count):
        response = requests.post(
            url,
            auth=(username, password),
            json={
                "jsonrpc": "2.0",
                "method": "generate_post",
                "params": {
                    "template_id": template_id,
                    "overrides": {
                        "post_status": "draft",
                        "category_ids": [5],
                        "tag_ids": [10, 15, 20]
                    }
                },
                "id": i + 1
            }
        )
        
        result = response.json()
        if 'result' in result:
            post_id = result['result']['post_id']
            print(f"Generated post {i+1}/{count}: Post ID {post_id}")
        else:
            print(f"Failed to generate post {i+1}: {result.get('error')}")
        
        time.sleep(2)  # Rate limiting

# Usage
bulk_generate(MCP_URL, "admin", "password", template_id=1, count=5)
```

### Example 4: Find and Republish Draft Posts

```bash
#!/bin/bash
# Find completed generations with draft status and publish them

# Get history of completed generations
HISTORY=$(curl -s -X POST $MCP_URL -u admin:$PASSWORD \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"get_generation_history","params":{"status":"completed","per_page":50},"id":1}')

# Extract post IDs that are in draft status
# (This would require WordPress API to check post status)

echo "Found draft posts to publish"
# ... additional logic to publish posts
```

## Error Handling

### Common Errors

**Error: missing_source**
```json
{
  "error": {
    "code": -32000,
    "message": "Must provide template_id, author_topic_id, or schedule_id"
  }
}
```
**Solution:** Provide at least one source parameter.

**Error: template_not_found**
```json
{
  "error": {
    "code": -32000,
    "message": "Template not found"
  }
}
```
**Solution:** Verify the template ID exists using `list_templates`.

**Error: ai_unavailable**
```json
{
  "error": {
    "code": -32000,
    "message": "AI Engine is not available. Please check your configuration."
  }
}
```
**Solution:** Ensure Meow Apps AI Engine plugin is installed, activated, and configured.

## Performance Considerations

### Rate Limiting

AI generation can be resource-intensive. Consider:

1. **Add delays** between generations (2-5 seconds)
2. **Limit batch size** to 5-10 posts at a time
3. **Monitor history** for failed generations
4. **Check AI Engine** rate limits and quotas

### Pagination Best Practices

For large history queries:

```python
def get_all_history(url, username, password):
    """Fetch all history with pagination"""
    all_items = []
    page = 1
    
    while True:
        response = requests.post(
            url,
            auth=(username, password),
            json={
                "jsonrpc": "2.0",
                "method": "get_generation_history",
                "params": {
                    "per_page": 100,
                    "page": page
                },
                "id": page
            }
        )
        
        data = response.json()['result']
        all_items.extend(data['items'])
        
        if page >= data['pagination']['pages']:
            break
            
        page += 1
    
    return all_items
```

## Integration Tips

### 1. Automated Content Pipeline

Use these tools to create an automated content generation pipeline:

```
list_templates → generate_post → get_generation_history → monitor results
```

### 2. Quality Control

Check generation history regularly to identify:
- High failure rates
- Problematic templates
- Performance issues

### 3. Scheduled Generation

Combine with cron to automate post generation:

```bash
#!/bin/bash
# Add to crontab: 0 */6 * * * /path/to/generate-posts.sh

# Generate 3 posts every 6 hours
for i in {1..3}; do
  curl -X POST $MCP_URL -u admin:$PASSWORD \
    -H "Content-Type: application/json" \
    -d '{"jsonrpc":"2.0","method":"generate_post","params":{"template_id":1},"id":1}'
  sleep 5
done
```

## Version History

- **v1.1.0** (2026-02-10): Added content generation tools
  - `generate_post` - Generate posts on demand
  - `list_templates` - List available templates
  - `get_generation_history` - Retrieve generation history
