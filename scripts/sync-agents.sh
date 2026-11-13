#!/usr/bin/env bash
# Generate the thin per-tool wrappers from the canonical files under .agents/.
#
#   bash scripts/sync-agents.sh           rewrite wrappers             (make sync-agents)
#   bash scripts/sync-agents.sh --check   fail on any drift, no writes (make check-agents)
#
# Canonical sources (edit these, never the wrappers):
#   .agents/agents/<name>.md            frontmatter: name, description, access (read-only | read-write)
#   .agents/agents/<name>.copilot.yml   optional extra Copilot frontmatter; its name:/tools: lines override the defaults
#   .agents/agents/<name>.codex.toml    optional extra Codex keys (e.g. model_reasoning_effort)
#   .agents/skills/<name>/SKILL.md      frontmatter: name, description
#   .agents/rules/<name>.md             frontmatter: paths (comma-separated globs)
#
# Generated wrappers:
#   .claude/agents/<name>.md  .codex/agents/<name>.toml  .github/agents/<name>.agent.md
#   .claude/skills/<name>/SKILL.md
#   .claude/rules/<name>.md   .github/instructions/<name>.instructions.md
#
# A file in a wrapper folder with no canonical source is an error in both modes; it is never deleted for you.
set -u
cd "$(dirname "$0")/.." || exit 1

mode=write
[ "${1:-}" = "--check" ] && mode=check

fail=0
wrote=0
expected=""
err() { echo "ERROR: $*" >&2; fail=1; }

# fm <file> <key>: print a single-line frontmatter value, unquoted.
fm() {
	awk -v k="$2" -v sq="'" '
		{ sub(/\r$/, "") }
		NR == 1 { if ($0 != "---") exit; next }
		$0 == "---" { exit }
		index($0, k ":") == 1 {
			v = substr($0, length(k) + 2)
			sub(/^[ \t]+/, "", v); sub(/[ \t]+$/, "", v)
			f = substr(v, 1, 1); l = substr(v, length(v), 1)
			if (length(v) > 1 && ((f == "\"" && l == "\"") || (f == sq && l == sq))) v = substr(v, 2, length(v) - 2)
			print v
			exit
		}' "$1"
}

# emit <path> <content>: write the wrapper (write mode) or report drift (check mode).
# Content is an argument, not stdin: a pipeline would run this in a subshell and lose fail/expected.
emit() {
	local path=$1 new=$2
	expected="$expected$path"$'\n'
	if [ -f "$path" ] && [ "$new" = "$(tr -d '\r' < "$path")" ]; then
		return
	fi
	if [ "$mode" = check ]; then
		if [ -f "$path" ]; then err "stale wrapper: $path"; else err "missing wrapper: $path"; fi
	else
		mkdir -p "$(dirname "$path")"
		printf '%s\n' "$new" > "$path"
		echo "wrote $path"
		wrote=$((wrote + 1))
	fi
}

# safe_text <file> <value>: descriptions are copied into YAML and TOML double-quoted strings.
safe_text() {
	case "$2" in
		"") err "$1: missing description"; return 1 ;;
		*'"'*|*'\'*) err "$1: description must not contain double quotes or backslashes"; return 1 ;;
	esac
}

shopt -s nullglob
agents=0
skills=0
rules=0

# ---- agents ----
for canon in .agents/agents/*.md; do
	name=$(basename "$canon" .md)
	desc=$(fm "$canon" description)
	access=$(fm "$canon" access)
	[ "$(fm "$canon" name)" = "$name" ] || { err "$canon: frontmatter name must be '$name'"; continue; }
	safe_text "$canon" "$desc" || continue
	case "$access" in
		read-only)
			claude_tools="Read, Grep, Glob"
			copilot_tools="[read, search]"
			codex_extra='sandbox_mode = "read-only"'
			;;
		read-write)
			claude_tools="Read, Grep, Glob, Edit, Write, Bash"
			copilot_tools="[read, search, edit, execute]"
			codex_extra=""
			;;
		*) err "$canon: access must be read-only or read-write"; continue ;;
	esac
	agents=$((agents + 1))
	pointer="Read \`$canon\` and follow it exactly. Repository-wide rules are in \`AGENTS.md\`."

	content=$(printf -- '---\nname: %s\ndescription: "%s"\ntools: %s\n---\n\n%s\n' "$name" "$desc" "$claude_tools" "$pointer")
	emit ".claude/agents/$name.md" "$content"

	if [ -f ".agents/agents/$name.codex.toml" ]; then
		side=$(tr -d '\r' < ".agents/agents/$name.codex.toml")
		codex_extra="${codex_extra:+$codex_extra$'\n'}$side"
	fi
	content=$(printf 'name = "%s"\ndescription = "%s"\n%s%sdeveloper_instructions = "%s"\n' \
		"$name" "$desc" "$codex_extra" "${codex_extra:+$'\n'}" "$pointer")
	emit ".codex/agents/$name.toml" "$content"

	copilot_name="name: \"$name\""
	copilot_tools_line="tools: $copilot_tools"
	copilot_extra=""
	if [ -f ".agents/agents/$name.copilot.yml" ]; then
		side=$(tr -d '\r' < ".agents/agents/$name.copilot.yml")
		line=$(printf '%s\n' "$side" | grep -m1 '^name:') && copilot_name=$line
		line=$(printf '%s\n' "$side" | grep -m1 '^tools:') && copilot_tools_line=$line
		copilot_extra=$(printf '%s\n' "$side" | grep -vE '^(name|tools):')
	fi
	content=$(printf -- '---\n%s\ndescription: "%s"\n%s\n%s%s---\n\n%s\n' \
		"$copilot_name" "$desc" "$copilot_tools_line" "$copilot_extra" "${copilot_extra:+$'\n'}" "$pointer")
	emit ".github/agents/$name.agent.md" "$content"
done

# ---- skills (only Claude Code needs a stub; other tools read .agents/skills directly) ----
for canon in .agents/skills/*/SKILL.md; do
	name=$(basename "$(dirname "$canon")")
	desc=$(fm "$canon" description)
	[ "$(fm "$canon" name)" = "$name" ] || { err "$canon: frontmatter name must be '$name'"; continue; }
	safe_text "$canon" "$desc" || continue
	skills=$((skills + 1))
	content=$(printf -- '---\nname: %s\ndescription: "%s"\n---\n\nThe full skill lives at `%s`. Read it and follow it; this stub exists only because Claude Code discovers skills under `.claude/skills/`.\n' \
		"$name" "$desc" "$canon")
	emit ".claude/skills/$name/SKILL.md" "$content"
done

# ---- rules (path-scoped pointers so Claude Code and Copilot load them for matching files) ----
for canon in .agents/rules/*.md; do
	name=$(basename "$canon" .md)
	paths=$(fm "$canon" paths | sed -E 's/[ \t]*,[ \t]*/,/g')
	[ -n "$paths" ] || { err "$canon: missing 'paths' frontmatter (comma-separated globs)"; continue; }
	case "$paths" in *'"'*|*'\'*) err "$canon: paths must not contain double quotes or backslashes"; continue ;; esac
	rules=$((rules + 1))
	path_list=$(printf '%s\n' "$paths" | tr ',' '\n' | sed -E 's/.*/  - "&"/')
	content=$(printf -- '---\npaths:\n%s\n---\n\nBefore editing these files, read `%s` and follow it. This stub exists only so Claude Code loads the pointer when matching files are touched.\n' \
		"$path_list" "$canon")
	emit ".claude/rules/$name.md" "$content"
	content=$(printf -- '---\napplyTo: "%s"\n---\n\nFollow [%s](../../%s) when editing these files. Do not duplicate it here.\n' \
		"$paths" "$canon" "$canon")
	emit ".github/instructions/$name.instructions.md" "$content"
done

# ---- anything else in a wrapper folder was written by hand ----
for f in .claude/agents/*.md .codex/agents/*.toml .github/agents/*.agent.md \
	.claude/skills/*/SKILL.md .claude/rules/*.md .github/instructions/*.instructions.md; do
	printf '%s' "$expected" | grep -Fxq "$f" && continue
	err "$f has no canonical source under .agents/. Move its content there (or delete it), then run: make sync-agents"
done

if [ "$fail" -eq 0 ]; then
	if [ "$mode" = check ]; then
		echo "OK: $agents agents, $skills skills, $rules rules; all wrappers match their canonical source."
	else
		echo "OK: $agents agents, $skills skills, $rules rules; $wrote wrapper(s) rewritten."
	fi
elif [ "$mode" = check ]; then
	echo "Wrappers are out of sync. Edit the canonical file under .agents/ and run: make sync-agents" >&2
fi
exit "$fail"
