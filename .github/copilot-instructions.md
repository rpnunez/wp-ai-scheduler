# Copilot Instructions for AI Post Scheduler

- Treat [../AGENTS.md](../AGENTS.md) as the canonical repository and architecture context. Do not duplicate that guidance here.
- Before proposing code, check the relevant file-specific guidance in `.github/instructions/` when it applies to the files being edited.
- Keep Copilot suggestions small, reviewable, and aligned with the WordPress/PHP conventions in `AGENTS.md`.
- Prefer repository services, controllers, and repositories that already exist; avoid inventing parallel patterns.
- To run tests, follow the Testing policy in [../AGENTS.md](../AGENTS.md) and [../TESTING.md](../TESTING.md): run `make test-session ARGS="tests/Test_A.php tests/Test_B.php"` (all files in one invocation; it stops the containers it started). Do not call `scripts/run-docker-test.sh` directly, chain runs, or run `./start-dev.sh` just to test. Stop any containers you started when done. Run the tests covering your changes.
- For larger subsystem details, consult [../docs/AI_AGENT_REFERENCE.md](../docs/AI_AGENT_REFERENCE.md) and [../docs/DEVELOPMENT_GUIDELINES.md](../docs/DEVELOPMENT_GUIDELINES.md).
