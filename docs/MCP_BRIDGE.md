# MCP Bridge

The MCP Bridge (`ai-post-scheduler/mcp-bridge.php`) provides a JSON-RPC 2.0 API that exposes AI Post Scheduler plugin functionality to MCP-compatible tools (GitHub Copilot, custom scripts, CI/CD pipelines, etc.).

**25 tools** across five categories: core operations, content generation, history management, author management, and analytics.

The JSON schema for all tools is at `ai-post-scheduler/mcp-bridge-schema.json`. Test and validation scripts are at `ai-post-scheduler/test-mcp-bridge.php` and `validate-mcp-bridge.php`. Example CLI clients are at `scripts/mcp-client-example.sh` and `scripts/mcp-client-example.py`.

---

## Authentication

All requests require WordPress admin capabilities (`manage_options`). Supported methods:

- **Application Passwords** (recommended): generate at WordPress Dashboard → Users → Profile → Application Passwords. Format: `xxxx xxxx xxxx xxxx xxxx xxxx`.
- **Session cookies**: send WordPress session cookies with the request.
- **Basic auth** (development only): WordPress username + password over HTTPS.

---

## Quick usage

```bash
# List all tools
curl -X POST https://your-site.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php \
  -u "admin:xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"list_tools","params":{},"id":1}'

# Or run the local test suite
cd ai-post-scheduler && php test-mcp-bridge.php
```

Shell and Python clients in `scripts/` accept `--tool` and `--params` flags for all methods.

---

## VSCode / GitHub Copilot setup

Create `.vscode/settings.json` in your project (or user settings for global):

```json
{
  "mcp.servers": {
    "aips": {
      "name": "AI Post Scheduler",
      "url": "${env:MCP_BRIDGE_URL}",
      "auth": {
        "type": "basic",
        "username": "${env:WP_USERNAME}",
        "password": "${env:WP_APP_PASSWORD}"
      },
      "timeout": 60000
    }
  }
}
```

Store credentials in a `.env` file (gitignored):

```env
WP_USERNAME=admin
WP_APP_PASSWORD=xxxx xxxx xxxx xxxx xxxx xxxx
MCP_BRIDGE_URL=https://your-site.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php
```

Test with: `@mcp list_tools from aips` in Copilot chat. Expected: 25 tools.

---

## Available tools

### Core (11 tools)

| Tool | Description |
|------|-------------|
| `list_tools` | List all tools with parameters |
| `get_plugin_info` | Plugin version, settings, AI Engine status |
| `clear_cache` | Clear transients; `cache_type`: `all` (default), `history_stats`, `schedule_stats`, or a transient name |
| `check_database` | Verify all tables and columns exist |
| `repair_database` | Re-run dbDelta to repair tables |
| `check_upgrades` | Check for pending DB upgrades; `run: true` to apply them |
| `system_status` | Full diagnostics; `section`: `all`, `environment`, `plugin`, `database`, `filesystem`, `cron`, `logs` |
| `clear_history` | Delete history records; `older_than_days`, `status`: `all`, `completed`, `failed`, `pending` |
| `export_data` | Export tables to JSON or MySQL dump; `format`: `json`/`mysql`, `tables`: array |
| `get_cron_status` | Status and next-run time for all scheduled cron hooks |
| `trigger_cron` | Manually fire a cron hook; `hook`: any `aips_*` hook name |

### Content Generation (3 tools)

| Tool | Description |
|------|-------------|
| `generate_post` | Generate a post from a template; `template_id`, optional `overrides` |
| `list_templates` | List templates; `active_only: true` to filter |
| `get_generation_history` | Retrieve generation history records with filters |

### History Management (1 tool)

| Tool | Description |
|------|-------------|
| `get_history` | Detailed history record by ID |

### Author Management (5 tools)

| Tool | Description |
|------|-------------|
| `list_authors` | List all authors |
| `get_author` | Author details by ID |
| `list_author_topics` | Topics for an author; `author_id`, `status` filter |
| `get_author_topic` | Single topic by ID |
| `regenerate_post_component` | Regenerate one component; `post_id`, `history_id`, `component`, `save: false` for preview |

### Analytics & Testing (5 tools)

| Tool | Description |
|------|-------------|
| `get_generation_stats` | Success rates, counts; `period`: `day`, `week`, `month` |
| `get_post_metadata` | AI metadata for a generated post |
| `get_ai_models` | Available AI models from AI Engine |
| `test_ai_connection` | Verify AI Engine is configured and reachable |
| `get_plugin_settings` | Read current plugin settings |

---

## Protocol

All requests follow JSON-RPC 2.0:

```json
{ "jsonrpc": "2.0", "method": "tool_name", "params": {}, "id": 1 }
```

Error responses:

```json
{ "jsonrpc": "2.0", "error": { "code": -32001, "message": "Insufficient permissions." }, "id": 1 }
```

Error codes: `-32700` parse error, `-32600` invalid request, `-32001` insufficient permissions, `-32000` tool execution error.

---

## Extending the bridge

1. Add a tool definition to `register_tools()` in `mcp-bridge.php`.
2. Create a handler method named `tool_<name>`.
3. Return a result array or `WP_Error` on failure.

---

## Troubleshooting

- **401 / auth failure**: use Application Password (not main password); username must be WordPress login name, not email.
- **403 / permissions**: user must have administrator role (`manage_options`).
- **Bridge not accessible**: check file permissions on `mcp-bridge.php` and that the plugin is active.
- **Timeout on `generate_post`**: expected for long-running AI calls; increase `timeout` in MCP client config.
- **Debug logs**: `wp-content/debug.log` (requires `WP_DEBUG_LOG true`) and plugin logs under `wp-content/uploads/aips-logs/`.
