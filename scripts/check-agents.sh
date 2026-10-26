#!/usr/bin/env bash
# Verify every agent in .agents/agents/ has a thin wrapper for each tool that points back at it,
# and that no wrapper is orphaned. Run: bash scripts/check-agents.sh (or: make check-agents)
set -u
cd "$(dirname "$0")/.." || exit 1

fail=0
err() { echo "ERROR: $*" >&2; fail=1; }

shopt -s nullglob

for canon in .agents/agents/*.md; do
	name=$(basename "$canon" .md)
	for wrapper in ".claude/agents/$name.md" ".codex/agents/$name.toml" ".github/agents/$name.agent.md"; do
		if [ ! -f "$wrapper" ]; then
			err "missing wrapper: $wrapper"
		elif ! grep -q "\.agents/agents/$name\.md" "$wrapper"; then
			err "$wrapper does not reference .agents/agents/$name.md"
		fi
	done
done

for wrapper in .claude/agents/*.md .codex/agents/*.toml .github/agents/*.agent.md; do
	base=$(basename "$wrapper")
	name=${base%.agent.md}
	name=${name%.md}
	name=${name%.toml}
	[ -f ".agents/agents/$name.md" ] || err "orphan wrapper (no canonical agent): $wrapper"
done

if [ "$fail" -eq 0 ]; then
	echo "OK: $(ls .agents/agents/*.md | wc -l) agents, all wrappers present."
fi
exit "$fail"
