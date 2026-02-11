# AI Post Scheduler MCP Bridge

## Overview

The MCP (Model Context Protocol) Bridge provides a JSON-RPC 2.0 style API that exposes AI Post Scheduler plugin functionality to MCP-compatible AI tools, GitHub Copilot, and other automation systems.

**🚀 Quick Links:**
- **[VSCode Setup Guide](./MCP_BRIDGE_VSCODE_SETUP.md)** - Configure VSCode + GitHub Copilot
- **[Integration Guide](./MCP_BRIDGE_INTEGRATION.md)** - All integration options
- **[Quick Start](./MCP_BRIDGE_QUICKSTART.md)** - Get started in minutes
- **[JSON Schema](./mcp-bridge-schema.json)** - Complete API reference

## Features

### Core Features (11 tools)
- **Cache Management**: Clear plugin transients and caches
- **Database Operations**: Repair, verify, and maintain database tables
- **System Diagnostics**: Comprehensive health checks and status information
- **Data Export**: Export plugin data in JSON or MySQL format
- **Cron Management**: Check status and trigger scheduled jobs
- **Upgrade Management**: Check and run database migrations
- **History Management**: Clear and manage generation history

### Content Generation (3 tools)
- Generate posts from templates or author topics
- List and filter templates
- View generation history

### Author Management (6 tools)
- Manage authors and topics
- Regenerate post components
- Get detailed history records

### Analytics & Testing (5 tools)
- Generation statistics and success rates
- Post metadata and AI details
- AI model information
- Connection testing
- Plugin settings access

**Total: 25 tools** across 5 categories. See [Tool Documentation](#available-tools) below.

## Installation

The MCP bridge is included in the plugin at `ai-post-scheduler/mcp-bridge.php`. No additional installation is required.

## Quick Start Options

### Option 1: VSCode + GitHub Copilot (Recommended)

Perfect for developers who want AI-assisted content management in their IDE.

👉 **[Complete VSCode Setup Guide](./MCP_BRIDGE_VSCODE_SETUP.md)**

Quick setup:
1. Create `.vscode/settings.json` with MCP server configuration
2. Set WordPress credentials in `.env`
3. Test with: `@mcp list_tools from aips`

### Option 2: Command Line

Quick operations and automation scripts.

👉 **[Quick Start Guide](./MCP_BRIDGE_QUICKSTART.md)**

```bash
curl -X POST https://your-site.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php \
  -u "admin:app-password" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"list_tools","params":{},"id":1}'
```

### Option 3: All Integration Methods

See **[MCP Bridge Integration Guide](./MCP_BRIDGE_INTEGRATION.md)** for:
- Python client
- Node.js/JavaScript
- PHP integration
- CI/CD pipelines
- Monitoring dashboards

## Usage

### As HTTP Endpoint

The bridge can be accessed via HTTP POST requests:

```bash
curl -X POST https://your-site.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "method": "list_tools",
    "params": {},
    "id": 1
  }'
```

### As PHP Include

You can also include the bridge in custom MCP server implementations:

```php
require_once AIPS_PLUGIN_DIR . 'mcp-bridge.php';

$bridge = new AIPS_MCP_Bridge();
$result = $bridge->execute_tool('clear_cache', array('cache_type' => 'all'));
```

## Authentication

The bridge requires WordPress admin capabilities (`manage_options`). Requests must be authenticated using:

- WordPress session cookies
- Application passwords
- Custom authentication token (if implemented)

## Available Tools

### 1. list_tools

Get a list of all available tools with their descriptions and parameters.

**Request:**
```json
{
  "jsonrpc": "2.0",
  "method": "list_tools",
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
    "version": "1.0.0",
    "tools": {
      "clear_cache": {
        "name": "clear_cache",
        "description": "Clear all plugin caches (transients)",
        "parameters": { ... }
      },
      ...
    }
  },
  "id": 1
}
```

### 2. clear_cache

Clear plugin caches and transients.

**Parameters:**
- `cache_type` (string, optional): Type of cache to clear
  - `all` (default): Clear all caches
  - `history_stats`: Clear history statistics cache
  - `schedule_stats`: Clear schedule statistics cache
  - Or specific transient name

**Request:**
```json
{
  "jsonrpc": "2.0",
  "method": "clear_cache",
  "params": {
    "cache_type": "all"
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
    "cleared": [
      "aips_history_stats",
      "aips_pending_schedule_stats",
      "aips_circuit_breaker_state",
      "aips_rate_limiter_requests"
    ],
    "count": 4
  },
  "id": 1
}
```

### 3. check_database

Verify database health and check that all tables and columns exist.

**Request:**
```json
{
  "jsonrpc": "2.0",
  "method": "check_database",
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
    "database": {
      "aips_history": {
        "label": "Table: aips_history",
        "value": "OK (25 columns)",
        "status": "ok"
      },
      ...
    }
  },
  "id": 1
}
```

### 4. repair_database

Repair or recreate database tables using WordPress dbDelta.

**Request:**
```json
{
  "jsonrpc": "2.0",
  "method": "repair_database",
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
    "message": "Database tables repaired/installed successfully"
  },
  "id": 1
}
```

### 5. check_upgrades

Check if database upgrades are needed and optionally run them.

**Parameters:**
- `run` (boolean, optional): If true, run upgrades. Default: false

**Request:**
```json
{
  "jsonrpc": "2.0",
  "method": "check_upgrades",
  "params": {
    "run": true
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
    "current_version": "1.7.0",
    "plugin_version": "1.7.0",
    "needs_upgrade": false,
    "upgraded": false
  },
  "id": 1
}
```

### 6. system_status

Get comprehensive system status information.

**Parameters:**
- `section` (string, optional): Specific section to retrieve
  - `all` (default): All sections
  - `environment`: PHP, WordPress, MySQL versions
  - `plugin`: Plugin version and dependencies
  - `database`: Database tables status
  - `filesystem`: File permissions
  - `cron`: WordPress cron status
  - `logs`: Log file status

**Request:**
```json
{
  "jsonrpc": "2.0",
  "method": "system_status",
  "params": {
    "section": "plugin"
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
    "section": "plugin",
    "data": {
      "version": {
        "label": "Plugin Version",
        "value": "1.7.0",
        "status": "ok"
      },
      "db_version": {
        "label": "Database Version",
        "value": "1.7.0",
        "status": "ok"
      },
      "ai_engine": {
        "label": "AI Engine Plugin",
        "value": "Active",
        "status": "ok"
      }
    }
  },
  "id": 1
}
```

### 7. clear_history

Clear generation history records based on age and status.

**Parameters:**
- `older_than_days` (integer, optional): Clear records older than N days. Default: 0 (all)
- `status` (string, optional): Filter by status. Default: "all"
  - `all`: All statuses
  - `completed`: Only completed generations
  - `failed`: Only failed generations
  - `pending`: Only pending generations

**Request:**
```json
{
  "jsonrpc": "2.0",
  "method": "clear_history",
  "params": {
    "older_than_days": 30,
    "status": "failed"
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
    "deleted": 15,
    "message": "Deleted 15 history records"
  },
  "id": 1
}
```

### 8. export_data

Export plugin data in JSON or MySQL format.

**Parameters:**
- `format` (string, required): Export format
  - `json`: JSON format
  - `mysql`: MySQL dump format
- `tables` (array, optional): List of tables to export. Default: all tables

**Request:**
```json
{
  "jsonrpc": "2.0",
  "method": "export_data",
  "params": {
    "format": "json",
    "tables": ["aips_templates", "aips_voices"]
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
    "format": "json",
    "file": "/path/to/export-2024-01-15-123456.json",
    "url": "https://site.com/wp-content/uploads/aips-exports/export-2024-01-15-123456.json",
    "size": 12345,
    "tables": ["aips_templates", "aips_voices"]
  },
  "id": 1
}
```

### 9. get_cron_status

Get status of all scheduled cron jobs.

**Request:**
```json
{
  "jsonrpc": "2.0",
  "method": "get_cron_status",
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
    "crons": {
      "aips_generate_scheduled_posts": {
        "scheduled": true,
        "next_run": "2024-01-15 14:00:00",
        "next_run_timestamp": 1705330800
      },
      ...
    }
  },
  "id": 1
}
```

### 10. trigger_cron

Manually trigger a specific cron job.

**Parameters:**
- `hook` (string, required): Cron hook name
  - `aips_generate_scheduled_posts`
  - `aips_generate_author_topics`
  - `aips_generate_author_posts`
  - `aips_scheduled_research`
  - `aips_send_review_notifications`
  - `aips_cleanup_export_files`

**Request:**
```json
{
  "jsonrpc": "2.0",
  "method": "trigger_cron",
  "params": {
    "hook": "aips_generate_scheduled_posts"
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
    "hook": "aips_generate_scheduled_posts",
    "message": "Cron hook 'aips_generate_scheduled_posts' triggered successfully"
  },
  "id": 1
}
```

### 11. get_plugin_info

Get plugin version, settings, and configuration.

**Request:**
```json
{
  "jsonrpc": "2.0",
  "method": "get_plugin_info",
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
    "plugin": {
      "name": "AI Post Scheduler",
      "version": "1.7.0",
      "db_version": "1.7.0",
      "php_version": "8.2.0",
      "wp_version": "6.4.2",
      "ai_engine_active": true,
      "settings": {
        "default_post_status": "draft",
        "default_category": "1",
        "enable_logging": "1",
        "retry_max_attempts": "3",
        "ai_model": "gpt-4",
        "developer_mode": "0"
      }
    }
  },
  "id": 1
}
```

## Error Responses

When an error occurs, the bridge returns a JSON-RPC error response:

```json
{
  "jsonrpc": "2.0",
  "error": {
    "code": -32001,
    "message": "Insufficient permissions. Admin access required."
  },
  "id": 1
}
```

### Error Codes

- `-32700`: Parse error (invalid JSON)
- `-32600`: Invalid request (missing method)
- `-32001`: Insufficient permissions
- `-32000`: Tool execution error
- Custom error codes for specific tool failures

## Security Considerations

1. **Authentication Required**: All requests must be authenticated with WordPress admin capabilities
2. **HTTPS Recommended**: Use HTTPS in production to protect sensitive data
3. **Rate Limiting**: Consider implementing rate limiting for production use
4. **Input Validation**: All parameters are validated before execution
5. **Audit Logging**: All tool executions are logged for audit purposes

## Integration with GitHub Copilot

To use this bridge with GitHub Copilot or other MCP clients:

1. Configure your MCP client to point to the bridge endpoint
2. Provide authentication credentials (WordPress admin user)
3. Use the `list_tools` method to discover available tools
4. Call tools using JSON-RPC 2.0 format

### Example MCP Configuration

```json
{
  "name": "AI Post Scheduler",
  "endpoint": "https://your-site.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php",
  "authentication": {
    "type": "wordpress_cookie",
    "credentials": {
      "username": "admin",
      "password": "your-application-password"
    }
  }
}
```

## Extending the Bridge

To add new tools to the bridge:

1. Add tool definition to `register_tools()` method
2. Create a handler method (e.g., `tool_your_tool_name`)
3. Implement parameter validation and tool logic
4. Return result array or WP_Error on failure

### Example Tool Implementation

```php
private function register_tools() {
    $this->tools['my_custom_tool'] = array(
        'description' => 'Description of my tool',
        'parameters' => array(
            'param1' => array(
                'type' => 'string',
                'description' => 'Description of param1',
                'required' => true
            )
        ),
        'handler' => array($this, 'tool_my_custom_tool')
    );
}

private function tool_my_custom_tool($params) {
    // Tool implementation
    return array(
        'success' => true,
        'result' => 'Tool result'
    );
}
```

## Troubleshooting

### Bridge Not Accessible

- Verify WordPress is properly installed and configured
- Check file permissions on `mcp-bridge.php`
- Ensure user has admin capabilities

### Authentication Failures

- Verify user credentials
- Check WordPress session cookies are being sent
- Consider using WordPress application passwords

### Tool Execution Errors

- Check plugin logs in `wp-content/uploads/aips-logs/`
- Verify required dependencies (AI Engine plugin)
- Check database connectivity

## Support

For issues, questions, or feature requests:

- Check the plugin logs for detailed error information
- Review the system status using the `system_status` tool
- Contact plugin support with relevant error messages

## Version History

- **1.0.0** (2024-02-10): Initial release
  - 11 core tools
  - JSON-RPC 2.0 protocol support
  - WordPress authentication integration
  - Comprehensive error handling and logging
