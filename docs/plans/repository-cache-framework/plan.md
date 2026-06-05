# Phased implementation plan for repository-scale caching

## Guiding design

Use a **small shared repository caching framework**, not invisible method interception.

Recommended call style instead of `__FUNCTION__`:

```php
return $this->cache_read(
	'authors.get_by_id',
	array(
		'author_id' => absint( $id ),
	),
	function() use ( $id ) {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$id
			)
		);
	}
);
```

Benefits:

- The cache policy ID is explicit and searchable.
- Renaming a PHP method does not accidentally change cache behavior.
- The same method can have multiple cache modes if needed.
- Policies can be validated centrally.
- Observability logs can use human-readable operation names like `authors.get_by_id`.

---

## Phase 0 — Add observability first

Before caching more repositories, add instrumentation so rollout decisions are based on measured cache behavior: hit rate, miss rate, bypasses, stale reads, invalidations, and slow cache rebuilds.

:::task-stub{title="Add repository cache observability primitives"}
Create repository-cache-level observability before adding broad caching.

Implement a small class in `ai-post-scheduler/includes/class-aips-repository-cache-observer.php`.

It should expose methods such as:

- `record_read( array $event )`
- `record_write( array $event )`
- `record_invalidation( array $event )`
- `record_bypass( array $event )`

Each event should support:

- repository class
- cache operation ID, for example `authors.get_by_id`
- cache group
- key hash, not the full key
- tags
- tier
- hit, miss, stale, bypass
- elapsed milliseconds
- invalidation reason
- correlation ID if available through `AIPS_Correlation_Id`

Prefer routing through existing `AIPS_Logger_Interface` or telemetry conventions instead of introducing ad-hoc output.

Do not block cache reads/writes if observability fails.
:::

---

## Phase 1 — Normalize cache keys centrally

Build the key builder before the trait/policy layer. This avoids inconsistent keys across repositories.

:::task-stub{title="Create a stable repository cache key builder"}
Create `ai-post-scheduler/includes/class-aips-repository-cache-key-builder.php`.

Implement a static or injectable key builder with methods such as:

- `build_key( string $operation_id, array $args, array $tag_versions = array(), array $context = array() ): string`
- `normalize_args( array $args ): array`
- `hash_args( array $args ): string`

Normalization rules:

- recursively sort associative arrays by key
- preserve indexed array order
- normalize booleans to `0` / `1`
- cast numeric IDs consistently where the caller provides typed argument names like `author_id`, `topic_id`, `template_id`
- normalize empty filters to a stable value
- include pagination, sort, status, search, and date filters
- include current blog ID when `get_current_blog_id()` exists
- include plugin/schema cache version, for example `AIPS_VERSION` or a dedicated repository-cache schema version

Output keys should include:

- operation ID
- normalized args hash
- tag-version hash
- context hash if present

Add PHPUnit coverage for equivalent argument arrays generating identical keys.
:::

---

## Phase 2 — Add versioned tag support to `AIPS_Cache`

Do not rely on driver-level tag support. Implement tags as normal cache keys containing integer versions.

:::task-stub{title="Add versioned cache tag APIs to AIPS_Cache"}
Update `ai-post-scheduler/includes/class-aips-cache.php`.

Add high-level methods:

- `public function get_tag_version( $tag, $group = 'default' )`
- `public function bump_tag_version( $tag, $group = 'default' )`
- `public function get_tag_versions( array $tags, $group = 'default' )`
- `public function bump_tag_versions( array $tags, $group = 'default' )`

Implementation notes:

- Store tag versions as ordinary cache keys, for example `tag_version:{tag}`.
- Default missing tag versions to `1`.
- Use `increment()` where possible.
- Sanitize tag strings to a stable safe key format.
- Record tag bump events through the new repository cache observer.
- Do not require changes to existing cache drivers.

Add tests confirming that bumping a tag changes the generated tag-version set without deleting existing cached values.
:::

---

## Phase 3 — Define cache tiers

Separate request-only memoization from persistent cache storage.

Suggested tiers:

| Tier | Driver | Default TTL | Use case |
|---|---:|---:|---|
| `request` | array | `0` | repeated reads in one request |
| `short` | configured driver | `60` | admin counters, dashboard cards |
| `medium` | configured driver | `300` | authors, templates, voices, structures |
| `long` | configured driver | `3600` | rarely changing lookup/config data |
| `none` | none | `0` | locks, queues, claim-next-job flows |

:::task-stub{title="Add repository cache tier configuration"}
Create `ai-post-scheduler/includes/class-aips-repository-cache-config.php`.

Define tier defaults:

- `request`
- `short`
- `medium`
- `long`
- `none`

Each tier should resolve:

- cache driver/name
- default TTL
- whether persistent storage is allowed
- whether cron should bypass by default
- whether stale reads are allowed

Expose methods such as:

- `get_tier_config( string $tier ): array`
- `resolve_ttl( array $policy ): int`
- `resolve_cache_instance( string $group, array $policy ): AIPS_Cache|null`

Use `AIPS_Cache_Factory::named()` for persistent tiers and an array-driver named cache for request-only memoization.
:::

---

## Phase 4 — Add the repository caching trait

The trait should provide mechanics only. It should not guess method names or infer SQL behavior.

Repository methods should call an explicit operation ID such as `authors.get_all`, `authors.get_by_id`, or `author_topics.get_status_counts`.

:::task-stub{title="Create AIPS_Cacheable_Repository trait with explicit operation IDs"}
Create `ai-post-scheduler/includes/trait-aips-cacheable-repository.php`.

Implement shared methods:

- `protected function cache_read( string $operation_id, array $args, callable $callback, array $options = array() )`
- `protected function cache_bypass_read( string $operation_id, array $args, callable $callback, array $options = array() )`
- `protected function invalidate_cache_domain( string $domain, array $context = array(), string $reason = '' )`
- `protected function invalidate_cache_tags( array $tags, string $reason = '' )`
- `protected function repository_cache_group(): string`
- `protected function repository_cache_policies(): array`

Important behavior:

- Look up policy by explicit `$operation_id`.
- Build keys through `AIPS_Repository_Cache_Key_Builder`.
- Resolve tier/TTL through `AIPS_Repository_Cache_Config`.
- Resolve tag versions through `AIPS_Cache`.
- Record hit/miss/bypass/rebuild timing through `AIPS_Repository_Cache_Observer`.
- Support `cache_null` in policy.
- Support `force_refresh` and `bypass_cache` options.
- Avoid `__FUNCTION__`.
:::

---

## Phase 5 — Add declarative read policies

Each repository should declare policies in one place, using explicit operation IDs.

Example shape:

```php
protected function repository_cache_policies(): array {
	return array(
		'authors.get_all' => array(
			'tier' => 'medium',
			'ttl'  => 300,
			'tags' => array( 'authors' ),
		),
		'authors.get_by_id' => array(
			'tier'       => 'medium',
			'tags'       => array( 'authors', 'author:{author_id}' ),
			'cache_null' => false,
		),
		'authors.get_due_for_topic_generation' => array(
			'tier'        => 'none',
			'bypass_cron' => true,
		),
	);
}
```

:::task-stub{title="Implement explicit repository cache policies"}
Add policy support to `AIPS_Cacheable_Repository`.

Policy requirements:

- Policies are keyed by explicit operation IDs, not PHP method names.
- Tags can contain named placeholders such as `author:{author_id}`.
- Missing policy should default to uncached behavior unless explicitly overridden.
- Unknown placeholders should trigger an observer warning but not fatal.
- Policy should support:
  - `tier`
  - `ttl`
  - `tags`
  - `cache_null`
  - `bypass_cron`
  - `bypass_ajax`
  - `allow_stale`
  - `description`

Apply initial policies only to `AIPS_Authors_Repository` read methods:

- `authors.get_all`
- `authors.get_by_id`

Leave volatile due-generation reads uncached in the first pass.
:::

---

## Phase 6 — Add central dependency map

This is the cross-repository invalidation layer. Repositories should invalidate domains, not individual cache keys.

:::task-stub{title="Create central repository cache dependency map"}
Create `ai-post-scheduler/includes/class-aips-repository-cache-dependencies.php`.

Implement domain-to-tag expansion methods:

- `tags_for_read( string $operation_id, array $args = array() ): array`
- `tags_for_invalidation( string $domain, array $context = array() ): array`

Start with author-related domains:

- `author`
- `author_topic`
- `author_topic_log`
- `post_generation`
- `dashboard`
- `unified_schedule`

Initial invalidation examples:

For `author` with `author_id`:

- `authors`
- `author:{author_id}`
- `author_generation_schedule`
- `dashboard_counts`
- `unified_schedule`

For `author_topic` with `author_id` and optional `topic_id`:

- `author_topics`
- `author_topics:author:{author_id}`
- `author_generation_summary:{author_id}`
- `author_post_queue:{author_id}`
- `dashboard_counts`
- `author_topic:{topic_id}` when present

For `post_generation` with `author_id`, `topic_id`, and optional `post_id`:

- `author:{author_id}`
- `author_topics:author:{author_id}`
- `author_topic:{topic_id}`
- `author_topic_logs`
- `history`
- `dashboard_counts`
- `unified_schedule`

Keep this class intentionally boring and searchable.
:::

---

## Phase 7 — Convert `AIPS_Authors_Repository` as the first pilot

Use one repository to prove the pattern before broad rollout.

:::task-stub{title="Pilot repository caching in AIPS_Authors_Repository"}
Update `ai-post-scheduler/includes/class-aips-authors-repository.php`.

Changes:

- Add `use AIPS_Cacheable_Repository;`
- Replace manual `$this->cache->has()`, `$this->cache->get()`, `$this->cache->set()`, and `$this->cache->flush()` usage.
- Convert `get_all()` to call:

  - operation ID: `authors.get_all`
  - args: `array( 'active_only' => (bool) $active_only )`

- Convert `get_by_id()` to call:

  - operation ID: `authors.get_by_id`
  - args: `array( 'author_id' => absint( $id ) )`

- Convert mutation methods to use domain invalidation:
  - `create()` invalidates `author` without a specific ID plus dashboard/schedule tags.
  - `update( $id, $data )` invalidates `author` with `author_id`.
  - `delete( $id )` invalidates `author` with `author_id`.
  - generation active/schedule mutators invalidate `author` with `author_id`.

Keep `get_due_for_topic_generation()` and `get_due_for_post_generation()` uncached initially.
:::

---

## Phase 8 — Add bypass rules for cron, queues, and due-item queries

Do this before converting schedule, job, or generation repositories.

:::task-stub{title="Add cache bypass support for cron and queue-sensitive reads"}
Add context-aware bypass handling to `AIPS_Cacheable_Repository`.

Bypass when:

- policy tier is `none`
- options include `bypass_cache => true`
- options include `force_refresh => true`
- `wp_doing_cron()` is true and policy has `bypass_cron => true`
- method is queue-sensitive or lock-sensitive

Add helper methods:

- `protected function repository_cache_should_bypass( array $policy, array $options = array() ): bool`
- `protected function without_repository_cache( callable $callback )`

Recommended initial uncached operation IDs:

- `authors.get_due_for_topic_generation`
- `authors.get_due_for_post_generation`
- `author_topics.get_approved_for_generation`
- job/slice/queue claim operations in `includes/job/`
- retry selection queries
- batch-processing slice queries

Record bypass events through `AIPS_Repository_Cache_Observer`.
:::

---

## Phase 9 — Convert author-topic reads and invalidation

After the author pilot works, move to the related repository because it is the best validation of cross-repository dependency invalidation.

:::task-stub{title="Apply repository cache framework to AIPS_Author_Topics_Repository"}
Update `ai-post-scheduler/includes/class-aips-author-topics-repository.php`.

Add cache policies for stable/admin-facing reads first:

- `author_topics.get_by_author`
- `author_topics.get_by_id`
- `author_topics.get_approved_summary`
- `author_topics.get_rejected_summary`
- `author_topics.get_status_counts`
- `author_topics.get_global_status_counts`
- `author_topics.get_counts_grouped_by_author`
- `author_topics.get_daily_topic_counts`

Keep generation-sensitive reads uncached or short-lived:

- `author_topics.get_approved_for_generation`
- `author_topics.get_all_approved_for_queue`

Invalidate domains from mutators:

- `create()`
- `create_bulk()`
- `update()`
- `update_status()`
- `delete()`
- `delete_by_author()`

Use `author_topic` invalidation with `author_id` and `topic_id` whenever available.
:::

---

## Phase 10 — Roll out by repository category, not all at once

Recommended conversion order:

1. Authors and author topics
2. Dashboard/count repositories
3. Templates, voices, structures, prompt sections
4. Schedule read models
5. Sources and source data
6. Internal links
7. History and telemetry, with extra caution
8. Job/queue repositories only where reads are demonstrably safe

:::task-stub{title="Roll out repository caching by risk category"}
Create a tracking document or issue list for repository cache adoption.

Group repositories by cache risk:

Low risk:

- `AIPS_Template_Repository`
- `AIPS_Voices_Repository`
- `AIPS_Article_Structure_Repository`
- `AIPS_Prompt_Section_Repository`

Medium risk:

- `AIPS_Schedule_Repository`
- `AIPS_Sources_Repository`
- `AIPS_Sources_Data_Repository`
- `AIPS_Internal_Links_Repository`
- `AIPS_Taxonomy_Repository`

High risk:

- `AIPS_History_Repository`
- `AIPS_Telemetry_Repository`
- job and batch queue classes
- due/claim/retry generation queries

For each repository, add policies only for read methods that are:

- deterministic for the provided arguments
- not lock-sensitive
- not responsible for claiming work
- invalidated by a known domain event
:::

---

## Phase 11 — Add tests for correctness and safety

The caching framework needs targeted tests before broad conversion.

:::task-stub{title="Add PHPUnit coverage for repository cache framework"}
Add tests under `ai-post-scheduler/tests/`.

Recommended test files:

- `test-repository-cache-key-builder.php`
- `test-repository-cache-tags.php`
- `test-repository-cache-dependencies.php`
- `test-cacheable-repository-trait.php`
- `test-authors-repository-cache.php`

Test cases:

- equivalent args produce identical keys
- different filters produce different keys
- tag version bump changes generated cache key
- invalidating `author` bumps expected tags
- `authors.get_by_id` hits cache after first read
- `authors.update` invalidates `authors.get_by_id`
- cron bypass skips cache for due-generation reads
- `cache_null => false` does not store missing rows
- request tier does not persist across cache factory reset
:::

---

## Recommended implementation sequence

1. `AIPS_Repository_Cache_Observer`
2. `AIPS_Repository_Cache_Key_Builder`
3. Versioned tag methods in `AIPS_Cache`
4. `AIPS_Repository_Cache_Config`
5. `AIPS_Cacheable_Repository`
6. `AIPS_Repository_Cache_Dependencies`
7. Pilot in `AIPS_Authors_Repository`
8. Add cron/queue bypass policies
9. Expand to `AIPS_Author_Topics_Repository`
10. Add tests
11. Roll out repository-by-repository