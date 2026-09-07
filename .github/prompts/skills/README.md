# Copilot skill prompts (`.github/prompts/skills`)

These files are **manual prompt assets** for agent and reviewer workflows.  
They are plain Markdown prompt templates (`*.prompt.md`) and are **not runtime plugin code**.

## IDE/editor support

These prompts are intended for environments where **GitHub Copilot Chat** can open and use repository prompt files, including:

- VS Code
- Visual Studio
- JetBrains IDEs (via GitHub Copilot plugin)
- GitHub.com Copilot surfaces that support prompt files

If a client does not support repository prompt-file flows, these can still be copied/pasted manually into chat.

## Skill catalog

| File | Workflow intent | Typical use cases |
|---|---|---|
| `admin-ui-skill.prompt.md` | **Copilot Chat + code review prep** | Reviewing admin template/JS changes, selector/localization stability checks, browser test checklisting, accessibility/escaping regression review. |
| `ajax-controllers-skill.prompt.md` | **Copilot Chat + code review prep** | Validating AJAX action registration, capability/nonce checks, sanitization, JSON response handling, and required denied/happy-path tests. |
| `db-changes-skill.prompt.md` | **Copilot Chat + PR prep + code review prep** | Planning/reviewing schema updates, version bump expectations (`Version:` + `AIPS_VERSION`), migration risk/rollback notes, and migration/repository tests. |
| `feature-slicing-skill.prompt.md` | **PR prep** | Breaking large initiatives into safe mergeable slices, defining per-slice verification/risk labels, and identifying inter-slice dependencies/blockers. |
| `generation-pipeline-skill.prompt.md` | **Copilot Chat + code review prep** | Checking generation flow scope, guard/safety regressions in prompt handling, and ensuring logging/history continuity in changed paths. |
| `pr-triage-skill.prompt.md` | **Triage workflows** | Ranking the most recent open PRs by merge complexity, highlighting quick wins/blockers, and producing structured triage output. |

## Notes

- These files are intentionally lightweight so they can be reused across manual chat sessions and scripted triage routines.
- Update checklist items when repository guardrails evolve (labels, test expectations, security checks, or release policy).
