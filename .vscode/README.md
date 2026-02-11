# VSCode MCP Configuration

This directory contains example configuration files for connecting VSCode to the AI Post Scheduler MCP Bridge.

## Quick Start

1. **Copy example files**:
   ```bash
   cp .env.example .env
   cp .vscode/settings.json.example .vscode/settings.json
   ```

2. **Edit `.env`** with your WordPress credentials:
   - Get your WordPress username
   - Generate an Application Password (Users → Profile → Application Passwords)
   - Update the `.env` file

3. **Verify VSCode configuration**:
   - Open VSCode settings
   - Ensure MCP extension is installed
   - Check that settings.json references the `.env` variables

4. **Test the connection**:
   ```
   @mcp list_tools from aips
   ```

## Files

- **settings.json.example** - Example VSCode MCP configuration
- **../.env.example** - Example environment variables file

## Security

⚠️ **Important**: Never commit credentials to version control!

Add to your `.gitignore`:
```
.env
.vscode/settings.json
```

The example files are safe to commit as they contain placeholders.

## Documentation

For detailed setup instructions, see:
- [MCP_BRIDGE_VSCODE_SETUP.md](../ai-post-scheduler/MCP_BRIDGE_VSCODE_SETUP.md) - Complete VSCode setup guide
- [MCP_BRIDGE_README.md](../ai-post-scheduler/MCP_BRIDGE_README.md) - MCP Bridge documentation
- [MCP_BRIDGE_QUICKSTART.md](../ai-post-scheduler/MCP_BRIDGE_QUICKSTART.md) - Quick start guide

## Troubleshooting

See the [Troubleshooting section](../ai-post-scheduler/MCP_BRIDGE_VSCODE_SETUP.md#troubleshooting) in the VSCode setup guide.

## Available Tools (25)

The MCP Bridge provides 25 tools organized in 5 categories:

- **Core** (11): System management, cache, database, cron
- **Content** (3): Generate posts, list templates, view history
- **History** (1): Get detailed history records
- **Authors** (5): Manage authors and topics
- **Analytics** (5): Stats, metadata, AI models, testing

Use `list_tools` to see all available tools.
