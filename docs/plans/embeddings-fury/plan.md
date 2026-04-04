## Plan: Embeddings and AI Engine Expansion Roadmap

Prioritize discovery and analytics features in a 1-3 week execution window by extending existing in-plugin embeddings patterns first (topic expansion architecture, repository/controller/history patterns), then layering optional advanced capabilities that can evolve toward vector infrastructure later without coupling the core plugin to external services.

**Steps**
1. Phase 1 - Baseline and instrumentation hardening (Week 1): Confirm and document current embeddings lifecycle across topic generation, topic expansion, and feedback workflows; add explicit lifecycle history events for embedding generation/reuse/fallback paths; define a single embeddings configuration surface (thresholds, model identifier, fail-open/fail-closed behavior). This creates observability and guards before adding net-new semantic features.
2. Phase 2 - Discovery/analytics quick wins (Week 1-2): Add semantic deduplication and retrieval where users already work. Implement trending-topic semantic dedupe (embedding similarity versus string-only heuristics) and semantic template similarity suggestions in scheduling/template workflows. Both reuse existing embeddings service methods and avoid new infrastructure. *parallelizable within phase after shared metadata conventions are set in Step 1*.
3. Phase 3 - Semantic search UX (Week 2): Add generated-post semantic search (title/excerpt/content embedding lookup) behind an AJAX endpoint and admin UI filter in Generated Posts. Persist post embeddings at generation-time and expose nearest-neighbor retrieval with configurable top-K and threshold. *depends on Step 1 metadata/logging conventions*.
4. Phase 4 - Author/topic intelligence layer (Week 2-3): Build matching service that links trending topics to author topical vectors and recommends schedule candidates. Run as scheduled background enrichment plus on-demand admin trigger. Reuse current scheduler/history patterns and topic expansion similarity primitives. *depends on Step 2 embedding coverage for trending topics*.
5. Phase 5 - Optional architecture runway (Week 3): Introduce abstraction boundary for vector retrieval so the plugin defaults to MySQL/metadata storage but can later route similarity queries to an external vector backend. Keep this feature-flagged and non-blocking for current users. *parallel with late testing once Steps 2-4 are stable*.
6. Verification and rollout: Add PHPUnit coverage for service/repository logic, controller permission/nonce behavior, and threshold edge cases; run targeted admin manual QA for topic pages, research page, generated posts filtering, and failure fallback when embeddings are unavailable. Roll out behind settings toggles and safe defaults.

**Relevant files**
- c:/Projects/NunezScheduler/wp-ai-scheduler/ai-post-scheduler/includes/class-aips-embeddings-service.php - Reuse and extend shared embedding generation, similarity, neighbor search, and support checks.
- c:/Projects/NunezScheduler/wp-ai-scheduler/ai-post-scheduler/includes/class-aips-topic-expansion-service.php - Reuse topic similarity and expanded context patterns for new analytics retrieval features.
- c:/Projects/NunezScheduler/wp-ai-scheduler/ai-post-scheduler/includes/class-aips-author-topics-generator.php - Existing duplicate detection integration point and metadata write path to mirror in new semantic dedupe flows.
- c:/Projects/NunezScheduler/wp-ai-scheduler/ai-post-scheduler/includes/class-aips-research-service.php - Add trending-topic embedding/dedupe logic.
- c:/Projects/NunezScheduler/wp-ai-scheduler/ai-post-scheduler/includes/class-aips-trending-topics-repository.php - Persist trending-topic embedding metadata and retrieval helpers.
- c:/Projects/NunezScheduler/wp-ai-scheduler/ai-post-scheduler/includes/class-aips-template-repository.php - Template embedding storage and similarity lookup methods.
- c:/Projects/NunezScheduler/wp-ai-scheduler/ai-post-scheduler/includes/class-aips-templates-controller.php - AJAX endpoint(s) for semantic template suggestions.
- c:/Projects/NunezScheduler/wp-ai-scheduler/ai-post-scheduler/includes/class-aips-generator.php - Generation-time post embedding persistence hook.
- c:/Projects/NunezScheduler/wp-ai-scheduler/ai-post-scheduler/includes/class-aips-history-repository.php - Semantic post retrieval query surface and analytics retrieval support.
- c:/Projects/NunezScheduler/wp-ai-scheduler/ai-post-scheduler/includes/class-aips-generated-posts-controller.php - AJAX semantic search endpoint + capability/nonce/sanitization handling.
- c:/Projects/NunezScheduler/wp-ai-scheduler/ai-post-scheduler/templates/admin/generated-posts.php - Add semantic search controls in existing page shell.
- c:/Projects/NunezScheduler/wp-ai-scheduler/ai-post-scheduler/assets/js/admin-generated-posts.js - Client-side semantic search requests/results rendering using existing JS module conventions.
- c:/Projects/NunezScheduler/wp-ai-scheduler/ai-post-scheduler/includes/class-aips-history-service.php - Ensure structured lifecycle observability for all embedding-powered operations.
- c:/Projects/NunezScheduler/wp-ai-scheduler/ai-post-scheduler/includes/class-aips-settings.php - Register toggles/threshold/model settings and sanitization.

**Verification**
1. Run focused PHPUnit tests for embeddings service similarity math, nearest-neighbor ranking, and fallback when Meow embeddings class is unavailable.
2. Run PHPUnit tests for repositories/controllers covering metadata persistence, nonce/capability checks, and invalid threshold/model input sanitization.
3. Manual admin QA in Research, Author Topics, Templates/Schedule, and Generated Posts pages to confirm semantic dedupe and retrieval produce expected ranked results.
4. Validate history events for each embedding flow stage (request, success, skipped, fallback, error) are written and visible.
5. Performance sanity check with representative batch sizes to ensure no blocking regressions in AJAX paths.

**Decisions**
- Prioritized scope: discovery/analytics integrations first over chat UX.
- Timebox: balanced 1-3 week roadmap.
- Storage strategy: in-plugin metadata/query approach first, with optional external vector backend abstraction later.
- Included: semantic dedupe, semantic retrieval/search, author-topic matching, observability hardening.
- Excluded for this roadmap window: full conversational assistant UX and full external vector migration.

**Further Considerations**
1. Threshold policy recommendation: global default plus per-feature override (topic dedupe vs. search relevance) to avoid one threshold harming all use cases.
2. Backfill strategy recommendation: asynchronous WP-Cron batch embedding for existing records, with progress visibility in admin status.
3. Cost-control recommendation: cache embeddings by normalized text hash and add per-run call caps to avoid quota spikes.