# Cloudflare AI Gateway fixtures

Real request/response pairs recorded by `scripts/cf-ai-gateway-probe.php` (Slice 0 of
`docs/plans/ai-providers/cloudflare-ai-gateway-provider-plan.md`). The tests for
`AIPS_Cloudflare_AI_Gateway_Client` replay them through the `pre_http_request` filter.

- The account ID and token are replaced with `{account_id}` and `{api_token}`.
- Base64 image payloads are replaced with a `<<base64 omitted: N chars, detected MIME>>` marker.
- `SUMMARY.md` is the probe's own summary table from the last run.

Recorded so far (2026-11-25, without valid credentials):

| File | What it shows |
|---|---|
| `error-invalid-token.json` | A well-formed but wrong token on `/ai/v1/chat/completions` returns HTTP 401 with the Cloudflare v4 envelope and code `10000`. |
| `error-malformed-token.json` | A token in the wrong format on `/user/tokens/verify` returns HTTP 400, code `6003`, with `error_chain` code `6111`. |

To record the rest, set `CLOUDFLARE_ACCOUNT_ID` and `CLOUDFLARE_API_TOKEN`, then run
`php scripts/cf-ai-gateway-probe.php` from the repository root.
