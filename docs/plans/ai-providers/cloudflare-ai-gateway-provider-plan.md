# Plan: Cloudflare AI Gateway provider (Option B)

Status: proposed · Owner: TBD · Target: 3.8.x

## Goal

Add a third AI backend, `cloudflare_ai_gateway`, next to `meow` and `wp_ai_client`. It calls the
Cloudflare AI Gateway REST API directly, so one Cloudflare API token reaches OpenAI, Anthropic,
Google and Workers AI models. It works with Unified Billing (prepaid Cloudflare credits) or BYOK
(provider keys stored in Cloudflare). Every call is logged in the Cloudflare dashboard with real
token counts and cost, tagged with the AIPS correlation ID.

## User-visible outcomes

1. **Engine settings:** "Cloudflare AI Gateway" appears in the AI Provider dropdown. When it is
   selected, the admin enters an Account ID, an API token (masked, encrypted at rest), a Gateway ID
   (default `default`) and models for text, image and embeddings.
2. **Test connection:** a button checks the token and runs a one-line completion, then shows a toast
   with the result or the Cloudflare error in plain words.
3. **Generation:** all existing generation entry points (scheduled, manual, bulk, regenerate,
   research, topics) work unchanged with the new provider: text, structured JSON, multi-turn
   conversation and featured images.
4. **Embeddings:** internal-links indexing and author embeddings work without Meow AI Engine,
   which fixes today's WP AI Client gap. Changing the embedding model warns that a re-index is needed.
5. **Observability:** each Cloudflare log entry carries `cf-aig-metadata` with the AIPS correlation
   ID and call type, so a post's History entry can be matched to its Cloudflare cost.
6. **Diagnostics:** System Info and the AI call counter list the new provider like the other two.
7. **No change for existing installs:** the provider is appended last in `REGISTRY`, so auto-detection
   still prefers Meow, then WP AI Client.

## Technical acceptance criteria

- `AIPS_Cloudflare_AI_Gateway_Provider implements AIPS_AI_Provider_Interface` and is a thin adapter.
  HTTP, auth headers and error normalization live in `AIPS_Cloudflare_AI_Gateway_Client`.
- All HTTP goes through `wp_remote_post` / `wp_remote_get`, so tests stub it with `pre_http_request`
  fixtures. No real network calls in PHPUnit.
- `is_available()` makes no network call. It is true when the account ID and a decryptable token are
  present. `get_unavailable_reason()` names what is missing.
- Failures throw `Exception("<code>: <message>")`. `extract_error_code()` maps Cloudflare codes onto
  the codes `AIPS_Resilience_Service` already understands, so retries, the circuit breaker and the
  quota alert behave the same as with the other providers (mapping below).
- Retries happen in one layer only: AIPS keeps its retries, and the client sends
  `cf-aig-max-attempts: 1`.
- Generation calls (text, JSON, image) always send `cf-aig-skip-cache: true`. Otherwise Regenerate
  could return the cached previous answer.
- The token is stored with `AIPS_Secret_Encryption` (`aipsenc:` prefix), never echoed back to the
  form, and submitting the field blank keeps the stored token.
- No SQL outside repositories. AJAX actions are registered only in `AIPS_Ajax_Registry`.

## Architecture

```
AIPS_AI_Service ──► AIPS_AI_Provider_Factory::REGISTRY
                      ├─ meow          → AIPS_Meow_AI_Provider
                      ├─ wp_ai_client  → AIPS_WP_AI_Client_Provider
                      └─ cloudflare_ai_gateway → AIPS_Cloudflare_AI_Gateway_Provider   (new)
                                                    │  maps canonical params ⇄ OpenAI / Workers AI shapes
                                                    ▼
                                          AIPS_Cloudflare_AI_Gateway_Client          (new)
                                            · base: https://api.cloudflare.com/client/v4/accounts/{account}/ai
                                            · Authorization: Bearer <token>
                                            · cf-aig-gateway-id, cf-aig-metadata, cf-aig-skip-cache,
                                              cf-aig-max-attempts: 1, cf-aig-collect-log-payload
                                            · wp_remote_post, timeout from existing request-timeout option
                                            · normalizes errors → Exception("code: message")
```

| Interface method | Endpoint | Notes |
|---|---|---|
| `generate_text` | `POST /ai/v1/chat/completions` | `instructions` → `system` message. Canonical `messages` (role `user`/`model`) → `user`/`assistant`. Passes `max_tokens` and `temperature`. The model may be `dynamic/<route>` to use a Cloudflare fallback route. |
| `generate_json` | same, with `response_format` | Native JSON only if the trial (Slice 0) shows `json_schema` passes through. Otherwise return `null`, and the service falls back to text-based JSON extraction. |
| `generate_image` | `POST /ai/run` | Default model to be decided in Slice 0 (e.g. `@cf/black-forest-labs/flux-1-schnell`). The response is turned into a data URI. |
| `generate_embedding` | `POST /ai/run` | Default `@cf/baai/bge-m3`. Dimensions are read from the response, not hard-coded. |
| `supports_conversation` | — | `true` |
| `supports_embeddings` | — | `true` when an embedding model is configured |

### Error-code mapping (Cloudflare → AIPS)

| Cloudflare | HTTP | AIPS code | Retry? |
|---|---|---|---|
| 10000, 2009 | 401 | `invalid_api_key` | no |
| 6003 / `error_chain` 6111 (malformed token) | 400 | `invalid_api_key` | no |
| 2044 (provider credentials required) | 400 | `invalid_api_key` | no |
| 2052, 2021 (no credits / payment) | 402 | existing quota/billing code (`insufficient_quota` / `billing_not_active`) → quota alert | no |
| 2042, 2049, 2036 (feature not enabled) | 403 | `billing_not_active` or a new non-retryable code | no |
| "Model not found" | 404 | `model_not_found` | no |
| 7003 (bad input), 413 | 400/413 | `invalid_request_error` / `context_length_exceeded` | no |
| 2018 / plain 429 | 429 | existing rate-limit code | yes, with backoff |
| 2002, 2005, 7005, 5xx, timeouts | 5xx | generic transient | yes |

Use the exact code strings from `AIPS_Resilience_Service::NON_RETRYABLE_CODES` and the quota
detection in `AIPS_AI_Error_Classifier` when implementing; do not invent parallel ones.

### Settings (registered in `AIPS_Settings_Registry`, Engine tab, `aips_ai_provider_section`)

| Key | Type / control | Default |
|---|---|---|
| `aips_cf_account_id` | string / text | `''` |
| `aips_cf_api_token` | string / `CONTROL_SECRET`. A custom sanitize callback encrypts the value and keeps the old one when the field is blank. | `''` |
| `aips_cf_gateway_id` | string / text | `'default'` |
| `aips_cf_text_model` | string / text with suggestions (`google/gemini-3-flash`, `openai/gpt-4.1`, `anthropic/claude-sonnet-4`, `dynamic/<route>`) | decide in Slice 0 |
| `aips_cf_image_model` | string / text | decide in Slice 0 |
| `aips_cf_embedding_model` | string / text | `@cf/baai/bge-m3` |
| `aips_cf_log_payloads` | bool / toggle. Sends `cf-aig-collect-log-payload`. | **decision needed** (on = easier debugging; off = prompts and posts aren't stored at Cloudflare) |

These fields are shown only when `aips_ai_provider` is `cloudflare_ai_gateway`, using the
conditional-field approach the WP AI connector block already uses. Defaults follow the settings
pattern (`AIPS_Config::get_default_options()` ⇄ registry).

## Slices

Each slice is one PR, reviewable alone, in this order. Nothing user-visible ships until Slice 3.

### Slice 0: API trial (no merge)
Confirm what the docs leave unclear, using a real account and a scratch PHP/curl script:
- chat-completions response shape and `usage` fields
- whether `response_format: {type: json_schema}` passes through for Gemini, OpenAI and Anthropic models
- the error body format (Cloudflare v4 `{success, errors[]}` or OpenAI-style `{error:{}}`)
- the input/output shape of `/ai/run` for an embedding model and an image model (base64 or URL)
- whether a `cf-aig-log-id` response header exists

**Output:** sanitized JSON response fixtures saved under `ai-post-scheduler/tests/fixtures/cloudflare-ai-gateway/`,
plus default model choices.
**Needs from you:** a Cloudflare account ID and an API token with *Workers AI – Read* and
*AI Gateway – Run/Read*, plus Unified Billing credits or a BYOK key.

**Tooling:** `scripts/cf-ai-gateway-probe.php` is a standalone PHP CLI script that needs no WordPress.
It reads the credentials from `CLOUDFLARE_ACCOUNT_ID` / `CLOUDFLARE_API_TOKEN`, runs about 20 small
requests, and writes one redacted fixture per request plus a `SUMMARY.md` table. It probes:
- token verification (user and account scope)
- for each model in `CF_PROBE_TEXT_MODELS`: chat basic, a conversation, `json_schema` in the OpenAI
  form (`{name, schema, strict}`), `json_schema` in the Workers AI form (the bare schema), and `json_object`
- cache HIT/MISS on two identical requests
- embeddings three ways (`/ai/v1/embeddings`, `/ai/run/{model}`, wrapped `/ai/run`)
- images two ways (`/ai/run/{model}`, wrapped `/ai/run`)
- four error cases

#### Slice 0 findings so far

Confirmed by live calls (run with fake credentials, so only the error cases):
- **Error envelope:** auth failures on `/ai/v1/chat/completions` use the Cloudflare v4 envelope
  `{"result":null,"success":false,"errors":[{"code":10000,"message":"Authentication error"}],"messages":[]}`,
  HTTP 401. A badly formatted token gets HTTP 400, code `6003`, with a nested
  `error_chain: [{code: 6111}]`. The client must read `errors[0].code`, and also look in `error_chain`.
- Authentication is checked before model lookup, so the model-not-found and bad-input shapes still
  need a valid token.

Confirmed by docs:
- **`/ai/run/{model}` (path form)** takes the model's inputs directly. The wrapped
  `{model, input}` form is documented for third-party models on `/ai/run`. The probe tries both.
- **Embeddings:** inputs are `{"text": string | string[]}`. `@cf/baai/bge-base-en-v1.5` returns 768
  dimensions, and `pooling` (`mean` or `cls`) changes the vectors, so it must stay fixed per index.
  Workers AI also documents an OpenAI-compatible `/v1/embeddings`. The bge-m3 dimension count is
  not documented, so read it from the response.
- **Images:** `@cf/black-forest-labs/flux-1-schnell` takes `{prompt (≤2048 chars), steps (≤8, default 4)}`
  and returns `{"image": "<base64>"}` (JPEG in Cloudflare's own example). There is no URL form.
- **JSON mode:** `response_format: {type: "json_object" | "json_schema", json_schema}` is documented
  for Workers AI models, with no streaming and a possible "JSON Mode couldn't be met" error. For
  third-party models it is passed through to the provider, so the probe checks each model.
- **Models:** there is no API to list models (the catalog is a docs page only), so the settings
  use text fields with suggestions rather than a dropdown loaded from the API.

Still open until the probe runs with real credentials: the chat-completions response and `usage`
fields, which `json_schema` form each provider accepts, the embedding and image response envelopes,
the not-found/bad-input error bodies, and whether there is a `cf-aig-log-id` header.

### Slice 1: Settings and secret storage
**Status: done.** Payload logging defaults to **off**. The text model has no default, so the
provider stays unavailable until one is entered. The settings live in their own
"Cloudflare AI Gateway" section on the Engine tab, and each field carries a
`data-aips-depends-on="aips_ai_provider"` attribute. The JS that acts on that attribute lands in
Slice 6. A blank token field keeps the saved token. Removing a saved token is left for Slice 6
(a "Remove token" button).

- Register the settings above, with defaults in `AIPS_Config`.
- Token sanitize callback: encrypt, keep the stored value when blank, mask on render.
- A small `AIPS_Cloudflare_AI_Gateway_Config` value object that reads and decrypts settings, so the
  provider never touches options directly.
- **Gate:** `Test_AIPS_Settings_Registry.php`, new `Test_AIPS_Cloudflare_AI_Gateway_Settings.php`
  (encrypt round trip, blank keeps the old value, the form never outputs the token).
- **Rollback:** settings only. Removing the registry entries leaves harmless orphan options.

### Slice 2: HTTP client and error normalization
- `includes/providers/class-aips-cloudflare-ai-gateway-client.php`: `chat()`, `run()`, `verify_token()`.
- Builds headers, including `cf-aig-metadata` = JSON `{aips_correlation_id, aips_call_type, aips_version}`
  (scalar values only; keep it small, since the size limit isn't documented).
- Normalizes `WP_Error`s, non-2xx responses and malformed JSON into `Exception("code: message")`.
- **Gate:** new `Test_AIPS_Cloudflare_AI_Gateway_Client.php`, driven by `pre_http_request` with the
  Slice 0 fixtures. Covers URL and header construction, each row of the error-mapping table, and a
  timeout.

### Slice 3: Provider (text, conversation, JSON), registered in the factory
- `includes/providers/class-aips-cloudflare-ai-gateway-provider.php`.
- Append it to `AIPS_AI_Provider_Factory::REGISTRY`, after `wp_ai_client`.
- Add the label and id to the hard-coded lists in `AIPS_System_Info_Provider` (lines ~195 and ~260).
  The call counter keys by provider id automatically.
- Image and embedding methods throw a clear "not configured yet" error until Slices 4 and 5.
- **Gate:** new `Test_AIPS_Cloudflare_AI_Gateway_Provider.php`, plus the existing
  `Test_AIPS_AI_Provider_Factory.php`, `Test_AIPS_Provider_Availability.php`,
  `Test_AIPS_AI_Service_With_Provider.php`, `Test_AIPS_Resilience_Service.php` and
  `Test_AIPS_System_Info_Provider.php`.
- **Rollback:** remove the `REGISTRY` entry. A saved option value pointing at it falls back to
  auto-detection.

### Slice 4: Image generation
- `generate_image()` through `/ai/run` with `aips_cf_image_model`, turning the response into a data URI
  that the existing featured-image pipeline can sideload.
- **Gate:** provider test cases for the image fixture, plus the existing image-generation tests that
  run through `AIPS_AI_Service::generate_image`.

### Slice 5: Embeddings
- `generate_embedding()` through `/ai/run`, reporting the model and dimensions used.
- Embeddings may be cached at Cloudflare: identical text gives an identical vector, so a long
  `cf-aig-cache-ttl` makes re-indexing cheaper.
- Index safety: `aips_embeddings` stores `model` and `dimensions` per row. Check that similarity
  queries compare only vectors from the same model. If they don't, add the filter in the repository
  and show "re-index required" in the Internal Links card when the configured model differs from
  the indexed models (`get_index_summary()` already groups by model).
- Reuse `AIPS_Embeddings_Rate_Limiter` and the managed background processes unchanged.
- **Gate:** provider embedding tests, `Test_AIPS_Embeddings_Repository*`, and the
  internal-links/author-embeddings process tests.
- Route the review to `background-job-reviewer` and `db-migration-reviewer` if a repository query
  changes.

### Slice 6: Test-connection AJAX and settings UI polish
- `AIPS_Cloudflare_AI_Gateway_Controller` with action `aips_cf_gateway_test_connection`, registered in
  `AIPS_Ajax_Registry::$map`. It checks the nonce and `manage_options`, calls `verify_token()` and then
  a short chat completion, and returns `AIPS_Ajax_Response` JSON.
- JS module `assets/js/admin-cf-gateway.js` (AIPS module pattern, `AIPS.Utilities.showToast`).
  Show or hide the Cloudflare fields based on the provider dropdown.
- Help text with links to Cloudflare token creation and the required permissions.
- **Gate:** new `Test_AIPS_Cloudflare_AI_Gateway_Controller.php` (nonce failure, missing capability,
  success, Cloudflare error), plus `Test_AIPS_Settings_Ajax.php`. Review with `ajax-controller-reviewer`.

### Slice 7 (later, optional): Cost in AIPS
- Read `GET /accounts/{id}/ai-gateway/gateways/{gw}/logs?filters=[{key:"metadata.value",operator:"eq",value:<correlation_id>}]`
  to show tokens and cost on the History detail view and as a Diagnostics total. The token needs
  *AI Gateway Read*. Cache results in a transient, and never call this on page load for lists.
- Kept separate because it adds a new permission and UI surface and isn't needed to generate posts.

## Open decisions

1. **Payload logging default:** on (debuggable) or off (privacy)?
2. **Default text model:** probably `google/gemini-3-flash`, to match the current Gemini-based dev
   setup. Confirm after Slice 0 pricing and quality checks.
3. **Embedding model:** `@cf/baai/bge-m3` (multilingual, Cloudflare-hosted) is the likely default.
   Switching to it means existing sites using Meow embeddings must re-index.
4. **Dynamic routes:** document them only (the admin types `dynamic/<route>` as the model), or add a
   "fallback route" field? This plan documents them only.

## Out of scope

- Rewriting the requests of the existing Meow or WP AI Client providers to go through the gateway (Option A).
- Streaming responses.
- Background `/ai/run` with webhooks.
- Managing gateways, BYOK keys or dynamic routes from inside WordPress.

## Overlap check (2026-11-25)

Open PRs touching settings (`expand_aips_admin_settings`, `implement_github_issue_2264`,
`fix/email-digest-batching-and-ajax-settings`) could conflict with Slice 1 and Slice 6 in
`class-aips-settings-registry.php` and the settings UI. Rebase on whichever lands first. No open PR
touches the provider layer.
