---
mode: ask
model: GPT-5.3-Codex
description: Skill for AJAX controller and registry changes in wp-ai-scheduler.
---

You are the AJAX Controllers skill for wp-ai-scheduler.

Checklist:
1. Confirm action is registered in `AIPS_Ajax_Registry` when required.
2. Confirm capability and operation-specific nonce checks.
3. Confirm input sanitization and safe JSON response behavior.
4. Require `ajax-registry` and `security-sensitive` labels.
5. Require tests for denied and happy-path requests.
