# MCP Bridge - Phase 3 Tools Documentation

This document describes the Phase 3 tools added to the MCP Bridge for analytics, metadata access, and testing.

## New Tools (5)

### 1. get_generation_stats

Get comprehensive generation statistics including success rates, performance metrics, and breakdowns.

**Parameters:**
- `period` (string, optional): Time period for stats: "all", "today", "week", "month". Default: "all"
- `template_id` (integer, optional): Filter stats by specific template ID

**Request (All Time):**
```json
{
  "jsonrpc": "2.0",
  "method": "get_generation_stats",
  "params": {
    "period": "all"
  },
  "id": 1
}
```

**Request (Weekly Stats):**
```json
{
  "jsonrpc": "2.0",
  "method": "get_generation_stats",
  "params": {
    "period": "week"
  },
  "id": 1
}
```

**Request (Template-Specific):**
```json
{
  "jsonrpc": "2.0",
  "method": "get_generation_stats",
  "params": {
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
    "stats": {
      "total": 150,
      "completed": 142,
      "failed": 6,
      "processing": 2,
      "success_rate": 94.7,
      "period": "week",
      "by_template": {
        "1": 45,
        "2": 38,
        "3": 59
      }
    }
  },
  "id": 1
}
```

**Use Cases:**
- Monitor generation success rates
- Track performance over time
- Identify problematic templates
- Generate analytics dashboards
- Calculate ROI metrics

---

### 2. get_post_metadata

Retrieve AI generation metadata for a specific WordPress post.

**Parameters:**
- `post_id` (integer, required): WordPress post ID

**Request:**
```json
{
  "jsonrpc": "2.0",
  "method": "get_post_metadata",
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
    "metadata": {
      "post_id": 456,
      "post_title": "AI Generated Post Title",
      "post_status": "publish",
      "post_date": "2024-01-15 10:00:00",
      "history_id": 123,
      "template_id": 1,
      "template_name": "Tech Article Template",
      "author_id": null,
      "topic_id": null,
      "creation_method": "manual",
      "generated_at": "2024-01-15 10:00:00",
      "completed_at": "2024-01-15 10:05:00",
      "status": "completed",
      "ai_model": "gpt-4",
      "tokens_used": 1250,
      "generation_time": 4.5,
      "has_prompt": true,
      "post_url": "https://site.com/post-title/",
      "edit_url": "https://site.com/wp-admin/post.php?post=456&action=edit"
    }
  },
  "id": 1
}
```

**Metadata Fields:**
- `post_id`, `post_title`, `post_status`, `post_date`: WordPress post info
- `history_id`: Related history record
- `template_id`, `template_name`: Template used
- `author_id`, `topic_id`: Author-topic info (if applicable)
- `creation_method`: manual or scheduled
- `generated_at`, `completed_at`: Timestamps
- `status`: Generation status
- `ai_model`: AI model used (e.g., "gpt-4")
- `tokens_used`: Token count
- `generation_time`: Generation duration in seconds
- `has_prompt`: Whether prompt is available
- `post_url`, `edit_url`: WordPress URLs

**Use Cases:**
- Audit AI-generated content
- Track token usage per post
- Verify generation details
- Calculate generation costs
- Quality control checks

---

### 3. get_ai_models

List available AI models from AI Engine configuration.

**Parameters:** None

**Request:**
```json
{
  "jsonrpc": "2.0",
  "method": "get_ai_models",
  "params": {},
  "id": 1
}
```

**Response:**
```json
{
  "jsonrpc": "2.0",
  "result": {
    "success": true,
    "current_model": "gpt-4",
    "models": [
      {
        "id": "gpt-4",
        "name": "GPT-4",
        "provider": "OpenAI",
        "type": "chat",
        "is_current": true
      },
      {
        "id": "gpt-4-turbo",
        "name": "GPT-4 Turbo",
        "provider": "OpenAI",
        "type": "chat",
        "is_current": false
      },
      {
        "id": "gpt-4o",
        "name": "GPT-4o",
        "provider": "OpenAI",
        "type": "chat",
        "is_current": false
      },
      {
        "id": "claude-3-opus",
        "name": "Claude 3 Opus",
        "provider": "Anthropic",
        "type": "chat",
        "is_current": false
      },
      {
        "id": "claude-3-sonnet",
        "name": "Claude 3 Sonnet",
        "provider": "Anthropic",
        "type": "chat",
        "is_current": false
      }
    ],
    "note": "Model availability depends on AI Engine configuration. List shows common models."
  },
  "id": 1
}
```

**Model Fields:**
- `id`: Model identifier (used in API calls)
- `name`: Human-readable name
- `provider`: Provider (OpenAI, Anthropic, etc.)
- `type`: Model type (chat, completion, etc.)
- `is_current`: Whether this is the currently configured model

**Note:** The list shows commonly available models. Actual availability depends on your AI Engine configuration and API keys.

**Use Cases:**
- Verify model configuration
- Switch between models
- Check available models
- Model comparison testing

---

### 4. test_ai_connection

Test the AI Engine connection with a simple query.

**Parameters:**
- `test_prompt` (string, optional): Custom test prompt. Default: "Say \"Hello\" if you can read this."

**Request (Default Prompt):**
```json
{
  "jsonrpc": "2.0",
  "method": "test_ai_connection",
  "params": {},
  "id": 1
}
```

**Request (Custom Prompt):**
```json
{
  "jsonrpc": "2.0",
  "method": "test_ai_connection",
  "params": {
    "test_prompt": "What is 2+2?"
  },
  "id": 1
}
```

**Response (Success):**
```json
{
  "jsonrpc": "2.0",
  "result": {
    "success": true,
    "connected": true,
    "test_prompt": "Say \"Hello\" if you can read this.",
    "response": "Hello! I can read your message perfectly.",
    "response_time_ms": 823.45,
    "model": "gpt-4",
    "message": "AI Engine connection successful"
  },
  "id": 1
}
```

**Response (Failure):**
```json
{
  "jsonrpc": "2.0",
  "result": {
    "success": false,
    "connected": false,
    "error": "API key not configured",
    "error_code": "api_key_missing",
    "response_time_ms": 15.23
  },
  "id": 1
}
```

**Use Cases:**
- Verify AI Engine setup
- Test API connectivity
- Monitor response times
- Troubleshoot configuration
- Health checks

---

### 5. get_plugin_settings

Retrieve plugin configuration settings by category.

**Parameters:**
- `category` (string, optional): Settings category: "ai", "resilience", "logging", "all". Default: "all"

**Request (All Settings):**
```json
{
  "jsonrpc": "2.0",
  "method": "get_plugin_settings",
  "params": {
    "category": "all"
  },
  "id": 1
}
```

**Request (AI Settings Only):**
```json
{
  "jsonrpc": "2.0",
  "method": "get_plugin_settings",
  "params": {
    "category": "ai"
  },
  "id": 1
}
```

**Response (All Categories):**
```json
{
  "jsonrpc": "2.0",
  "result": {
    "success": true,
    "category": "all",
    "settings": {
      "ai": {
        "model": "gpt-4",
        "max_tokens": 2000,
        "temperature": 0.7,
        "default_post_status": "draft",
        "default_post_author": 1
      },
      "resilience": {
        "enable_retry": true,
        "retry_max_attempts": 3,
        "retry_initial_delay": 1,
        "enable_rate_limiting": false,
        "rate_limit_requests": 10,
        "rate_limit_period": 60,
        "enable_circuit_breaker": false,
        "circuit_breaker_threshold": 5,
        "circuit_breaker_timeout": 300
      },
      "logging": {
        "enable_logging": true,
        "log_retention_days": 30
      },
      "thresholds": {
        "generated_posts_log_threshold_tmpfile": 200,
        "generated_posts_log_threshold_client": 20,
        "history_export_max_records": 10000
      }
    }
  },
  "id": 1
}
```

**Response (AI Category Only):**
```json
{
  "jsonrpc": "2.0",
  "result": {
    "success": true,
    "category": "ai",
    "settings": {
      "ai": {
        "model": "gpt-4",
        "max_tokens": 2000,
        "temperature": 0.7,
        "default_post_status": "draft",
        "default_post_author": 1
      }
    }
  },
  "id": 1
}
```

**Settings Categories:**

**AI Settings:**
- `model`: AI model identifier
- `max_tokens`: Maximum tokens per generation
- `temperature`: Creativity level (0.0-1.0)
- `default_post_status`: Default post status (draft/publish)
- `default_post_author`: Default WordPress user ID

**Resilience Settings:**
- `enable_retry`: Enable automatic retry on failure
- `retry_max_attempts`: Maximum retry attempts
- `retry_initial_delay`: Initial delay between retries (seconds)
- `enable_rate_limiting`: Enable rate limiting
- `rate_limit_requests`: Requests per period
- `rate_limit_period`: Rate limit period (seconds)
- `enable_circuit_breaker`: Enable circuit breaker pattern
- `circuit_breaker_threshold`: Failure threshold
- `circuit_breaker_timeout`: Timeout before retry (seconds)

**Logging Settings:**
- `enable_logging`: Enable logging
- `log_retention_days`: Days to keep logs

**Thresholds:**
- `generated_posts_log_threshold_tmpfile`: Threshold for temp file export
- `generated_posts_log_threshold_client`: Threshold for client display
- `history_export_max_records`: Maximum records for export

**Use Cases:**
- Configuration audit
- Settings backup
- Configuration comparison
- Troubleshooting
- Documentation

---

## Workflow Examples

### Example 1: Monitor Generation Performance

```bash
#!/bin/bash
# Weekly performance report

# Get weekly stats
STATS=$(curl -s -X POST $MCP_URL -u admin:$PASSWORD \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"get_generation_stats","params":{"period":"week"},"id":1}')

echo "$STATS" | jq '{
  total: .result.stats.total,
  success_rate: .result.stats.success_rate,
  failed: .result.stats.failed
}'

# Compare to all-time
ALL_TIME=$(curl -s -X POST $MCP_URL -u admin:$PASSWORD \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"get_generation_stats","params":{"period":"all"},"id":1}')

echo "All-time success rate: $(echo "$ALL_TIME" | jq -r '.result.stats.success_rate')%"
```

### Example 2: Audit Post Metadata

```python
import requests
import csv

def audit_generated_posts(url, username, password, post_ids):
    """Audit metadata for a list of posts"""
    
    results = []
    
    for post_id in post_ids:
        response = requests.post(
            url,
            auth=(username, password),
            json={
                "jsonrpc": "2.0",
                "method": "get_post_metadata",
                "params": {"post_id": post_id},
                "id": 1
            }
        )
        
        if 'result' in response.json():
            meta = response.json()['result']['metadata']
            results.append({
                'post_id': meta['post_id'],
                'title': meta['post_title'],
                'model': meta.get('ai_model', 'N/A'),
                'tokens': meta.get('tokens_used', 0),
                'time': meta.get('generation_time', 0),
                'status': meta['status']
            })
    
    # Export to CSV
    with open('post_audit.csv', 'w', newline='') as f:
        writer = csv.DictWriter(f, fieldnames=['post_id', 'title', 'model', 'tokens', 'time', 'status'])
        writer.writeheader()
        writer.writerows(results)
    
    print(f"Audited {len(results)} posts")

# Usage
audit_generated_posts(MCP_URL, "admin", "password", [456, 457, 458, 459, 460])
```

### Example 3: Test and Monitor AI Connection

```python
def monitor_ai_health(url, username, password):
    """Monitor AI Engine health"""
    
    # Test connection
    response = requests.post(
        url,
        auth=(username, password),
        json={
            "jsonrpc": "2.0",
            "method": "test_ai_connection",
            "params": {},
            "id": 1
        }
    )
    
    result = response.json()['result']
    
    if result['connected']:
        print(f"✅ AI Engine online")
        print(f"   Model: {result['model']}")
        print(f"   Response time: {result['response_time_ms']}ms")
        
        # Check if response time is acceptable
        if result['response_time_ms'] > 2000:
            print("⚠️  Slow response time detected")
    else:
        print(f"❌ AI Engine offline")
        print(f"   Error: {result['error']}")
        
        # Send alert
        send_alert(f"AI Engine offline: {result['error']}")

# Usage
monitor_ai_health(MCP_URL, "admin", "password")
```

### Example 4: Configuration Backup

```bash
#!/bin/bash
# Backup plugin settings

BACKUP_FILE="aips-settings-$(date +%Y%m%d-%H%M%S).json"

curl -s -X POST $MCP_URL -u admin:$PASSWORD \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"get_plugin_settings","params":{"category":"all"},"id":1}' \
  | jq '.result.settings' > "$BACKUP_FILE"

echo "Settings backed up to $BACKUP_FILE"

# Show summary
echo "Current configuration:"
jq '{
  ai_model: .ai.model,
  max_tokens: .ai.max_tokens,
  retry_enabled: .resilience.enable_retry,
  logging_enabled: .logging.enable_logging
}' "$BACKUP_FILE"
```

### Example 5: Calculate Token Costs

```python
def calculate_token_costs(url, username, password, post_ids, cost_per_1k_tokens=0.03):
    """Calculate costs for generated posts"""
    
    total_tokens = 0
    
    for post_id in post_ids:
        response = requests.post(
            url,
            auth=(username, password),
            json={
                "jsonrpc": "2.0",
                "method": "get_post_metadata",
                "params": {"post_id": post_id},
                "id": 1
            }
        )
        
        if 'result' in response.json():
            tokens = response.json()['result']['metadata'].get('tokens_used', 0)
            total_tokens += tokens
    
    total_cost = (total_tokens / 1000) * cost_per_1k_tokens
    
    print(f"Total tokens: {total_tokens:,}")
    print(f"Total cost: ${total_cost:.2f}")
    print(f"Average per post: {total_tokens / len(post_ids):.0f} tokens")
    
    return total_cost

# Usage
calculate_token_costs(MCP_URL, "admin", "password", [456, 457, 458])
```

## Error Handling

### Common Errors

**get_generation_stats - No Data:**
```json
{
  "result": {
    "success": true,
    "stats": {
      "total": 0,
      "completed": 0,
      "failed": 0,
      "processing": 0,
      "success_rate": 0,
      "by_template": {}
    }
  }
}
```

**get_post_metadata - Post Not Found:**
```json
{
  "error": {
    "code": -32000,
    "message": "Post not found"
  }
}
```

**get_post_metadata - No History:**
```json
{
  "error": {
    "code": -32000,
    "message": "No generation history found for this post"
  }
}
```

**test_ai_connection - AI Unavailable:**
```json
{
  "result": {
    "success": false,
    "connected": false,
    "error": "AI Engine plugin is not available or not installed",
    "message": "Please install and activate the AI Engine plugin"
  }
}
```

**test_ai_connection - API Key Missing:**
```json
{
  "result": {
    "success": false,
    "connected": false,
    "error": "API key not configured",
    "error_code": "api_key_missing",
    "response_time_ms": 15.23
  }
}
```

## Integration Tips

### 1. Performance Monitoring

Use `get_generation_stats` with different periods to track trends:
```python
periods = ['today', 'week', 'month']
for period in periods:
    stats = get_stats(period)
    print(f"{period}: {stats['success_rate']}% success")
```

### 2. Cost Tracking

Combine `get_post_metadata` with billing APIs to calculate actual costs based on token usage.

### 3. Health Checks

Use `test_ai_connection` in monitoring systems:
- Run every 5 minutes
- Alert if response time > 2000ms
- Alert if connection fails

### 4. Configuration Audits

Use `get_plugin_settings` for:
- Regular configuration backups
- Change detection
- Documentation generation

### 5. Analytics Dashboards

Build dashboards using:
- `get_generation_stats` for overview metrics
- `get_post_metadata` for detailed post analysis
- Real-time updates with periodic polling

## Version History

- **v1.3.0** (2026-02-11): Added Phase 3 tools
  - `get_generation_stats` - Performance analytics
  - `get_post_metadata` - Post metadata access
  - `get_ai_models` - Model discovery
  - `test_ai_connection` - Connection testing
  - `get_plugin_settings` - Configuration access
