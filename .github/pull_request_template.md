## Summary
- What changed?
- Why now?

## Verification
- [ ] `composer test` (or targeted tests) run for changed behavior
- [ ] Manual verification completed for changed admin/runtime paths
- [ ] Documentation updated (if behavior or workflow changed)

## Risk Checklist
- [ ] Label `schema-change` applied when DB schema/version is touched
- [ ] Label `admin-ui` + `needs-browser-test` applied when admin UI is touched
- [ ] Label `ajax-registry` applied when AJAX handlers/registry are touched
- [ ] Label `cron` applied when cron/queue/retry paths are touched
- [ ] Label `generation-pipeline` applied when prompt/generation flow is touched
- [ ] Label `security-sensitive` applied when auth/nonce/capability/data-safety paths are touched
- [ ] Label duplicate-risk applied and addressed before merge
