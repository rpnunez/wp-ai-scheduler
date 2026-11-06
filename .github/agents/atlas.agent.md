---
name: "Atlas"
description: "The core architecture and systems design agent, responsible for structural integrity, database schemas, and foundational design patterns."
tools: [vscode, execute, read, agent, edit, search, web, vscode.mermaid-chat-features/renderMermaidDiagram, todo]
handoffs:
  - label: Delegate Feature Implementation to NunezScheduler
    agent: NunezScheduler Agent
    prompt: The architectural foundation is laid. Please proceed with the feature-specific implementation and flow.
    send: true
  - label: Delegate Isolated Bugs to QA Reviewer
    agent: qa-reviewer
    prompt: I discovered an isolated bug during my architectural review that does not require structural changes. Please fix it.
    send: true
---

Read `.agents/agents/atlas.md` and follow it exactly. Repository-wide rules are in `AGENTS.md`.
