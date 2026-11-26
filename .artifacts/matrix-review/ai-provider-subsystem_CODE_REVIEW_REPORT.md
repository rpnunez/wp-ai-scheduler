# AI Provider Subsystem — Multi-Level Matrix Code Review Report

- **Target**: AI Provider subsystem on detached HEAD 1eeac641d83d35e816dc7ad9694fe34f06107b08
- **Base Branch**: N/A — subsystem audit on current HEAD
- **Scope**: 20+ subsystem and dependent files audited; no branch diff
- **Review Mode**: Read-only multi-level execution-trace and matrix audit (matrix-review)
- **Overall Verdict**: **NEEDS_HARDENING**

## 1. Executive Summary

### 1.1 High-Level Assessment

AIPS has a clear two-layer contract: application callers depend on AIPS_AI_Service_Interface; AIPS_AI_Service handles common preparation, resilience, logging and call counting; provider adapters implement transport behavior behind AIPS_AI_Provider_Interface. Factory selection is explicit and documented, and both Meow and WordPress AI Client adapters have coherent availability and capability methods.

The cross-path review found two high-impact embedding defects. The WordPress AI Client embedding implementation obtains a cached empty-prompt capability probe instead of building a request for the supplied text, and it bypasses connector routing/failover. Separately, embedding caches and the content indexer's unchanged-content gate do not include provider identity, so changing providers can reuse or retain vectors from a different embedding space. Native JSON error handling also treats unclassified provider exceptions as a signal to issue a second text request, and embedding diagnostics can attribute an internal Meow fallback to the WordPress AI Client.

No source, config, or test files were modified. PHPUnit was not run because this was a read-only review, not an implementation request. All conclusions below are from manual source tracing; there was no live provider/API test.

### 1.2 Defect Tally by Severity

| Severity | Count | Summary of Impact |
| :--- | :---: | :--- |
| Critical | 0 | No confirmed critical fatal, data loss, or security bypass. |
| High | 2 | WordPress AI Client embedding requests can use an empty prompt; provider changes can retain vectors from the previous provider. |
| Medium | 3 | Native JSON provider errors can trigger a second paid request; embedding errors can be classified by the wrong provider; hidden Meow fallback usage is attributed to WordPress AI Client. |
| Low | 0 | No confirmed low-severity defect promoted. |
| **Total** | **5** | Two high-priority correctness risks in embedding paths and three resilience/observability contract gaps. |

### 1.3 Matrix Verdict Summary

| Matrix Group | Total Cases | PASS | PARTIAL / DEFECT | FAIL / DEFECT | FAIL / CRITICAL FATAL |
| :--- | :---: | :---: | :---: | :---: | :---: |
| Selection and settings (SEL) | 9 | 9 | 0 | 0 | 0 |
| Availability and capabilities (CAP) | 2 | 1 | 1 | 0 | 0 |
| Text, JSON and image (TXT, JSON, IMG) | 9 | 8 | 1 | 0 | 0 |
| Embeddings and conversation (EMB, CONV) | 6 | 2 | 4 | 0 | 0 |
| Resilience and accounting (ERR, USE) | 6 | 3 | 3 | 0 | 0 |
| Caller and compatibility (CALL, COMP) | 3 | 2 | 1 | 0 | 0 |
| **Total** | **35** | **25** | **10** | **0** | **0** |

PARTIAL / DEFECT means the ordinary path has a valid implementation but a concrete configuration, fallback, or identity boundary violates the intended contract. No runtime tests were run to experimentally execute these scenarios.

## 2. Severity-Ranked Defects & Suggested Unified Diff Patches

### 2.1 Critical Defects

None found.

### 2.2 High Defects

#### [H-01] WordPress AI Client embedding uses the capability probe instead of the requested text

- **Severity**: High
- **Affected Matrix Scenarios**: CAP-02, EMB-01, EMB-02, EMB-03, EMB-04, ERR-04
- **Location**: ai-post-scheduler/includes/providers/class-aips-wp-ai-client-provider.php:883, especially lines 885–889 and 489–510
- **Lens**: Symbolic trace; caller-callee contract; cross-path parity
- **Evidence & Root Cause**:
  generate_embedding($text, $params) calls $this->create_prompt_builder($text) at line 886. The callee declares zero parameters at line 489 and always constructs its cached builder with create_client_builder('') at line 508. PHP user-defined methods accept the extra argument without passing it into the method body; the resulting builder still has an empty prompt. The adapter then calls generate_embedding() on this builder at lines 887–888. With a native embedding-capable builder that returns a non-empty vector for the empty probe, the subsequent Meow fallback is not reached. The supplied $text is therefore never embedded.

  The same branch does not call execute_with_connector_failover() (used by text/JSON/image at lines 806, 828 and 857) or build_prompt() (which applies the chosen connector and canonical model/options). Thus configured connector selection and failover are not applied to native embedding calls either.
- **Suggested Fix Patch**:

```diff
diff --git a/ai-post-scheduler/includes/providers/class-aips-wp-ai-client-provider.php b/ai-post-scheduler/includes/providers/class-aips-wp-ai-client-provider.php
--- a/ai-post-scheduler/includes/providers/class-aips-wp-ai-client-provider.php
+++ b/ai-post-scheduler/includes/providers/class-aips-wp-ai-client-provider.php
@@ -881,15 +881,25 @@ class AIPS_WP_AI_Client_Provider implements AIPS_AI_Provider_Interface {
     public function generate_embedding(string $text, array $params): array {
-        // 1. Check if the active connector or builder exposes a native embeddings method
-        if ($this->is_available()) {
-            $builder = $this->create_prompt_builder($text);
-            if (is_object($builder) && is_callable(array($builder, 'generate_embedding'))) {
-                $result = $builder->generate_embedding();
-                if (is_array($result) && !empty($result)) {
-                    return $result;
-                }
-            }
-        }
+        // Use the actual request prompt and configured connector routing. The
+        // cached empty-prompt builder is only for capability checks.
+        if ($this->is_available()) {
+            $result = $this->execute_with_connector_failover(function($connector_id) use ($text, $params) {
+                $builder = $this->build_prompt($text, $params, $connector_id);
+                if (!is_callable(array($builder, 'generate_embedding'))) {
+                    return array();
+                }
+                $embedding = $builder->generate_embedding();
+                return is_array($embedding) ? $embedding : array();
+            });
+            if (!empty($result)) {
+                return $result;
+            }
+        }
 
         // 2. If Meow AI Engine is available on the site, fallback gracefully
```

This patch sketch preserves the existing Meow fallback when the WordPress builder exposes no usable vector. Confirm how per-connector capability absence should advance through the connector list when implementing; the critical invariant is that native embedding receives $text and is routed through the configured connector policy.

#### [H-02] Provider changes reuse stale embedding caches and indexed vectors

- **Severity**: High
- **Affected Matrix Scenarios**: EMB-01, EMB-02, EMB-04, COMP-01
- **Location**: ai-post-scheduler/includes/class-aips-embeddings-service.php:189, lines 205–215; ai-post-scheduler/includes/class-aips-content-indexer-service.php:133, lines 136–153
- **Lens**: Symbolic state trace; cross-entry-point and persistence contract parity
- **Evidence & Root Cause**:
  AIPS_Embeddings_Service::generate_embedding() keys its in-memory cache only by md5($text) (line 189), then its persistent raw-vector transient by md5($text . '_' . $model) (line 209). Neither includes aips_embeddings_provider or an explicit provider override. If the text and model remain the same while the selected provider changes, either cache can return the old provider's vector without dispatching to the newly selected provider.

  The persistent index has the same identity gap. AIPS_Content_Indexer_Service::index_post() computes md5($text) and returns early when an existing row has that content hash (lines 133–141), before it reads the configured provider/model at lines 145–153. The embeddings table stores model, dimensions and content hash but no provider field (ai-post-scheduler/includes/class-aips-db-manager.php:541). Thus a provider switch with unchanged content does not regenerate existing vectors. Search/similarity then combines vectors that may belong to incompatible model spaces.
- **Suggested Fix Patch**:

```diff
diff --git a/ai-post-scheduler/includes/class-aips-embeddings-service.php b/ai-post-scheduler/includes/class-aips-embeddings-service.php
--- a/ai-post-scheduler/includes/class-aips-embeddings-service.php
+++ b/ai-post-scheduler/includes/class-aips-embeddings-service.php
@@ -186,10 +186,6 @@ public function generate_embedding($text, $options = array()) {
-        $mem_key = md5($text);
-        if (isset($this->embedding_cache[$mem_key])) {
-            return $this->embedding_cache[$mem_key];
-        }
-
         $default_env_id = (string) $this->config->get_option('aips_embeddings_env_id');
         $default_model  = (string) $this->config->get_option('aips_embeddings_model');
@@ -203,11 +199,18 @@ public function generate_embedding($text, $options = array()) {
         $model = !empty($options['model']) ? (string) $options['model'] : $this->get_active_model();
+        $provider_id = !empty($options['provider'])
+            ? (string) $options['provider']
+            : (string) $this->config->get_option('aips_embeddings_provider', '');
+        $provider_id = AIPS_AI_Provider_Factory::create($provider_id !== '' ? $provider_id : null)->get_id();
+        $mem_key = md5($text . '_' . $provider_id . '_' . $model);
+        if (isset($this->embedding_cache[$mem_key])) {
+            return $this->embedding_cache[$mem_key];
+        }
 
         // Check persistent vector cache (transients / object cache)
         $persistent_cache_enabled = (bool) $this->config->get_option('aips_embeddings_persistent_cache_enabled', true);
-        $transient_key            = 'aips_raw_emb_' . md5($text . '_' . $model);
+        $transient_key            = 'aips_raw_emb_' . md5($text . '_' . $provider_id . '_' . $model);
```

```diff
diff --git a/ai-post-scheduler/includes/class-aips-content-indexer-service.php b/ai-post-scheduler/includes/class-aips-content-indexer-service.php
--- a/ai-post-scheduler/includes/class-aips-content-indexer-service.php
+++ b/ai-post-scheduler/includes/class-aips-content-indexer-service.php
@@ -130,17 +130,20 @@ public function index_post($post_id, $compute_relationships = true) {
-        $content_hash = md5($text);
-        $existing     = $this->embeddings_repo->get_by_post_id($post_id);
-
-        // Skip if text hasn't changed
-        if ($existing && !empty($existing->content_hash) && $existing->content_hash === $content_hash) {
-            if ($compute_relationships) {
-                $this->recompute_relationships_for_post($post_id);
-            }
-            return true;
-        }
-
         $start_time = microtime(true);
         $ai_config  = $this->config->get_ai_config();
         $configured_model = (string) $this->config->get_option('aips_embeddings_model');
@@ -150,6 +153,17 @@ public function index_post($post_id, $compute_relationships = true) {
         $provider = (string) $this->config->get_option('aips_embeddings_provider');
         if ($provider === '') {
             $provider = 'default';
         }
+        $provider_id = AIPS_AI_Provider_Factory::create($provider !== 'default' ? $provider : null)->get_id();
+        // Identify both input content and embedding space. Legacy text-only hashes
+        // intentionally cause a one-time reindex.
+        $content_hash = md5($text . "\0" . $provider_id . "\0" . $model);
+        $existing = $this->embeddings_repo->get_by_post_id($post_id);
+        if ($existing && !empty($existing->content_hash) && $existing->content_hash === $content_hash) {
+            if ($compute_relationships) {
+                $this->recompute_relationships_for_post($post_id);
+            }
+            return true;
+        }
```

The index fingerprint proposal avoids a schema migration and causes a one-time reindex of legacy text-only hashes. A dedicated persisted provider/model identity would be preferable if the vector store needs to distinguish transparent provider-internal fallbacks as well.

### 2.3 Medium Defects

#### [M-01] Unclassified native JSON exceptions trigger an additional text-generation request

- **Severity**: Medium
- **Affected Matrix Scenarios**: JSON-04, USE-02
- **Location**: ai-post-scheduler/includes/class-aips-ai-service.php:302, lines 306–319 and 326–328
- **Lens**: Provider interface return contract; resilience trace; usage accounting
- **Evidence & Root Cause**:
  The provider interface states that native JSON providers return null only to request fallback and throw an exception on backend failure (interface-aips-ai-provider-interface.php:77). However, if extract_error_code() returns an empty string, the service converts any caught exception into WP_Error('json_query_unavailable', ...) at lines 317–319. execute_with_retry() treats that code as a non-fault sentinel and stops retrying (class-aips-resilience-service.php:239); generate_json() then calls text JSON fallback at lines 326–328. A provider failure with an unrecognized message can therefore issue another request, potentially charge twice, and leave the native failure uncounted because the sentinel path does not call log_call().
- **Suggested Fix Patch**:

```diff
diff --git a/ai-post-scheduler/includes/class-aips-ai-service.php b/ai-post-scheduler/includes/class-aips-ai-service.php
--- a/ai-post-scheduler/includes/class-aips-ai-service.php
+++ b/ai-post-scheduler/includes/class-aips-ai-service.php
@@ -302,18 +302,9 @@ public function generate_json($prompt, $options = array()) {
             } catch (Exception $e) {
-                // Unknown exceptions are converted to a fallback sentinel.
                 $provider_code = $this->provider->extract_error_code($e->getMessage());
-                if ($provider_code !== '') {
-                    $error = new WP_Error($provider_code, $e->getMessage());
-                    $this->log_call('json', $prompt, $options, $error);
-                    return $error;
-                }
-                return new WP_Error('json_query_unavailable', $e->getMessage());
+                $error = new WP_Error($provider_code ?: 'generation_failed', $e->getMessage());
+                $this->log_call('json', $prompt, $options, $error);
+                return $error;
             }
```

Fallback remains driven by the provider's documented null return or the existing structural sentinel branch; backend exceptions remain failures and enter normal retry/error accounting.

#### [M-02] Embedding exceptions are classified by the service's primary provider, not the selected embedding provider

- **Severity**: Medium
- **Affected Matrix Scenarios**: EMB-02, ERR-04
- **Location**: ai-post-scheduler/includes/class-aips-ai-service.php:742
- **Lens**: Caller-callee contract; cross-provider parity
- **Evidence & Root Cause**:
  generate_embedding() chooses a request/config-specific $provider at lines 709–710 and invokes it at line 731, but the catch block calls $this->provider->extract_error_code() at line 742. If the embedding provider differs from the primary text provider, provider-specific error syntax is interpreted with the wrong adapter, so retry/permanent-error/circuit classification can be wrong. The text, JSON, image and text-JSON paths use the same active provider they dispatched through; the embedding path is inconsistent.
- **Suggested Fix Patch**:

```diff
diff --git a/ai-post-scheduler/includes/class-aips-ai-service.php b/ai-post-scheduler/includes/class-aips-ai-service.php
--- a/ai-post-scheduler/includes/class-aips-ai-service.php
+++ b/ai-post-scheduler/includes/class-aips-ai-service.php
@@ -739,7 +739,7 @@ public function generate_embedding($text, $options = array()) {
             } catch (Exception $e) {
-                $provider_code = $this->provider->extract_error_code($e->getMessage());
+                $provider_code = $provider->extract_error_code($e->getMessage());
                 $error = new WP_Error($provider_code ?: 'embedding_failed', $e->getMessage());
```

#### [M-03] WordPress AI Client's Meow embedding fallback is reported as a WordPress AI Client call

- **Severity**: Medium
- **Affected Matrix Scenarios**: USE-01, USE-02, EMB-03
- **Location**: ai-post-scheduler/includes/providers/class-aips-wp-ai-client-provider.php:895; ai-post-scheduler/includes/class-aips-ai-service.php:739 and :846
- **Lens**: Usage attribution; cross-provider identity contract
- **Evidence & Root Cause**:
  When the selected adapter is wp_ai_client and native embeddings do not return a usable array, its generate_embedding() calls a new AIPS_Meow_AI_Provider at lines 895–899. The service still holds the selected WordPress adapter in $provider, and log_call(..., $provider) passes that object to count_call(), which records get_id() as wp_ai_client. The actual embedding call can therefore be performed by Meow while provider totals report it as WordPress AI Client. The same distinction is not surfaced to the embeddings caller.
- **Suggested Fix Patch**:

```diff
diff --git a/ai-post-scheduler/includes/providers/class-aips-wp-ai-client-provider.php b/ai-post-scheduler/includes/providers/class-aips-wp-ai-client-provider.php
--- a/ai-post-scheduler/includes/providers/class-aips-wp-ai-client-provider.php
+++ b/ai-post-scheduler/includes/providers/class-aips-wp-ai-client-provider.php
@@ -895,10 +895,7 @@ public function generate_embedding(string $text, array $params): array {
-        // 2. If Meow AI Engine is available on the site, fallback gracefully
-        $meow = new AIPS_Meow_AI_Provider();
-        if ($meow->is_available() && $meow->supports_embeddings()) {
-            return $meow->generate_embedding($text, $params);
-        }
-
-        throw new Exception('embeddings_not_supported: ' . __('The active WordPress AI Client connector does not support vector embeddings, and Meow Apps AI Engine is not available.', 'ai-post-scheduler'));
+        throw new Exception('embeddings_not_supported: ' . __('The active WordPress AI Client connector does not support vector embeddings.', 'ai-post-scheduler'));
@@ -916,9 +913,6 @@ public function supports_embeddings(): bool {
         $builder = $this->create_prompt_builder();
-        if (is_object($builder) && is_callable(array($builder, 'generate_embedding'))) {
-            return true;
-        }
-
-        $meow = new AIPS_Meow_AI_Provider();
-        return $meow->is_available() && $meow->supports_embeddings();
+        return is_object($builder) && is_callable(array($builder, 'generate_embedding'));
```

This keeps the provider ID aligned with the backend actually used: if WP AI Client lacks embedding capability, its capability check is false and an explicit Meow embedding provider must be selected. This preserves provider-level call attribution without expanding the service return shape.

### 2.4 Low Defects

None promoted.

### 2.5 Falsified / Downgraded Candidate Hypotheses

- **Unknown provider ID is not a request-dispatch bypass:** AIPS_AI_Provider_Factory::create() falls through from unknown IDs to configured selection and then autodetection (class-aips-ai-provider-factory.php:45–79). The settings dropdown exposes only registered providers (class-aips-settings-registry.php:682–694). The standalone sanitize_ai_provider() method is not wired to the option definition (repository search found only its declaration); the option uses sanitize_text_field. This leaves a dead sanitizer, but unknown values still fall through safely, so it was not promoted as a functional/security defect.
- **Configured known-but-unavailable provider does not silently switch backends:** factory lines 55–66 returns that provider even when unavailable; the service checks availability and returns ai_unavailable. This matches the documented selection behavior.
- **AI Client local availability check is not a network probe:** is_available() uses configured connector state at lines 71–73, while text capability probing is separate. This avoids remote capability calls in basic settings/status checks; no issue found in that separation.
- **Connector failover catches Throwable inside the WordPress adapter:** execute_with_connector_failover() catches Throwable, classifies it, applies the failover decision and rethrows when exhausted (lines 306–342). The service-level catch(Exception) was not promoted as a general provider fatal because the WordPress adapter normalizes its own throwables on text/JSON/image paths. The embedding call path is specifically defective for other reasons described above.

## 3. Feature Mechanism Map & Architectural Parity Audit

### 3.1 Tier A — Primary Architecture Paths

1. AIPS_AI_Provider_Factory registry maps stable IDs meow and wp_ai_client to provider classes. Selection order is explicit argument, configured aips_ai_provider, first available provider in registry order, then AIPS_Null_AI_Provider (class-aips-ai-provider-factory.php:30–79). It caches provider instances per request (:168–204).
2. Plugin bootstrap binds AIPS_AI_Service::class and AIPS_AI_Service_Interface::class as singletons (ai-post-scheduler.php:384–392). Application services and controllers generally inject the interface, resolving via the container or constructing a fallback service.
3. AIPS_AI_Service prepares canonical text/JSON parameters, enforces provider availability, invokes the resilience service and provider, normalizes failures to WP_Error, logs calls, invokes submission callbacks for text/native JSON transport, and counts through AIPS_AI_Call_Counter (class-aips-ai-service.php:131–207, :221–341, :461–521, :707–779, :841–864).
4. Meow translates canonical values to AI Engine APIs and advertises its current capabilities (providers/class-aips-meow-ai-provider.php:160–208, :213–270, :342–373). WordPress AI Client adapts its fluent builder, selected connector allowlist/failover, system instruction, history and model settings (providers/class-aips-wp-ai-client-provider.php:306–358, :805–879).
5. Settings expose auto-detect and known providers, labeling currently unavailable choices (class-aips-settings-registry.php:674–697). Connector mode and connector IDs are separately sanitized (class-aips-settings-ui.php:1126–1168).
6. Usage is counted by provider ID and call type in aips_ai_call_counts; System Info aggregates provider totals and by-type counts (class-aips-ai-call-counter.php:37–73, class-aips-system-info-provider.php:190–245).

### 3.2 Tier B — Hybrid / Partially Wired Callers

- Primary text/image/JSON uses the service's constructed provider; embeddings can resolve a distinct provider from options['provider'] or aips_embeddings_provider (class-aips-ai-service.php:707–731).
- The WP AI Client adapter provides a second, internal embedding route through Meow. That route is invisible in the interface return type and is not reflected in provider call counts.
- WP AI Client text, JSON and image calls use execute_with_connector_failover; native embeddings do not, despite connector mode/ordered IDs being configurable.
- WP AI Client's native JSON unavailable path returns null; the service additionally synthesizes the same fallback sentinel from unclassified exceptions (class-aips-ai-service.php:302–328). The latter behavior violates the provider interface's exception/null distinction.
- AIPS_AI_Service::supports_embeddings() reflects only the primary generation provider (class-aips-ai-service.php:766–768), while generate_embedding() can use the separately configured embedding provider. The status method therefore does not describe the provider selected for that operation; its practical reach is limited to capability/status consumers.

### 3.3 Tier C — Untouched Dependents and Orphan Reference Sweep

- Service-interface callers include generation, campaign/author topic flows, images, embeddings/indexing, research, content audit, taxonomy, integrations, editor AI tools, similarity, settings AJAX and AI assistance. All use methods declared by AIPS_AI_Service_Interface; the review found no missing called method or mismatched service signature in these paths.
- Direct provider consumers are limited to settings/factory/status and stress-test reporting paths. These consumers use methods declared on AIPS_AI_Provider_Interface.
- Embedding consumers include content indexing, author topic profiles, deduplication, internal links, related posts, similarity evaluation and stress tests. Provider/model identity is not stored in the embedding row schema; the current table stores content_hash, vector, dimensions and model (class-aips-db-manager.php:541–555).
- No retired provider class, method, option or hook was found because this is a subsystem audit without a change diff. Repository-wide searches did find the unused provider sanitizer mentioned above. No source edits or linter-driven verdicts were made.

### 3.4 Cross-Path Contract Parity

| Contract / Parallel Path | Compared Location | Parity Status | Notes |
| :--- | :--- | :--- | :--- |
| Provider selection vs. configured option | Factory create() vs. has_available_provider() | Preserved | Both use configured selection/autodetect; explicit known unavailable choices remain selected. |
| Text/JSON/image connector routing | WP AI Client methods lines 805–879 | Preserved | All use the ordered connector failover wrapper. |
| Native embedding connector routing | WP AI Client generate_embedding() lines 883–901 | **DROPPED** | Does not use the connector failover/routing wrapper. [H-01] |
| Canonical provider embedding input | Interface parameter vs. WP AI Client implementation | **DROPPED** | Empty cached probe builder is used instead of $text. [H-01] |
| Native JSON unavailable vs. backend failure | Provider interface vs. service exception conversion | **Altered** | Unknown exception is treated as a fallback request. [M-01] |
| Selected embedding provider error decoder | Selected $provider vs. $this->provider | **Altered** | Wrong adapter can classify a provider-specific error. [M-02] |
| Actual backend vs. usage identity | WP AI Client internal Meow fallback vs. get_id() count | **Altered** | Call totals can attribute Meow work to wp_ai_client. [M-03] |
| Embedding identity across cache and stored rows | Provider configuration vs. text/model-only keys and skip hash | **DROPPED** | Provider switch can reuse vectors from prior provider. [H-02] |

## 4. Complete Scenario Matrix Execution Traces

The verdicts below are symbolic source traces, not runtime/API test outcomes.

### Group 1 — Selection and settings

| ID | Verdict | Traced path and result |
| :--- | :--- | :--- |
| SEL-01 | **PASS** | Empty configured ID → registry order Meow then WP AI Client → first available provider; service factory and status path call the same selection logic. |
| SEL-02 | **PASS** | Empty configured ID; Meow unavailable → loop continues → available WP AI Client selected. |
| SEL-03 | **PASS** | Both unavailable → no loop match → null provider; service availability guard returns ai_unavailable before transport; unsent error is excluded from counts. |
| SEL-04 | **PASS** | Valid explicit ID → instantiate(id) succeeds → explicit adapter returned before reading configured option. |
| SEL-05 | **PASS** | Unknown explicit ID → instantiate returns null → configured provider is attempted; if absent/unknown, auto-detection follows. |
| SEL-06 | **PASS** | Unknown configured ID → instantiate returns null → factory auto-detects available registry provider. |
| SEL-07 | **PASS** | Known configured ID, unavailable → factory returns that adapter immediately → service returns unavailable error; it does not silently use another backend. |
| SEL-08 | **PASS** | Missing registered class → instantiate caches null → availability/all listings omit it → auto-detection can continue to another registered adapter. |
| SEL-09 | **PASS** | Empty setting is accepted as auto-detect; unknown non-empty setting falls through factory; values are text-sanitized by the option registration. The extra known-ID sanitizer is unused but not needed for safe dispatch. |

### Group 2 — Availability and capability contracts

| ID | Verdict | Traced path and result |
| :--- | :--- | :--- |
| CAP-01 | **PASS** | Missing Meow core or WordPress connector/config returns unavailable; service guards before provider transport and returns ai_unavailable. |
| CAP-02 | **PARTIAL / DEFECT** | Capability flags match most methods; however WP AI Client native embedding says supported from a probe builder then uses the same empty builder rather than request text and does not route connector IDs. [H-01] |

### Group 3 — Text, JSON and image requests

| ID | Verdict | Traced path and result |
| :--- | :--- | :--- |
| TXT-01 | **PASS** | Empty/short text still follows service parameter prep and provider dispatch; empty provider output becomes empty_response rather than success. |
| TXT-02 | **PASS** | Prepared model, temperature, tokens, context/instructions and messages are mapped by each adapter; WordPress builder applies context/instructions/history/model preference. |
| TXT-03 | **PASS** | Empty provider text is converted to empty_response; exception errors pass through provider decoder and normal retry/error logging. |
| JSON-01 | **PASS** | Native provider returns decoded array; service rejects empty data as empty_response, otherwise logs and returns the array. |
| JSON-02 | **PASS** | Capability false → service uses text JSON extraction; no native JSON call is issued. |
| JSON-03 | **PASS** | Provider returns documented null → sentinel exits retry loop → text JSON fallback happens outside nested resilience call and shares submission callback. |
| JSON-04 | **PARTIAL / DEFECT** | Unknown exception from native JSON is converted to fallback sentinel; service issues a second text request instead of treating exception as backend failure. [M-01] |
| IMG-01 | **PASS** | Adapter returns image URL/data URI; service rejects empty result, truncates long data URI only for logs, and returns full successful value. |
| IMG-02 | **PASS** | Unsupported/error image path is converted to a provider-code WP_Error; permanent capability failures are excluded from retries/circuit penalty. |

### Group 4 — Embeddings and conversation

| ID | Verdict | Traced path and result |
| :--- | :--- | :--- |
| EMB-01 | **PARTIAL / DEFECT** | Default provider is selected and embedding options forwarded; WP AI native path consumes the empty cached probe builder, not input text. [H-01] |
| EMB-02 | **PARTIAL / DEFECT** | Explicit/configured provider dispatch works; error decoder uses primary $this->provider rather than selected $provider; caches omit selected provider identity. [H-02], [M-02] |
| EMB-03 | **PARTIAL / DEFECT** | Unsupported explicit provider returns embeddings_not_supported; WP adapter may instead fall back to Meow invisibly and count under WP ID. [M-03] |
| EMB-04 | **PARTIAL / DEFECT** | Empty response is rejected at service boundary, but empty text is primarily screened in Embeddings Service. Text/model caches and persistent index identity omit provider, allowing prior-provider vectors to be reused. [H-02] |
| CONV-01 | **PASS** | Canonical ordered messages are mapped to Meow roles or WP message DTOs; provider supports conversation only when its requirements are met. |
| CONV-02 | **PASS** | Unsupported providers report false; callers can build self-contained prompts, and generic JSON text fallback is used when native path cannot carry conversation. |

### Group 5 — Resilience and usage accounting

| ID | Verdict | Traced path and result |
| :--- | :--- | :--- |
| ERR-01 | **PASS** | Provider exception is converted to WP_Error; resilience retries retryable codes up to configured attempts, records one circuit result per logical execute_safely() call. |
| ERR-02 | **PASS** | Recognized permanent/request errors stop retry; WP connector adapter does not fail over content-policy/invalid-request codes. |
| ERR-03 | **PASS** | Open circuit/local rate limit exits before callback/transport; these local codes are excluded from provider call counts. |
| ERR-04 | **PARTIAL / DEFECT** | Embedding calls may use a different provider than the active text adapter, but errors are classified by the latter; WP embedding internal Meow fallback also hides actual provider identity. [M-02], [M-03] |
| USE-01 | **PARTIAL / DEFECT** | log_call() counts by the provider object passed; WP adapter's internal Meow fallback returns only vector, so service counts the outer wp_ai_client ID. [M-03] |
| USE-02 | **PARTIAL / DEFECT** | Normal provider failures and retries are counted through log calls; unclassified JSON native exceptions become an uncounted sentinel followed by a second counted text call. [M-01] |

### Group 6 — Callers and compatibility

| ID | Verdict | Traced path and result |
| :--- | :--- | :--- |
| CALL-01 | **PASS** | Container binds concrete and interface service singletons; consumers call methods declared on AIPS_AI_Service_Interface with expected option/result shapes. |
| CALL-02 | **PASS** | Dashboard/onboarding/status and diagnostics use factory selection methods directly; unavailable reason and provider capability details are sourced from current registered adapters. Embedding-specific status caveats are captured by CAP-02. |
| COMP-01 | **PARTIAL / DEFECT** | Stable provider IDs and auto-detect remain backward compatible, but a provider switch does not invalidate same-text/model caches or existing content index records. [H-02] |

## 5. Read-Only Integrity and Verification Limits

- No tracked source, configuration, or test file was modified.
- No PHPUnit or live AI-provider calls were executed. This report is based on manual source inspection and symbolic call-path tracing.
- The WordPress AI Client path could not be confirmed against a live WordPress 7 connector runtime; the input and routing defect follows directly from the local method signatures and builder construction shown above.
- Final repository verification: git diff and git diff --cached are empty; .artifacts/matrix-review/ is ignored, leaving git status --short clean.
