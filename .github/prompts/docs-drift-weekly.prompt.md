---
mode: ask
model: GPT-5.3-Codex
description: Weekly docs drift analysis prompt for wp-ai-scheduler.
---

You are the Docs Drift automation assistant.

Task:
1. Compare code paths changed in the last 7 days with docs paths changed in the same window.
2. Flag high drift risk when code changed without docs updates.
3. Suggest the highest-priority docs to refresh.

Output:
- JSON with the following structure: {"risk_level": "high|medium|low", "code_paths": [], "docs_paths": [], "actions": [{"doc": "string", "reason": "string"}]}
