#!/bin/bash
#
# MCP Bridge Shell Client Example
#
# Simple bash script to interact with the MCP Bridge using curl
#
# Usage:
#   ./mcp-client-example.sh list_tools
#   ./mcp-client-example.sh clear_cache '{"cache_type":"all"}'
#   ./mcp-client-example.sh get_plugin_info

# Configuration
MCP_URL="${MCP_URL:-http://localhost/wp-content/plugins/ai-post-scheduler/mcp-bridge.php}"
WP_USERNAME="${WP_USERNAME:-admin}"
WP_PASSWORD="${WP_PASSWORD:-}"
# Must match the AIPS_MCP_BRIDGE_TOKEN constant defined in wp-config.php.
MCP_BRIDGE_TOKEN="${MCP_BRIDGE_TOKEN:-}"

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Get tool name from first argument
TOOL="${1:-list_tools}"

# Get parameters from second argument (default to empty object)
PARAMS="${2:-{}}"

# Build JSON-RPC request
REQUEST=$(cat <<EOF
{
  "jsonrpc": "2.0",
  "method": "$TOOL",
  "params": $PARAMS,
  "id": 1,
  "token": "$MCP_BRIDGE_TOKEN"
}
EOF
)

echo -e "${YELLOW}🔧 Calling MCP Bridge Tool${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "URL: $MCP_URL"
echo "Tool: $TOOL"
echo "Parameters: $PARAMS"
echo ""

# Make request
RESPONSE=$(curl -s -X POST "$MCP_URL" \
  -H "Content-Type: application/json" \
  -d "$REQUEST" \
  ${WP_USERNAME:+-u "$WP_USERNAME:$WP_PASSWORD"})

# Check if response contains error
if echo "$RESPONSE" | jq -e '.error' > /dev/null 2>&1; then
  echo -e "${RED}❌ Error Response:${NC}"
  echo "$RESPONSE" | jq '.error'
  exit 1
elif echo "$RESPONSE" | jq -e '.result' > /dev/null 2>&1; then
  echo -e "${GREEN}✅ Success Response:${NC}"
  echo "$RESPONSE" | jq '.result'
  exit 0
else
  echo -e "${RED}⚠️  Unexpected Response:${NC}"
  echo "$RESPONSE"
  exit 1
fi
