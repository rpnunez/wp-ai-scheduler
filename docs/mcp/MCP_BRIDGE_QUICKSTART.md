# MCP Bridge Quick Start Guide

This guide helps you get started with the AI Post Scheduler MCP Bridge quickly.

## What is the MCP Bridge?

The MCP (Model Context Protocol) Bridge is a JSON-RPC 2.0 API that exposes AI Post Scheduler plugin functionality to external tools, automation systems, and AI assistants like GitHub Copilot.

## Quick Test (No Setup Required)

1. **List Available Tools**

```bash
cd ai-post-scheduler
php test-mcp-bridge.php
```

This runs a comprehensive test suite showing all available tools.

## Using the Bridge via HTTP

### Prerequisites

- WordPress site with AI Post Scheduler installed
- Admin user credentials
- `curl` or HTTP client

### Example 1: List All Tools

```bash
curl -X POST https://your-site.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php \
  -u admin:your-password \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "method": "list_tools",
    "params": {},
    "id": 1
  }'
```

### Example 2: Get Plugin Information

```bash
curl -X POST https://your-site.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php \
  -u admin:your-password \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "method": "get_plugin_info",
    "params": {},
    "id": 1
  }'
```

### Example 3: Clear All Caches

```bash
curl -X POST https://your-site.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php \
  -u admin:your-password \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "method": "clear_cache",
    "params": {"cache_type": "all"},
    "id": 1
  }'
```

### Example 4: Check Database Health

```bash
curl -X POST https://your-site.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php \
  -u admin:your-password \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "method": "check_database",
    "params": {},
    "id": 1
  }'
```

## Using Shell Script Client

We provide a ready-to-use shell script:

```bash
# Set your credentials
export MCP_URL="https://your-site.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php"
export WP_USERNAME="admin"
export WP_PASSWORD="your-password"

# List tools
./mcp-client-example.sh list_tools

# Clear cache
./mcp-client-example.sh clear_cache '{"cache_type":"all"}'

# Get plugin info
./mcp-client-example.sh get_plugin_info
```

## Using Python Client

Install dependencies:

```bash
pip install requests
```

Run the client:

```bash
# List tools
python mcp-client-example.py \
  --url https://your-site.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php \
  --username admin \
  --password your-password \
  --tool list_tools

# Clear cache
python mcp-client-example.py \
  --url https://your-site.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php \
  --username admin \
  --password your-password \
  --tool clear_cache \
  --params '{"cache_type":"all"}'
```

## Common Use Cases

### 1. Automated Cache Clearing

Clear caches after deployments:

```bash
#!/bin/bash
# deploy-hooks.sh
curl -X POST $MCP_URL -u admin:$PASSWORD \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"clear_cache","params":{"cache_type":"all"},"id":1}'
```

### 2. Database Health Monitoring

Check database health in monitoring scripts:

```bash
#!/bin/bash
# health-check.sh
curl -X POST $MCP_URL -u admin:$PASSWORD \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"check_database","params":{},"id":1}' \
  | jq '.result.database'
```

### 3. System Status Dashboard

Get comprehensive system status:

```bash
curl -X POST $MCP_URL -u admin:$PASSWORD \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"system_status","params":{"section":"all"},"id":1}' \
  | jq '.result.system_info'
```

### 4. Automated Exports

Schedule regular data exports:

```bash
#!/bin/bash
# backup-cron.sh
curl -X POST $MCP_URL -u admin:$PASSWORD \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"export_data","params":{"format":"json","tables":[]},"id":1}' \
  | jq '.result.url'
```

### 5. Manual Cron Trigger

Trigger cron jobs manually:

```bash
curl -X POST $MCP_URL -u admin:$PASSWORD \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"trigger_cron","params":{"hook":"aips_generate_scheduled_posts"},"id":1}'
```

## Available Tools Summary

| Tool | Description | Common Use |
|------|-------------|------------|
| `list_tools` | List all available tools | Discovery, documentation |
| `get_plugin_info` | Get plugin version and settings | Monitoring, debugging |
| `clear_cache` | Clear plugin caches | After updates, troubleshooting |
| `check_database` | Verify database health | Health checks, monitoring |
| `repair_database` | Repair database tables | Maintenance, recovery |
| `check_upgrades` | Check/run database upgrades | Deployment automation |
| `system_status` | Get system diagnostics | Status dashboard, alerts |
| `clear_history` | Clear generation history | Data cleanup, maintenance |
| `export_data` | Export plugin data | Backups, migrations |
| `get_cron_status` | Check cron job status | Monitoring, debugging |
| `trigger_cron` | Manually trigger cron | Testing, manual runs |

## Authentication Methods

### 1. Basic Auth (Development)

```bash
curl -u username:password ...
```

### 2. WordPress Application Password (Production)

Generate in WordPress Dashboard → Users → Profile → Application Passwords

```bash
curl -u username:xxxx-xxxx-xxxx-xxxx ...
```

### 3. Session Cookies (Browser)

Send WordPress session cookies with requests.

## Troubleshooting

### Error: Insufficient permissions

**Solution**: Ensure user has `manage_options` capability (admin user).

### Error: WordPress environment not available

**Solution**: Verify WordPress is installed and the path in `mcp-bridge.php` is correct.

### Error: Tool execution failed

**Solution**: Check plugin logs in `wp-content/uploads/aips-logs/` for details.

### Connection refused

**Solution**: 
- Verify URL is correct
- Check if WordPress site is accessible
- Verify plugin is installed and activated

## Integration with GitHub Copilot

To use with GitHub Copilot:

1. Configure MCP client to point to your bridge endpoint
2. Provide authentication credentials
3. Use `list_tools` to discover available operations
4. Call tools using JSON-RPC format

## Security Best Practices

1. **Use HTTPS** in production
2. **Use Application Passwords** instead of account passwords
3. **Implement rate limiting** for production use
4. **Monitor logs** for suspicious activity
5. **Restrict access** to admin users only
6. **Keep credentials secure** - never commit to version control

## Next Steps

- Read the full documentation: `MCP_BRIDGE_README.md`
- Review the JSON schema: `mcp-bridge-schema.json`
- Run comprehensive tests: `php test-mcp-bridge.php`
- Validate installation: `php validate-mcp-bridge.php`
- Explore example clients: `mcp-client-example.py`, `mcp-client-example.sh`

## Support

For issues or questions:

1. Check logs: `wp-content/uploads/aips-logs/`
2. Run diagnostics: `system_status` tool
3. Review documentation in this repository
4. Open an issue on GitHub

## Version

MCP Bridge v1.0.0 - 2026-02-10
