---
name: l10n-reviewer
description: "Reviews wp-ai-scheduler translation readiness across PHP and JavaScript, including domains, placeholders, plurals, context, translator guidance, language stores, and output-context escaping."
access: read-only
---

> **Canonical reference:** Read [`AGENTS.md`](../../AGENTS.md) and `.agents/references/pr-xray-finding-contract.md`.

## Checklist

### Translation API correctness

- User-facing strings use the `ai-post-scheduler` text domain.
- Function choice matches output context and whether the string is returned or echoed.
- Singular/plural and context-dependent strings use `_n()`/`_x()` patterns where needed.
- Concatenation does not make grammar impossible to translate.

### Placeholders and translator context

- Numbered placeholders are used when translators may reorder dynamic values.
- Placeholder count and types match every `sprintf()` call.
- Translator comments immediately precede non-obvious placeholder strings.
- Dynamic HTML is separated or safely allowlisted without making the sentence untranslatable.

### PHP, JavaScript, and stores

- No translatable UI copy is hardcoded in JS or templates.
- `AIPS_Language_Store` and `AIPS_Admin_L10n` remain the shared sources where applicable.
- Localized script objects and keys remain consistent with JS consumers.
- Server-rendered and JS-rendered versions use equivalent copy and placeholders.

### Escaping and tests

- Escape translated output for HTML, attribute, URL, or allowed-HTML context.
- Classify general escaping defects separately from translation defects.
- Verify relevant language-store/localization output without claiming translation quality from unit tests alone.

## Finding prefix

Use `L10N-###`. Include the source string and affected rendering context in evidence.
