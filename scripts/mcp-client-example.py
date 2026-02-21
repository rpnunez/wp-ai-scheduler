#!/usr/bin/env python3
"""
MCP Bridge Client Example

This script demonstrates how to interact with the AI Post Scheduler MCP Bridge
using HTTP requests. It can be used as a template for building custom MCP clients.

Requirements:
    pip install requests

Usage:
    python mcp-client-example.py --url https://your-site.com/wp-content/plugins/ai-post-scheduler/mcp-bridge.php
"""

import argparse
import json
import requests
from typing import Dict, Any, Optional


class MCPClient:
    """Simple MCP Bridge client"""
    
    def __init__(self, url: str, username: str = None, password: str = None):
        """
        Initialize MCP client
        
        Args:
            url: URL to the MCP bridge endpoint
            username: WordPress username (optional)
            password: WordPress application password (optional)
        """
        self.url = url
        self.session = requests.Session()
        
        if username and password:
            self.session.auth = (username, password)
    
    def call_tool(self, method: str, params: Optional[Dict[str, Any]] = None, request_id: int = 1) -> Dict[str, Any]:
        """
        Call an MCP tool
        
        Args:
            method: Tool name to call
            params: Tool parameters (optional)
            request_id: JSON-RPC request ID
            
        Returns:
            Tool result or error
        """
        payload = {
            "jsonrpc": "2.0",
            "method": method,
            "params": params or {},
            "id": request_id
        }
        
        try:
            response = self.session.post(
                self.url,
                json=payload,
                headers={"Content-Type": "application/json"},
                timeout=30
            )
            
            response.raise_for_status()
            return response.json()
            
        except requests.exceptions.RequestException as e:
            return {
                "error": {
                    "code": -32000,
                    "message": f"HTTP request failed: {str(e)}"
                }
            }
    
    def print_response(self, response: Dict[str, Any]):
        """Pretty print response"""
        if "error" in response:
            print("❌ Error:")
            print(f"   Code: {response['error']['code']}")
            print(f"   Message: {response['error']['message']}")
        elif "result" in response:
            print("✅ Success:")
            print(json.dumps(response["result"], indent=2))
        else:
            print("⚠️ Unknown response format:")
            print(json.dumps(response, indent=2))


def main():
    parser = argparse.ArgumentParser(description="MCP Bridge Client Example")
    parser.add_argument("--url", required=True, help="MCP Bridge URL")
    parser.add_argument("--username", help="WordPress username")
    parser.add_argument("--password", help="WordPress application password")
    parser.add_argument("--tool", default="list_tools", help="Tool to call (default: list_tools)")
    parser.add_argument("--params", help="Tool parameters as JSON string")
    
    args = parser.parse_args()
    
    # Parse parameters
    params = {}
    if args.params:
        try:
            params = json.loads(args.params)
        except json.JSONDecodeError as e:
            print(f"Error parsing parameters: {e}")
            return 1
    
    # Create client
    client = MCPClient(args.url, args.username, args.password)
    
    print(f"🔧 Calling tool: {args.tool}")
    print(f"📝 Parameters: {json.dumps(params)}")
    print("-" * 50)
    
    # Call tool
    response = client.call_tool(args.tool, params)
    
    # Print response
    client.print_response(response)
    
    return 0


if __name__ == "__main__":
    exit(main())
