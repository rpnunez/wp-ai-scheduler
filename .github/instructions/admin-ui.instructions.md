---
applyTo: "ai-post-scheduler/templates/admin/**/*.php,ai-post-scheduler/assets/js/admin*.js,ai-post-scheduler/assets/css/admin*.css"
---

Lane: **Admin UI changes** (`admin-ui`, `needs-browser-test`)

- Keep templates presentation-only; move business logic to includes/.
- Preserve existing `aips-*` hooks/classes used by JS.
- Localize user-facing strings; escape output correctly in templates.
- For behavior changes, include a manual browser verification checklist.
- Apply both `admin-ui` and `needs-browser-test` labels on related PRs.
