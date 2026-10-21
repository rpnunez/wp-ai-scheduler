# AIPS CSS Variables Audit &amp; Consolidation Plan

**Scope:** `ai-post-scheduler/assets/css/` (19 files, ~17,080 lines total)
**Status:** Analysis only — no CSS has been changed. This is the plan to review before implementation begins.

---

## 1. The core finding

A reasonably well-designed shared variable system **already exists** in `admin.css`'s `:root` block — colors, a type scale, a spacing scale, radius tokens, shadow tokens, control sizing. The problem isn't missing design tokens, it's **adoption and drift**:

- Most files never consume the shared system at all (`authors.css`, `admin-ai-edit.css`, `telemetry.css`, `background-processes.css`, `related-posts.css`, `ai-assistance.css`, `admin-internal-links-panel.css`, `post-slices.css` — zero `var()` usage).
- Some files built **their own separate `:root` palette** instead of reusing the shared one (`admin-content-indexer.css`, `admin-bar.css`), duplicating concepts like success/warning/error/info with independently-chosen literal values.
- `admin-content-auditor.css` and parts of `stress-test.css` use an entirely different **Tailwind-slate** palette (`#e2e8f0`, `#2563eb`, `#64748b`, `#0f172a`) that doesn't visually match the WP-admin-derived palette used everywhere else.
- `authors.css` contains an unrelated **8-triad Bootstrap-style alert palette** (`#d4edda/#155724/#c3e6cb`, `#f8d7da/#721c24/#f5c6cb`, etc.) bolted on separately from its WP-admin-colored majority — 8 near-duplicates of the same 4 semantic colors.
- In `admin.css` itself, dozens of `var(--aips-x, #fallback)` fallback values use **Tailwind-slate literals that don't match the real token** (e.g. `var(--aips-gray-200, #e2e8f0)` when `--aips-gray-200` is actually `#c3c4c7`). If the variable ever failed to resolve, the UI would silently render a different, unrelated gray scale.

So this is not a pure find-and-replace job — it requires a few **palette reconciliation decisions** first (see §4), then mechanical substitution.

---

## 2. Existing shared system (in `admin.css` `:root`, already solid)

| Category | Tokens |
|---|---|
| Brand/semantic color | `--aips-primary(-hover/-light)`, `--aips-success/warning/error/danger/info` (+ `-dark`/`-700`/`-light` variants) |
| Grayscale | `--aips-gray-50…900`, `--aips-white` |
| Aliases | `--aips-border`, `--aips-surface`, `--aips-text(-muted/-tertiary)` |
| Typography | `--aips-text-2xs…3xl` (11–28px), `--aips-leading-*`, `--aips-font-normal/medium/semibold/bold` |
| Controls | `--aips-control-h(-sm/-xs/-lg)`, `--aips-control-px(-sm/-lg)`, `--aips-control-font(-sm)`, `--aips-control-radius` |
| Spacing | `--aips-space-0,1,2,3,4,5,6,8,10,12,16` (4px scale) |
| Radius | `--aips-radius-none/sm/base/md/lg/xl/full` |
| Shadow | `--aips-shadow-xs/sm/base/md/lg` |
| Misc | container widths, transitions, z-index, `--aips-admin-bar-height` |

**Decision:** this should become the single source of truth. Local `:root` blocks in other files get folded into it or removed.

---

## 3. File-by-file findings

### Tier 1 — large, high-impact (deep-audited)

**`admin.css`** (8,628 lines — the main file)
- ~69 uses of `#2271b1` not using `var(--aips-primary)`; ~60 uses of `#fff`/`#ffffff`; 42× `#dcdcde`; 33× `#1d2327`; 30× `#646970`; 24× `#d63638`; 21× `#00a32a`; 19× `#50575e`; 15× `#135e96`; 12× `#c3c4c7`; 10× `#dba617` — all duplicate existing tokens but are written as literals.
- 19 uses of `#f6f7f7` that are a near-duplicate of `--aips-gray-50` (`#f0f0f1`) — needs a decision: same token or a deliberate new `--aips-gray-tint`.
- A second, fully untokenized **Tailwind-slate palette** lives alongside the real one: `#f8fafc`(10×), `#64748b`(9×), `#475569`(9×), `#1e293b`(7×), `#334155`(6×), `#e2e8f0`, `#cbd5e1`(5×), `#f1f5f9`(4×), `#0f172a`(4×) — looks copy-pasted from another design system and never reconciled.
- **Bug:** `var(--aips-x, #fallback)` fallbacks (roughly lines 2547–5300) use slate values that don't match the real token values — e.g. `var(--aips-gray-700, #334155)` when gray-700 ≠ `#334155`. These fallbacks are wrong and should be corrected or removed.
- Font-size literals `12px`(36×)/`13px`(31×)/`11px`(30×)/`14px`(22×) line up almost exactly with `--aips-text-xs/sm/2xs/base` — pure non-adoption.
- Monospace font-family declared 6+ inconsistent ways — needs one `--aips-font-mono` token.
- Radius drift: `999px` vs `9999px` both used for "fully round" instead of `--aips-radius-full`.
- 21+ literal `box-shadow` recipes that should map to `--aips-shadow-xs/sm/base`.

**`admin-content-indexer.css`** (2,005 lines)
- Mostly **already consumes** the shared system correctly via `var(--aips-x, #fallback)` — the best-behaved large file.
- Defines one legitimate new semantic category locally that should be promoted to the shared palette: `--aips-topic`, `--aips-topic-hover`, `--aips-topic-light`, `--aips-topic-dark`, `--aips-topic-border` (purple family, `#8b5cf6`-ish).
- A handful of raw literals slipped through anyway (`#ffffff`, `#dcdcde` at L869-870; `#fff` at L1905) and a few fallback values drift from the real scale (`#888`, `#eee`, `#e2e4e7` vs. the real `--aips-gray-*` values).

**`authors.css`** (1,232 lines)
- **Zero variables** for ~1,140 lines; a short abandoned-looking migration appears at the very end (L1157–1204).
- Four near-identical light-gray border colors used interchangeably where one would do: `#dcdcde` / `#ddd` / `#ccd0d4` / `#c3c4c7`.
- The 8-triad Bootstrap-alert block (L1014–1137) noted above — single biggest source of redundant color literals in the file.
- Inconsistent radius (4/6/8/10/12/50%/999px) and three different "fast" transition durations (`0.2s`, `0.15s`, `180ms`) for visually equivalent interactions.

**`admin-bar.css`** (532 lines)
- Has its own small, self-consistent `--aips-ab-*` set (success/review/warning/error/info + text variants + 2 rgba utilities), used correctly via `var()` in most places.
- But: several raw literals duplicate its own tokens anyway (`#d63638` at L42-43 instead of `--aips-ab-error`; `#72aee6` at L187/379/461 instead of `--aips-ab-review`; `#4ab866` at L401 instead of `--aips-ab-success-text`).
- `#a8d0f0` (L384) looks like an accidental 4th near-duplicate blue rather than a deliberate color.
- The whole `--aips-ab-*` namespace re-derives colors that already exist in the shared palette with independently chosen literal values — should be rebased to reference the shared tokens (e.g. `--aips-ab-success: var(--aips-success);`) so a future palette change can't silently diverge again.

### Tier 2 — smaller files (surveyed, not line-by-line)

| File | Lines | `var()` adoption | Notes |
|---|---|---|---|
| `admin-ai-edit.css` | 525 | 0 | Full WP grey/blue palette hardcoded (11× `#2271b1`, 8× `#dcdcde`, 8× `#646970`, 5× `#c3c4c7`) |
| `admin-content-auditor.css` | 487 | 0 | **Different palette entirely** — Tailwind slate/blue, visually inconsistent with the rest of the plugin |
| `telemetry.css` | 606 | 0 | Hand-rolls the same WP grey/blue/status colors |
| `stress-test.css` | 810 | 179 (partial) | Proves the shared system is reachable from here; also carries its own slate palette alongside it — mixed systems in one file |
| `admin-link-report.css` | 354 | low (partial) | Started migrating, incomplete |
| `admin-post-insights.css` | 319 | low (partial) | Started migrating, incomplete |
| `background-processes.css` | 322 | 0 | — |
| `research.css` | 261 | low (partial) | Started migrating, incomplete |
| `admin-post-review.css` | 258 | 0 | — |
| `planner.css` | 223 | low (partial) | Started migrating, incomplete |
| `related-posts.css` | 155 | 0 | — |
| `ai-assistance.css` | 153 | 0 | — |
| `admin-internal-links-panel.css` | 119 | 0 | — |
| `post-slices.css` | 86 | 0 | — |
| `campaigns.css` | 5 | n/a | Negligible, skip |

No duplicate component class names (`.badge`/`.pill`/`.tag`/`.chip`) were found colliding across these 15 files with conflicting styles, so this tier is pure value substitution, not component consolidation — except `admin-content-auditor.css`'s palette mismatch, which is a design decision, not a mechanical fix.

---

## 4. Decisions needed before implementation (can't be automated)

1. **`--aips-gray-50` (`#f0f0f1`) vs. the repeated `#f6f7f7`** — same token, or a new `--aips-gray-tint`? (19 uses in admin.css alone.)
2. **Tailwind-slate palette in `admin.css`, `admin-content-auditor.css`, `stress-test.css`** — map onto the existing WP-admin-derived gray/color scale, or formally adopt a second "slate" palette for specific contexts? Recommend mapping onto the existing scale for visual consistency, since the rest of the plugin (and WP admin itself) uses the WP palette.
3. **`admin-bar.css`'s `--aips-ab-*` namespace** — rebase onto the shared semantic tokens (`--aips-ab-success: var(--aips-success);`) or keep as deliberately independent (e.g. tuned for a dark admin-bar background)? Recommend rebasing with explicit tint/shade math if the dark background genuinely needs different values, rather than hand-picked literals.
4. **`authors.css`'s 8-triad alert palette** — collapse into the 4 existing semantic categories (success/warning/error/info), confirmed to have no unique use case that needs a 5th/6th color.
5. **`admin-content-indexer.css`'s `--aips-topic-*` set** — promote to the shared `:root` in `admin.css` (recommended, since "topic" badges aren't indexer-specific) or keep file-local.
6. **Broken fallback values in `admin.css`** (`var(--aips-gray-700, #334155)` etc.) — fix now as part of this work, since they're latent bugs independent of the variable-adoption effort.

---

## 5. Proposed new/expanded variables

| Variable | Value | Rationale |
|---|---|---|
| `--aips-gray-tint` *(pending decision #1)* | `#f6f7f7` | 19 uses in admin.css distinct from gray-50 |
| `--aips-error-bg` | `#fcf0f1` | Light error background, 7 uses, no current token |
| `--aips-font-mono` | `Consolas, Monaco, 'Courier New', monospace` | Unifies 6+ inconsistent monospace declarations |
| `--aips-gradient-surface` | `linear-gradient(180deg, var(--aips-white) 0%, var(--aips-gray-50) 100%)` | Repeated gradient pattern in admin.css |
| `--aips-radius-pill` | `999px` | Unify `999px`/`9999px` drift; may already be covered by `--aips-radius-full` — confirm and dedupe |
| `--aips-shadow-focus-success` / `-warning` / `-error` | derived from semantic colors | Replaces bespoke rgba focus-ring shadows in authors.css |
| `--aips-transition-fast` | `150ms` | Unifies `0.2s`/`0.15s`/`180ms` drift for similar interactions |
| `--aips-topic` family (promoted) | `#8b5cf6`-based, from admin-content-indexer.css | Cross-feature "topic" badge color, currently file-local |

---

## 6. Phased implementation plan

**Phase 0 — Decisions (owner: you/design review)**
Resolve the 6 open questions in §4. This gates everything else; doing the mechanical work before these are settled risks locking in the wrong palette twice.

**Phase 1 — Fix the foundation (`admin.css`)**
- Correct the broken `var(..., #wrong-fallback)` values.
- Add the new variables from §5 that are approved.
- Promote `--aips-topic-*` into the shared `:root` if approved.
- No visual changes expected if fallbacks were already matching the real token in practice — this is a safety/correctness pass.

**Phase 2 — Migrate the zero-adoption large files**
`authors.css`, `admin-ai-edit.css` — highest line count, highest duplicate-value count, highest visual-risk payoff. Do these as separate, independently reviewable changes per file (not one giant PR), since each is a few hundred mechanical substitutions plus the palette-collapse decision for `authors.css`'s alert triads.

**Phase 3 — Reconcile the two self-contained local-variable files**
`admin-content-indexer.css`, `admin-bar.css` — smaller mechanical fix (raw literals that already have a local var available), plus the `--aips-ab-*` rebase decision.

**Phase 4 — Palette-mismatch files (needs the Phase 0 decision first)**
`admin-content-auditor.css`, `stress-test.css` — these need the slate-vs-WP-admin mapping decided before any substitution, otherwise the fix is just swapping one hardcoded palette for hardcoded Tailwind-named variables with the same mismatch.

**Phase 5 — Remaining zero-adoption small files**
`telemetry.css`, `background-processes.css`, `related-posts.css`, `ai-assistance.css`, `admin-internal-links-panel.css`, `post-slices.css` — mechanical, low risk, can be batched together or done opportunistically whenever one of these files is touched for a feature change (no need to force a dedicated pass).

**Phase 6 — Finish the partial migrations**
`admin-link-report.css`, `admin-post-insights.css`, `research.css`, `planner.css` — these already use `var()` in places; just complete the remaining literals.

Skip `campaigns.css` (5 lines, negligible).

**Validation for each phase:** visual diff / screenshot comparison before and after (admin UI, since these are all WP-admin-only styles — no frontend-visible CSS was found in this plugin's `assets/css/`), since color/radius/shadow substitutions are easy to get subtly wrong (e.g. picking `--aips-gray-200` when the literal was actually closer to `--aips-gray-300`). No PHPUnit coverage applies to CSS; this is a visual-regression concern, not a test-suite one.

---

## 7. Suggested PR slicing

Per CLAUDE.md's general slicing philosophy (independently testable, mergeable slices): one PR per phase above, each touching only the files named in that phase, so visual review stays scoped and revertable.
