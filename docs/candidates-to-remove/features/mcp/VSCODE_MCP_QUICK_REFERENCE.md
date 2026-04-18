# VSCode MCP Bridge - Quick Reference Card

## 🚀 Quick Setup (3 Steps)

### Step 1: Copy Files
```bash
cp .env.example .env
cp .vscode/settings.json.example .vscode/settings.json
```

### Step 2: Get WordPress Credentials
1. Go to WordPress Admin → Users → Your Profile
2. Scroll to "Application Passwords"
3. Name: "VSCode MCP Bridge"
4. Click "Add New Application Password"
5. Copy the password: `xxxx xxxx xxxx xxxx xxxx xxxx`

### Step 3: Edit `.env`
```env
WP_USERNAME=your-admin-username
WP_APP_PASSWORD=xxxx xxxx xxxx xxxx xxxx xxxx
MCP_BRIDGE_URL=https://your-site.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php
```

## ✅ Test Connection
In VSCode:
```
@mcp list_tools from aips
```

Should return 25 tools.

## 📚 Essential Commands

### List Resources
```
@mcp list_tools from aips
@mcp list_templates from aips with active_only=true
@mcp list_authors from aips
```

### Generate Content
```
@mcp generate a post using template 1 with draft status
@mcp generate a post from author topic 5
@mcp generate post from template 2 with categories [5,7] as draft
```

### View Statistics & History
```
@mcp show me generation statistics for this week
@mcp get generation history for the last 10 posts
@mcp show me stats for template 1
```

### Get Information
```
@mcp get plugin info from aips
@mcp get post metadata for post 456
@mcp test AI connection
```

### Manage Content
```
@mcp get history for post 456
@mcp list topics for author 1 with status approved
@mcp regenerate title for post 456 history 123 in preview mode
```

## 🔧 All 25 Tools

### Core (11)
- `list_tools` - List all available tools
- `get_plugin_info` - Get version and settings
- `clear_cache` - Clear transients
- `check_database` - Verify database health
- `repair_database` - Repair/install tables
- `check_upgrades` - Check/run migrations
- `system_status` - Get diagnostics
- `clear_history` - Clear generation records
- `export_data` - Export to JSON/MySQL
- `get_cron_status` - Check scheduled jobs
- `trigger_cron` - Trigger cron hooks

### Content (3)
- `generate_post` - Generate post now
- `list_templates` - Get templates
- `get_generation_history` - Get past generations

### History (1)
- `get_history` - Get history by ID or post_id

### Authors (5)
- `list_authors` - Get all authors
- `get_author` - Get author details
- `list_author_topics` - Get author topics
- `get_author_topic` - Get topic details
- `regenerate_post_component` - Regenerate components

### Analytics (5)
- `get_generation_stats` - Success rates & metrics
- `get_post_metadata` - AI generation details
- `get_ai_models` - List available models
- `test_ai_connection` - Verify AI Engine
- `get_plugin_settings` - Get configuration

## 📖 Documentation

- **[VSCode Setup](./ai-post-scheduler/MCP_BRIDGE_VSCODE_SETUP.md)** - Complete guide (13.5 KB)
- **[Integration Guide](./ai-post-scheduler/MCP_BRIDGE_INTEGRATION.md)** - All platforms (16 KB)
- **[Quick Start](./ai-post-scheduler/MCP_BRIDGE_QUICKSTART.md)** - Get started fast
- **[Main Docs](./ai-post-scheduler/MCP_BRIDGE_README.md)** - Full reference

## 🔒 Security Checklist

✅ Use Application Passwords (not main password)
✅ Store credentials in `.env` file
✅ Add `.env` to `.gitignore`
✅ Use HTTPS in production
✅ Rotate passwords regularly
✅ Different credentials per environment

## ⚠️ Common Issues

**"Authentication failed"**
→ Use Application Password, not main password
→ Check username is correct (not email)

**"Connection refused"**
→ Verify URL in `.env` is correct
→ Check plugin is active in WordPress
→ Test URL in browser first

**"Tool not found"**
→ Run `list_tools` to see available tools
→ Check spelling of tool name
→ Verify plugin version

**"Timeout"**
→ Increase timeout in `.vscode/settings.json`
→ Expected for `generate_post` (AI operations take time)

## 🆘 Get Help

1. Check [Troubleshooting Guide](./ai-post-scheduler/MCP_BRIDGE_VSCODE_SETUP.md#troubleshooting)
2. Test with curl:
   ```bash
   curl -X POST $MCP_BRIDGE_URL -u "user:pass" \
     -H "Content-Type: application/json" \
     -d '{"jsonrpc":"2.0","method":"list_tools","params":{},"id":1}'
   ```
3. Check WordPress debug.log: `wp-content/debug.log`
4. Enable WP_DEBUG in `wp-config.php`

## 💡 Pro Tips

- Start with read-only tools (`list_templates`, `get_generation_history`)
- Use `save=false` for preview mode in `regenerate_post_component`
- Set `post_status=draft` when testing `generate_post`
- Use filters to limit results (`active_only`, `status`, `per_page`)
- Check `success_rate` in `get_generation_stats` to monitor health

## 🎯 Example Workflows

**Daily Content Generation:**
```
@mcp list active templates
@mcp generate post from template 1 as draft
@mcp get generation history last 5
```

**Performance Monitoring:**
```
@mcp show stats for this week
@mcp test AI connection
@mcp check system status
```

**Topic Management:**
```
@mcp list approved topics for author 1
@mcp generate post from topic 5
@mcp get history for the generated post
```

---

**Need more details?** See [MCP_BRIDGE_VSCODE_SETUP.md](./ai-post-scheduler/MCP_BRIDGE_VSCODE_SETUP.md) for complete documentation.
