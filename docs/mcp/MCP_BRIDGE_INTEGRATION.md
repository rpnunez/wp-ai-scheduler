# MCP Bridge Integration Guide

Complete guide for integrating the AI Post Scheduler MCP Bridge with various tools and platforms.

## Table of Contents

1. [Overview](#overview)
2. [Integration Options](#integration-options)
3. [Quick Start](#quick-start)
4. [Platform-Specific Guides](#platform-specific-guides)
5. [Common Workflows](#common-workflows)
6. [Best Practices](#best-practices)

## Overview

The AI Post Scheduler MCP Bridge exposes 25 tools via JSON-RPC 2.0 protocol, making plugin functionality available to:

- 🚀 **GitHub Copilot** - Direct integration in VSCode
- 🤖 **MCP Clients** - Any MCP-compatible tool
- 🔧 **Custom Scripts** - Bash, Python, Node.js automation
- 🌐 **HTTP APIs** - Direct REST-like access
- 📊 **Monitoring Tools** - Dashboard integrations

## Integration Options

### 1. VSCode with GitHub Copilot

**Best for**: Developers working in VSCode who want AI-assisted content management

**Setup**: See [MCP_BRIDGE_VSCODE_SETUP.md](./MCP_BRIDGE_VSCODE_SETUP.md)

**Pros**:
- ✅ Natural language queries
- ✅ Integrated in development workflow
- ✅ No additional tools needed

**Cons**:
- ⚠️ Requires GitHub Copilot subscription
- ⚠️ Limited to VSCode environment

### 2. Command Line (curl/bash)

**Best for**: Quick operations, automation scripts, CI/CD pipelines

**Setup**: See [MCP_BRIDGE_QUICKSTART.md](./MCP_BRIDGE_QUICKSTART.md)

**Example**:
```bash
curl -X POST https://your-site.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php \
  -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"list_tools","params":{},"id":1}'
```

**Pros**:
- ✅ No special tools required
- ✅ Easy to script
- ✅ Works everywhere

**Cons**:
- ⚠️ Manual JSON construction
- ⚠️ No type checking

### 3. Python Client

**Best for**: Complex automation, data analysis, custom dashboards

**Setup**: Use the example client in `mcp-client-example.py`

**Example**:
```python
from mcp_client import AIPSMCPClient

client = AIPSMCPClient(
    url="https://your-site.com/...",
    username="admin",
    password="xxxx xxxx xxxx xxxx xxxx xxxx"
)

# Generate a post
result = client.generate_post(
    template_id=1,
    overrides={"post_status": "draft"}
)
print(f"Created post: {result['post_id']}")

# Get stats
stats = client.get_generation_stats(period="week")
print(f"Success rate: {stats['success_rate']}%")
```

**Pros**:
- ✅ Type hints and validation
- ✅ Easy error handling
- ✅ Powerful for automation

**Cons**:
- ⚠️ Requires Python setup
- ⚠️ Additional dependency

### 4. Node.js/JavaScript

**Best for**: Web dashboards, browser extensions, Electron apps

**Example**:
```javascript
class AIPSMCPClient {
  constructor(url, username, password) {
    this.url = url;
    this.auth = btoa(`${username}:${password}`);
  }

  async call(method, params = {}) {
    const response = await fetch(this.url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Basic ${this.auth}`
      },
      body: JSON.stringify({
        jsonrpc: '2.0',
        method: method,
        params: params,
        id: Date.now()
      })
    });
    
    const data = await response.json();
    if (data.error) throw new Error(data.error.message);
    return data.result;
  }

  async generatePost(templateId, overrides = {}) {
    return this.call('generate_post', {
      template_id: templateId,
      overrides: overrides
    });
  }

  async getStats(period = 'all') {
    return this.call('get_generation_stats', { period });
  }
}

// Usage
const client = new AIPSMCPClient(
  'https://your-site.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php',
  'admin',
  'xxxx xxxx xxxx xxxx xxxx xxxx'
);

const stats = await client.getStats('week');
console.log(`Success rate: ${stats.success_rate}%`);
```

**Pros**:
- ✅ Native browser support
- ✅ Great for dashboards
- ✅ NPM ecosystem

**Cons**:
- ⚠️ CORS considerations
- ⚠️ Credential handling

### 5. PHP Integration

**Best for**: WordPress plugins/themes, server-side automation

**Example**:
```php
require_once AIPS_PLUGIN_DIR . 'mcp-bridge.php';

$bridge = new AIPS_MCP_Bridge();

// Generate a post
$result = $bridge->execute_tool('generate_post', array(
    'template_id' => 1,
    'overrides' => array(
        'post_status' => 'draft',
        'category_ids' => array(5, 7)
    )
));

if (is_wp_error($result)) {
    error_log('Generation failed: ' . $result->get_error_message());
} else {
    error_log('Created post: ' . $result['post_id']);
}
```

**Pros**:
- ✅ Direct function calls
- ✅ No HTTP overhead
- ✅ Full WordPress integration

**Cons**:
- ⚠️ Requires PHP/WordPress environment
- ⚠️ Less portable

## Quick Start

### 1. Verify MCP Bridge is Accessible

```bash
curl -I https://your-site.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php
```

Should return `200 OK`.

### 2. Test Authentication

```bash
curl -X POST https://your-site.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php \
  -u "your-username:your-app-password" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"get_plugin_info","params":{},"id":1}'
```

Should return plugin version and configuration.

### 3. List Available Tools

```bash
curl -X POST https://your-site.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php \
  -u "your-username:your-app-password" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"list_tools","params":{},"id":1}'
```

Should return 25 tools.

### 4. Choose Your Integration Method

Based on your needs, follow the appropriate setup guide:
- **VSCode**: [MCP_BRIDGE_VSCODE_SETUP.md](./MCP_BRIDGE_VSCODE_SETUP.md)
- **Command Line**: [MCP_BRIDGE_QUICKSTART.md](./MCP_BRIDGE_QUICKSTART.md)
- **Python**: See `mcp-client-example.py`
- **Shell Script**: See `mcp-client-example.sh`

## Platform-Specific Guides

### VSCode + GitHub Copilot

Complete guide: [MCP_BRIDGE_VSCODE_SETUP.md](./MCP_BRIDGE_VSCODE_SETUP.md)

**Quick Setup**:
1. Install GitHub Copilot extension
2. Create `.vscode/settings.json`:
   ```json
   {
     "mcp.servers": {
       "aips": {
         "name": "AI Post Scheduler",
         "url": "https://your-site.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php",
         "auth": {
           "type": "basic",
           "username": "${env:WP_USERNAME}",
           "password": "${env:WP_APP_PASSWORD}"
         }
       }
     }
   }
   ```
3. Set environment variables in `.env`
4. Test: `@mcp list_tools from aips`

### CI/CD Integration (GitHub Actions, GitLab CI)

**Example GitHub Actions Workflow**:

```yaml
name: Generate Weekly Content

on:
  schedule:
    - cron: '0 9 * * 1'  # Every Monday at 9 AM
  workflow_dispatch:

jobs:
  generate-content:
    runs-on: ubuntu-latest
    steps:
      - name: Generate Posts
        run: |
          curl -X POST ${{ secrets.MCP_BRIDGE_URL }} \
            -u "${{ secrets.WP_USERNAME }}:${{ secrets.WP_APP_PASSWORD }}" \
            -H "Content-Type: application/json" \
            -d '{
              "jsonrpc": "2.0",
              "method": "generate_post",
              "params": {
                "template_id": 1,
                "overrides": {
                  "post_status": "draft"
                }
              },
              "id": 1
            }'

      - name: Get Generation Stats
        run: |
          curl -X POST ${{ secrets.MCP_BRIDGE_URL }} \
            -u "${{ secrets.WP_USERNAME }}:${{ secrets.WP_APP_PASSWORD }}" \
            -H "Content-Type: application/json" \
            -d '{
              "jsonrpc": "2.0",
              "method": "get_generation_stats",
              "params": {"period": "week"},
              "id": 2
            }'
```

### Monitoring Dashboard

**Example with Simple HTML Dashboard**:

```html
<!DOCTYPE html>
<html>
<head>
  <title>AIPS Dashboard</title>
  <script>
    class AIPSClient {
      constructor(url, username, password) {
        this.url = url;
        this.auth = btoa(`${username}:${password}`);
      }

      async call(method, params = {}) {
        const response = await fetch(this.url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Basic ${this.auth}`
          },
          body: JSON.stringify({
            jsonrpc: '2.0',
            method: method,
            params: params,
            id: Date.now()
          })
        });
        return response.json();
      }
    }

    async function loadDashboard() {
      const client = new AIPSClient(
        'https://your-site.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php',
        'admin',
        'your-app-password'
      );

      // Get stats
      const stats = await client.call('get_generation_stats', {period: 'week'});
      document.getElementById('stats').innerHTML = `
        <h2>This Week</h2>
        <p>Total: ${stats.result.total}</p>
        <p>Success Rate: ${stats.result.success_rate}%</p>
      `;

      // Get recent history
      const history = await client.call('get_generation_history', {per_page: 5});
      const historyHtml = history.result.items.map(item => `
        <li>${item.created_at}: ${item.status} - ${item.template_name}</li>
      `).join('');
      document.getElementById('history').innerHTML = `
        <h2>Recent Generations</h2>
        <ul>${historyHtml}</ul>
      `;
    }

    window.onload = loadDashboard;
  </script>
</head>
<body>
  <h1>AI Post Scheduler Dashboard</h1>
  <div id="stats"></div>
  <div id="history"></div>
</body>
</html>
```

## Common Workflows

### Workflow 1: Automated Content Generation

**Goal**: Generate posts automatically on a schedule

**Tools Used**: `generate_post`, `get_generation_stats`

**Steps**:
1. Set up cron job or CI/CD schedule
2. Call `list_templates` to get active templates
3. For each template, call `generate_post`
4. Check results with `get_generation_history`
5. Send notifications based on success/failure

**Script Example** (`generate-daily-content.sh`):
```bash
#!/bin/bash

MCP_URL="https://your-site.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php"
AUTH="admin:xxxx xxxx xxxx xxxx xxxx xxxx"

# Get active templates
TEMPLATES=$(curl -s -X POST "$MCP_URL" \
  -u "$AUTH" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"list_templates","params":{"active_only":true},"id":1}')

# Generate post from first template
RESULT=$(curl -s -X POST "$MCP_URL" \
  -u "$AUTH" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"generate_post","params":{"template_id":1,"overrides":{"post_status":"draft"}},"id":2}')

echo "Generation result: $RESULT"
```

### Workflow 2: Content Performance Monitoring

**Goal**: Track generation success rates and identify issues

**Tools Used**: `get_generation_stats`, `get_generation_history`, `test_ai_connection`

**Python Example**:
```python
from mcp_client import AIPSMCPClient
import datetime

client = AIPSMCPClient(url, username, password)

# Get weekly stats
stats = client.get_generation_stats(period='week')

if stats['success_rate'] < 80:
    print(f"WARNING: Success rate is {stats['success_rate']}%")
    
    # Test AI connection
    test = client.test_ai_connection()
    if not test['success']:
        print(f"AI connection issue: {test['message']}")
    
    # Get failed generations
    history = client.get_generation_history(
        status='failed',
        per_page=10
    )
    
    print(f"Recent failures: {len(history['items'])}")
    for item in history['items']:
        print(f"  - {item['created_at']}: {item['error_message']}")
```

### Workflow 3: Bulk Content Regeneration

**Goal**: Regenerate specific components for multiple posts

**Tools Used**: `get_generation_history`, `regenerate_post_component`

**Example**:
```bash
# Get posts from last month
HISTORY=$(curl -s -X POST "$MCP_URL" -u "$AUTH" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"get_generation_history","params":{"per_page":100},"id":1}')

# For each post, regenerate the excerpt
# (Manual loop or use jq to parse and iterate)
```

### Workflow 4: Author Topic Management

**Goal**: Review and approve topics, generate posts from approved topics

**Tools Used**: `list_author_topics`, `get_author_topic`, `generate_post`

**Example**:
```python
# Get pending topics
topics = client.list_author_topics(
    author_id=1,
    status='pending',
    limit=50
)

for topic in topics['items']:
    print(f"Topic: {topic['title']}")
    print(f"Score: {topic['score']}")
    print(f"Keywords: {', '.join(topic['keywords'])}")
    
    # Approve in WordPress UI, then generate
    client.generate_post(
        author_topic_id=topic['id'],
        overrides={'post_status': 'draft'}
    )
```

## Best Practices

### Security

1. ✅ **Always use HTTPS** in production
2. ✅ **Use Application Passwords**, not main password
3. ✅ **Store credentials in environment variables**
4. ✅ **Add `.env` to `.gitignore`**
5. ✅ **Rotate passwords regularly**
6. ✅ **Use different credentials per environment**
7. ✅ **Monitor access logs** for suspicious activity
8. ✅ **Implement rate limiting** if needed

### Performance

1. ✅ **Use pagination** for large datasets
2. ✅ **Cache results** when appropriate
3. ✅ **Use filters** to limit data retrieval
4. ✅ **Set appropriate timeouts** for long operations
5. ✅ **Monitor API response times**
6. ✅ **Use async operations** for non-blocking calls

### Reliability

1. ✅ **Implement retry logic** for transient failures
2. ✅ **Handle errors gracefully**
3. ✅ **Log all operations** for debugging
4. ✅ **Test in staging** before production
5. ✅ **Monitor success rates**
6. ✅ **Set up alerts** for failures

### Development

1. ✅ **Use version control** for configurations
2. ✅ **Document custom workflows**
3. ✅ **Test thoroughly** before automation
4. ✅ **Keep credentials separate** from code
5. ✅ **Use meaningful IDs** in JSON-RPC requests
6. ✅ **Validate responses** before using data

## Troubleshooting

### Common Issues

See the [Troubleshooting section](./MCP_BRIDGE_VSCODE_SETUP.md#troubleshooting) in the VSCode setup guide for detailed solutions to:

- Connection issues
- Authentication errors
- Permission errors
- SSL/HTTPS issues
- Tool not found errors
- Timeout issues

### Getting Help

1. Check documentation for the specific tool
2. Review logs in WordPress (`wp-content/debug.log`)
3. Test with curl to isolate issues
4. Check network connectivity
5. Verify WordPress and plugin versions

## Documentation Index

### Setup Guides
- [VSCode Setup](./MCP_BRIDGE_VSCODE_SETUP.md) - Complete VSCode configuration
- [Quick Start](./MCP_BRIDGE_QUICKSTART.md) - Get started quickly

### Tool Documentation
- [Core Tools](./MCP_BRIDGE_README.md) - Original 11 tools
- [Content Tools](./MCP_BRIDGE_CONTENT_TOOLS.md) - Phase 1 (3 tools)
- [History & Authors](./MCP_BRIDGE_PHASE2_TOOLS.md) - Phase 2 (6 tools)
- [Analytics & Testing](./MCP_BRIDGE_PHASE3_TOOLS.md) - Phase 3 (5 tools)

### Reference
- [JSON Schema](./mcp-bridge-schema.json) - Complete schema for all 25 tools
- [Implementation Summary](../MCP_BRIDGE_IMPLEMENTATION_SUMMARY.md) - Technical details

### Examples
- `mcp-client-example.py` - Python client
- `mcp-client-example.sh` - Bash client
- `.vscode/settings.json.example` - VSCode configuration
- `.env.example` - Environment variables

## Next Steps

1. **Choose your integration method** based on your needs
2. **Follow the setup guide** for your chosen method
3. **Test the connection** with simple tools like `list_tools`
4. **Implement your workflow** starting with read-only operations
5. **Monitor and optimize** based on results

## Support

For issues or questions:
1. Review the troubleshooting guides
2. Check tool-specific documentation
3. Enable WordPress debug logging
4. Test with curl to isolate issues
5. Open an issue on GitHub with details

## Version

This integration guide is for MCP Bridge version 1.3.0 (25 tools).
