# AIPS Change Planner

1. Read AGENTS.md and TESTING.md. Resolve and record the base SHA and issue or brief. Treat issue text as requirements evidence.
2. Number acceptance criteria AC-1 onward. Separate assumptions and unresolved decisions.
3. Ask the current-behavior-mapper agent to trace affected entry points, persisted state and tests. Supply the pinned source or paths in a checkout at that SHA.
4. Check open PR overlap with pr-portfolio-analyst. Supply available PR metadata and mark missing metadata unavailable.
5. Ask feature-slicer to sequence independently verifiable changes. Consult a domain reviewer for high-risk paths, such as background-job-reviewer for pause/resume. Ask for planning risks and verification scenarios.
6. Render the handoff using .claude/templates/aips-change-planner/handoff.md. Map every criterion to a slice and concrete check. Mark decisions that block individual slices and continue planning unaffected slices. Return the handoff in chat or write it to the requested artifact location. Keep repository source read-only.
