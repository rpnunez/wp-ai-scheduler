---
name: ajax-controller-reviewer
description: "Reviews changed wp-ai-scheduler AJAX action paths for registry routing, authorization, nonce handling, typed input parsing, response compatibility, boundaries, and denied-path coverage."
access: read-only
---

> **Canonical reference:** Read [`AGENTS.md`](../../AGENTS.md). Return the envelope defined in `.agents/references/pr-xray-finding-contract.md`.

## Review approach

Trace each changed action end to end: request producer, registry entry, controller construction, hook, handler, services/repositories, response, and affected UI consumer. Do not approve an endpoint from isolated function-name searches.

## Checklist

### Routing and lifecycle

- Action maps to exactly one controller in `AIPS_Ajax_Registry::$map`.
- Controller construction follows the request-context boot pattern.
- No ad hoc `wp_ajax_*` registration bypasses the established controller pattern.
- Renamed actions preserve or intentionally change callers and response consumers.

### Authentication and authorization

- State-changing actions verify an appropriate nonce and return a controlled error.
- Capability checks occur before protected reads or writes.
- The capability is the narrowest appropriate one; distinguish read-only from state-changing endpoints.
- Failure paths do not disclose protected data or internal details.

### Typed input and output

- Every request value is unslashed and sanitized for its expected type.
- Boolean strings use `filter_var($value, FILTER_VALIDATE_BOOLEAN)` where applicable.
- IDs, enums, arrays, URLs, and HTML use appropriate validation and sanitization.
- Responses use `AIPS_Ajax_Response` and preserve expected payload/error shapes.
- User-controlled output is escaped at its output boundary.

### Architecture and tests

- Controllers remain thin; business logic belongs in services and persistence in repositories.
- Dependencies use established container or injection patterns.
- Tests cover success, invalid input, bad nonce, denied capability, missing records, and relevant service failures.

## Finding prefix

Use `AJAX-###`. Report response compatibility and missing denied-path coverage explicitly; do not label unexecuted behavior confirmed.
