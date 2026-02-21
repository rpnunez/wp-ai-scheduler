# VSCode MCP Bridge Configuration Guide

This guide explains how to configure Visual Studio Code to use the AI Post Scheduler MCP Bridge with GitHub Copilot and other MCP-compatible tools.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [VSCode Extension Setup](#vscode-extension-setup)
3. [Configuration Methods](#configuration-methods)
4. [Authentication Setup](#authentication-setup)
5. [Testing the Connection](#testing-the-connection)
6. [Usage Examples](#usage-examples)
7. [Troubleshooting](#troubleshooting)

## Prerequisites

Before configuring VSCode, ensure you have:

1. **WordPress Site Running**: Your WordPress site with AI Post Scheduler plugin installed and active
2. **Admin Access**: WordPress administrator credentials
3. **HTTPS Recommended**: SSL certificate for secure communication (optional but recommended)
4. **MCP Bridge Accessible**: The bridge is accessible at `https://your-site.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php`

## VSCode Extension Setup

### Option 1: GitHub Copilot with MCP Support

1. **Install GitHub Copilot**:
   - Open VSCode Extensions (Ctrl+Shift+X / Cmd+Shift+X)
   - Search for "GitHub Copilot"
   - Install the extension
   - Sign in with your GitHub account

2. **Enable MCP Features**:
   - Open Settings (File → Preferences → Settings)
   - Search for "copilot mcp"
   - Enable MCP protocol support (if available)

### Option 2: MCP Client Extension

If there's a dedicated MCP client extension:

1. Search for "MCP" or "Model Context Protocol" in VSCode Extensions
2. Install the MCP client extension
3. Follow the extension's configuration instructions

## Configuration Methods

### Method 1: Workspace Configuration (Recommended)

Create or edit `.vscode/settings.json` in your project root:

```json
{
  "mcp.servers": {
    "aips": {
      "name": "AI Post Scheduler",
      "url": "https://your-site.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php",
      "auth": {
        "type": "basic",
        "username": "your-wp-username",
        "password": "your-application-password"
      },
      "description": "AI Post Scheduler MCP Bridge for content generation"
    }
  }
}
```

### Method 2: User Settings (Global)

Edit global VSCode settings (File → Preferences → Settings → User):

```json
{
  "mcp.servers": {
    "aips-production": {
      "name": "AI Post Scheduler (Production)",
      "url": "https://your-production-site.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php",
      "auth": {
        "type": "basic",
        "username": "${env:WP_USERNAME}",
        "password": "${env:WP_APP_PASSWORD}"
      }
    },
    "aips-staging": {
      "name": "AI Post Scheduler (Staging)",
      "url": "https://your-staging-site.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php",
      "auth": {
        "type": "basic",
        "username": "${env:WP_STAGING_USERNAME}",
        "password": "${env:WP_STAGING_APP_PASSWORD}"
      }
    }
  }
}
```

### Method 3: Environment Variables (Most Secure)

1. **Create `.env` file** in your project root:

```env
WP_USERNAME=your-wp-username
WP_APP_PASSWORD=xxxx xxxx xxxx xxxx xxxx xxxx
MCP_BRIDGE_URL=https://your-site.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php
```

2. **Reference in `.vscode/settings.json`**:

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
      }
    }
  }
}
```

3. **Add to `.gitignore`**:

```
.env
```

## Authentication Setup

### WordPress Application Passwords (Recommended)

WordPress Application Passwords provide secure, revokable access without exposing your main password.

1. **Create Application Password**:
   - Log in to WordPress admin
   - Go to Users → Profile
   - Scroll to "Application Passwords" section
   - Enter name: "VSCode MCP Bridge"
   - Click "Add New Application Password"
   - Copy the generated password (format: `xxxx xxxx xxxx xxxx xxxx xxxx`)

2. **Use in Configuration**:
   - Use your WordPress username
   - Use the application password (keep spaces or remove them)

### Basic Authentication Plugin (Alternative)

If Application Passwords aren't available:

1. Install a Basic Auth plugin (e.g., "Application Passwords" backport)
2. Use your WordPress credentials
3. ⚠️ **Security Note**: Only use over HTTPS

## Testing the Connection

### Test 1: List Available Tools

Use the VSCode command palette or Copilot chat:

```
@mcp list_tools from aips
```

Expected response should include 25 tools.

### Test 2: Get Plugin Info

```
@mcp get_plugin_info from aips
```

Should return plugin version and configuration.

### Test 3: Test AI Connection

```
@mcp test_ai_connection from aips
```

Verifies the AI Engine is configured correctly.

### Test 4: Generate Content (Safe Test)

```
@mcp list_templates from aips with active_only=true
```

Lists your active templates without making any changes.

## Usage Examples

### Example 1: Generate a Post

```
@mcp Can you generate a post using template ID 1 with these overrides:
- Post status: draft
- Categories: [5, 7]
```

This translates to:
```json
{
  "method": "generate_post",
  "params": {
    "template_id": 1,
    "overrides": {
      "post_status": "draft",
      "category_ids": [5, 7]
    }
  }
}
```

### Example 2: Check Generation Stats

```
@mcp Show me generation statistics for this week
```

Translates to:
```json
{
  "method": "get_generation_stats",
  "params": {
    "period": "week"
  }
}
```

### Example 3: List Author Topics

```
@mcp List approved topics for author ID 3
```

Translates to:
```json
{
  "method": "list_author_topics",
  "params": {
    "author_id": 3,
    "status": "approved"
  }
}
```

### Example 4: Regenerate Post Component

```
@mcp Regenerate the title for post 456 (history 123) in preview mode
```

Translates to:
```json
{
  "method": "regenerate_post_component",
  "params": {
    "post_id": 456,
    "history_id": 123,
    "component": "title",
    "save": false
  }
}
```

## Advanced Configuration

### Custom Headers

If your WordPress setup requires custom headers:

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
      },
      "headers": {
        "X-Custom-Header": "value",
        "User-Agent": "VSCode-MCP-Client/1.0"
      }
    }
  }
}
```

### Timeout Configuration

For long-running operations:

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
      },
      "timeout": 60000,
      "retries": 3
    }
  }
}
```

### Multiple Environments

Manage dev, staging, and production environments:

```json
{
  "mcp.servers": {
    "aips-dev": {
      "name": "AIPS Dev",
      "url": "http://localhost:8080/wp-content/plugins/ai-post-scheduler/mcp-bridge.php",
      "auth": {
        "type": "basic",
        "username": "admin",
        "password": "admin"
      }
    },
    "aips-staging": {
      "name": "AIPS Staging",
      "url": "https://staging.example.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php",
      "auth": {
        "type": "basic",
        "username": "${env:WP_STAGING_USERNAME}",
        "password": "${env:WP_STAGING_PASSWORD}"
      }
    },
    "aips-prod": {
      "name": "AIPS Production",
      "url": "https://example.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php",
      "auth": {
        "type": "basic",
        "username": "${env:WP_PROD_USERNAME}",
        "password": "${env:WP_PROD_PASSWORD}"
      }
    }
  }
}
```

## Troubleshooting

### Connection Issues

**Problem**: "Failed to connect to MCP server"

**Solutions**:
1. Verify the URL is correct and accessible
2. Check that the plugin is active
3. Verify WordPress credentials
4. Check for firewall/WAF blocking
5. Try accessing the URL directly in a browser with Basic Auth

**Test with curl**:
```bash
curl -v -X POST https://your-site.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php \
  -u "username:password" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"list_tools","params":{},"id":1}'
```

### Authentication Errors

**Problem**: "Authentication failed" or 401 Unauthorized

**Solutions**:
1. **Application Password**: Ensure you're using an Application Password, not your main password
2. **Format**: Try with and without spaces in the Application Password
3. **Username**: Use WordPress username, not email
4. **Permissions**: Ensure user has `manage_options` capability (administrator role)

### Permission Errors

**Problem**: "You do not have sufficient permissions"

**Solutions**:
1. User must have administrator role
2. Check WordPress user capabilities
3. Verify the MCP Bridge capability check isn't being filtered by a plugin

### SSL/HTTPS Issues

**Problem**: "SSL certificate verification failed"

**Solutions**:
1. Use valid SSL certificate (Let's Encrypt is free)
2. For development only: Some MCP clients may allow SSL verification bypass
3. Use `http://` for local development (not recommended for production)

### Tool Not Found

**Problem**: "Method not found" or "Unknown tool"

**Solutions**:
1. Run `list_tools` to see available tools
2. Check tool name spelling
3. Verify plugin version (some tools require newer versions)

### Timeout Issues

**Problem**: Requests timing out

**Solutions**:
1. Increase timeout in MCP configuration
2. Check server response times
3. For `generate_post`, this is expected for long-running AI operations
4. Consider using async operations if supported

### Debugging

Enable verbose logging:

1. **In WordPress**:
   ```php
   // In wp-config.php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```

2. **Check logs**:
   - WordPress debug log: `wp-content/debug.log`
   - AIPS logs: Check plugin's logging configuration

3. **VSCode Developer Tools**:
   - Help → Toggle Developer Tools
   - Check Console for MCP-related errors

## Best Practices

### Security

1. ✅ **Always use HTTPS** in production
2. ✅ **Use Application Passwords** instead of main password
3. ✅ **Use environment variables** for credentials
4. ✅ **Add `.env` to `.gitignore`**
5. ✅ **Rotate Application Passwords** regularly
6. ✅ **Revoke unused** Application Passwords

### Performance

1. ✅ **Use pagination** for large result sets
2. ✅ **Cache results** when appropriate
3. ✅ **Use filters** to limit data retrieval
4. ✅ **Consider timeouts** for long operations

### Development

1. ✅ **Test in staging** before production
2. ✅ **Use different credentials** per environment
3. ✅ **Monitor logs** for errors
4. ✅ **Document custom workflows**

## Complete Working Example

### Project Structure

```
my-wp-project/
├── .env                          # Credentials (gitignored)
├── .vscode/
│   └── settings.json            # MCP configuration
└── README.md
```

### `.env`

```env
WP_USERNAME=admin
WP_APP_PASSWORD=xxxx xxxx xxxx xxxx xxxx xxxx
MCP_BRIDGE_URL=https://mysite.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php
```

### `.vscode/settings.json`

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
      "timeout": 60000,
      "description": "WordPress AI Post Scheduler MCP Bridge"
    }
  }
}
```

### `.gitignore`

```
.env
.vscode/settings.local.json
```

## Available Tools Reference

The MCP Bridge provides 25 tools across multiple categories:

### Core Tools (11)
- list_tools
- get_plugin_info
- clear_cache
- check_database
- repair_database
- check_upgrades
- system_status
- clear_history
- export_data
- get_cron_status
- trigger_cron

### Content Generation (3)
- generate_post
- list_templates
- get_generation_history

### History Management (1)
- get_history

### Author Management (5)
- list_authors
- get_author
- list_author_topics
- get_author_topic
- regenerate_post_component

### Analytics & Testing (5)
- get_generation_stats
- get_post_metadata
- get_ai_models
- test_ai_connection
- get_plugin_settings

For detailed tool documentation, see:
- `MCP_BRIDGE_README.md` - Core tools
- `MCP_BRIDGE_CONTENT_TOOLS.md` - Content generation
- `MCP_BRIDGE_PHASE2_TOOLS.md` - History and authors
- `MCP_BRIDGE_PHASE3_TOOLS.md` - Analytics and testing

## Additional Resources

- [MCP Bridge README](./MCP_BRIDGE_README.md)
- [MCP Bridge Quick Start](./MCP_BRIDGE_QUICKSTART.md)
- [Content Tools Documentation](./MCP_BRIDGE_CONTENT_TOOLS.md)
- [Phase 2 Tools Documentation](./MCP_BRIDGE_PHASE2_TOOLS.md)
- [Phase 3 Tools Documentation](./MCP_BRIDGE_PHASE3_TOOLS.md)
- [JSON Schema](./mcp-bridge-schema.json)

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Review the tool-specific documentation
3. Check WordPress debug logs
4. Open an issue on GitHub

## Updates

This configuration guide is for MCP Bridge version 1.3.0 which includes all 25 tools. Check the CHANGELOG for updates.
