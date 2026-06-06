## Admin Cache Monitor

Build a dedicated **Cache Monitor** subsystem that lets administrators inspect, understand, and safely operate the plugin cache.

The Cache Monitor should support:

- Viewing cached objects and metadata.
- Searching/filtering by group, key hash, tag, repository operation, tier, driver, and TTL state.
- Seeing cache size and item counts.
- Inspecting safe previews of cached values.
- Flushing individual cache entries.
- Flushing cache groups.
- Invalidating tags/domains.
- Flushing all plugin caches.
- Viewing repository cache activity: hits, misses, bypasses, stale reads, invalidations.
- Driver-specific capabilities and limitations.

:::task-stub{title="Create a dedicated Cache Monitor admin subsystem"}
Create a Cache Monitor subsystem for AI Post Scheduler.

Add:

- `ai-post-scheduler/includes/class-aips-cache-monitor-controller.php`
- `ai-post-scheduler/includes/class-aips-cache-monitor-service.php`
- `ai-post-scheduler/includes/class-aips-cache-monitor-repository.php`
- `ai-post-scheduler/templates/admin/cache-monitor.php`

Register the page through `AIPS_Admin_Menu::add_menu_pages()` as either:

- a dedicated submenu item named `Cache Monitor`, or
- a tab/page under System Status or Dev Tools if the team wants it hidden from normal admins.

Require `current_user_can( 'manage_options' )`.

Use nonce checks for every destructive action.
:::

---

### 1. Add cache inventory support

The existing `AIPS_Cache` API is good for `get`, `set`, `delete`, and `flush`, but a monitor needs introspection. Not every driver can list keys equally well, so the system should expose **capabilities** per driver.

Recommended driver capabilities:

| Capability | Meaning |
|---|---|
| `list_keys` | Driver can list plugin cache keys |
| `inspect_entry` | Driver can read metadata/value preview |
| `delete_key` | Driver can delete one key |
| `delete_group` | Driver can delete a group |
| `flush_plugin` | Driver can flush all plugin-owned cache |
| `size_bytes` | Driver can estimate size |
| `ttl_remaining` | Driver can report TTL |
| `tag_versions` | Driver can list tag-version keys |
| `live_metrics` | Driver can expose hit/miss counters |

:::task-stub{title="Add cache driver introspection capabilities"}
Create a cache monitor introspection contract.

Add an interface such as `ai-post-scheduler/includes/interface-aips-cache-monitorable-driver.php`.

Define methods like:

- `get_monitor_capabilities(): array`
- `list_entries( array $filters = array(), $limit = 100, $offset = 0 ): array`
- `count_entries( array $filters = array() ): int`
- `get_entry_metadata( string $key, string $group = 'default' ): array`
- `delete_entry( string $key, string $group = 'default' ): bool`
- `delete_group( string $group ): bool`
- `estimate_size( array $filters = array() ): array`

Implement this interface where practical for:

- `AIPS_Cache_Db_Driver`
- `AIPS_Cache_Array_Driver`
- `AIPS_Cache_Redis_Driver`
- `AIPS_Cache_Wp_Object_Cache_Driver`
- `AIPS_Cache_Session_Driver`

For drivers that cannot safely list keys, return capabilities indicating the limitation and show a clear UI message.
:::

---

### 2. Maintain a cache index for non-listable drivers

Some cache backends, especially object cache or Redis in shared environments, may not support safe key listing. To make the Cache Monitor reliable, maintain a plugin-owned **cache index** whenever `AIPS_Cache::set()` is called.

The index should track metadata, not necessarily the cached value.

Suggested metadata:

- key
- key hash
- group
- driver
- tier
- operation ID
- repository class
- tags
- domain
- created at
- updated at
- expires at
- TTL
- approximate serialized size
- value type
- source: repository cache, config cache, template cache, etc.

:::task-stub{title="Add a plugin-owned cache index for monitorability"}
Create a cache index layer so the Cache Monitor can list cache entries even when the underlying driver cannot.

Add `ai-post-scheduler/includes/class-aips-cache-index.php`.

The index should record metadata on cache writes and deletes.

Track:

- cache key
- key hash
- group
- driver name
- cache tier
- operation ID if available
- repository class if available
- tags
- domain
- TTL
- created timestamp
- expires timestamp
- estimated serialized size
- value type
- last access timestamp when practical

Use the existing `aips_cache` table only if its schema can safely support this metadata. Otherwise, add a dedicated schema through `AIPS_DB_Manager::get_schema()`, for example `aips_cache_index`.

Update `AIPS_Cache::set()`, `AIPS_Cache::delete()`, and `AIPS_Cache::flush()` to update the index.
:::

---

### 3. Add cache size and health summaries

The top of the Cache Monitor should show operational health at a glance.

Recommended dashboard cards:

- Cache system enabled/disabled.
- Active driver.
- Total indexed entries.
- Estimated total cache size.
- Expired entries count.
- Entries by group.
- Entries by tier.
- Entries by repository.
- Hit/miss ratio over the last period.
- Bypass count.
- Last flush time.
- Largest cache entries.
- Slowest cache rebuild operations.
- Most-invalidated tags/domains.

:::task-stub{title="Add Cache Monitor summary metrics"}
Implement summary metrics in `AIPS_Cache_Monitor_Service`.

Expose a method such as:

- `get_summary(): array`

Include:

- cache enabled state
- active configured driver
- driver capabilities
- total indexed entries
- total estimated size
- expired indexed entries
- entries by cache group
- entries by cache tier
- entries by repository operation
- largest entries
- recent hit/miss/bypass counts from repository cache observability
- most frequently invalidated tags/domains
- last plugin cache flush timestamp

Render these metrics at the top of `templates/admin/cache-monitor.php`.
:::

---

### 4. Add a searchable cache entries table

The main Cache Monitor view should be a table of cache entries.

Suggested columns:

- checkbox
- key hash
- group
- operation ID
- tier
- driver
- tags
- value type
- estimated size
- TTL remaining
- expires at
- created at
- last accessed
- actions

Suggested filters:

- group
- tier
- driver
- operation ID
- repository
- tag
- TTL state: active / expired / no expiration
- size range
- search key hash or operation ID

:::task-stub{title="Build searchable cache entries table"}
Create a cache entries list table for the admin Cache Monitor.

Use either `WP_List_Table` or a custom admin table in `templates/admin/cache-monitor.php`.

Support:

- pagination
- sorting by size, TTL, created date, expires date, group, operation ID
- filtering by group, tier, driver, repository, operation ID, and tag
- search by key hash, group, operation ID, and tag
- row actions for inspect, delete, and copy key hash
- bulk actions for delete selected, invalidate selected tags, and flush selected group

Back the table with `AIPS_Cache_Monitor_Service` and `AIPS_Cache_Index`.
:::

---

### 5. Add safe value inspection

Admins may want to inspect what is cached, but cached values can contain large data or sensitive content. The monitor should show a safe preview by default.

Rules:

- Never dump full large values by default.
- Redact obvious secrets/API keys/tokens.
- Limit preview length.
- Show type and structure summary.
- For objects/arrays, show top-level keys and counts.
- Allow full raw view only behind a separate confirmation and only in dev/debug mode.

:::task-stub{title="Add safe cache value inspection"}
Add an inspect view/action in `AIPS_Cache_Monitor_Controller`.

For a selected cache entry, display:

- key hash
- group
- driver
- TTL
- created/expires timestamps
- tags
- operation ID
- value type
- estimated size
- safe preview

Implement value preview rules:

- arrays: show top-level keys, counts, and truncated scalar values
- objects: show class name and public properties when safe
- strings: truncate to a configurable preview length
- redact fields matching names like `api_key`, `token`, `secret`, `password`, `authorization`
- avoid unserializing untrusted data unless the existing driver already returns a PHP value safely

Add a “full value” view only when a dev/debug option is enabled.
:::

---

### 6. Add individual, group, tag, domain, and global invalidation

The Cache Monitor should support multiple levels of cache operations:

| Operation | Purpose |
|---|---|
| Delete entry | Remove one cached object |
| Delete selected | Remove checked entries |
| Flush group | Remove all entries in one group |
| Invalidate tag | Bump tag version |
| Invalidate domain | Use dependency map to bump related tags |
| Flush expired | Clean expired indexed entries/cache rows |
| Flush repository cache | Clear all repository cache groups |
| Flush all plugin cache | Clear all AIPS-owned cache |
| Emergency flush | Best-effort flush plus cache index reset |

:::task-stub{title="Add Cache Monitor invalidation actions"}
Implement destructive cache actions in `AIPS_Cache_Monitor_Controller`.

Actions should include:

- delete single cache entry
- delete selected entries
- flush cache group
- invalidate tag
- invalidate domain through `AIPS_Repository_Cache_Dependencies`
- flush expired cache entries
- flush all repository caches
- flush all plugin-owned caches
- reset cache index metadata

Every action must:

- require `manage_options`
- verify a nonce
- sanitize all submitted keys/groups/tags/domains
- log the action through `AIPS_Logger_Interface` or cache observer
- return a clear admin notice with affected counts where possible

Avoid calling a global driver flush if it could delete non-plugin cache entries.
:::

---

### 7. Add cache tag and domain explorer

Since the new repository caching system relies on tags and dependency domains, admins need to see those relationships.

Suggested views:

- Tag list:
  - tag name
  - current version
  - number of indexed entries using tag
  - last invalidated
  - invalidate action

- Domain map:
  - domain name
  - tags it invalidates
  - affected operation IDs
  - sample repositories using it

:::task-stub{title="Add tag and domain explorer to Cache Monitor"}
Add Cache Monitor tabs for tags and dependency domains.

Implement service methods:

- `list_tags( array $filters = array() ): array`
- `get_tag_details( string $tag ): array`
- `list_domains(): array`
- `get_domain_details( string $domain ): array`

Render:

- current tag versions
- indexed entry counts per tag
- last invalidation timestamps
- domains from `AIPS_Repository_Cache_Dependencies`
- tags affected by each domain
- repository operation IDs associated with those tags

Add actions for invalidating one tag or one domain.
:::

---

### 8. Add repository operation analytics

This connects the Cache Monitor to the observability work from Phase 0.

For each operation ID, show:

- repository class
- policy tier
- TTL
- tags
- hit count
- miss count
- hit ratio
- bypass count
- stale count
- average rebuild time
- slowest rebuild time
- last accessed
- last invalidated
- estimated size impact

:::task-stub{title="Add repository operation analytics to Cache Monitor"}
Extend `AIPS_Repository_Cache_Observer` to persist or aggregate operation-level metrics.

Expose these metrics through `AIPS_Cache_Monitor_Service`.

Add a Cache Monitor tab named `Operations`.

For each operation ID, show:

- repository class
- cache policy
- tier
- TTL
- tags
- hit count
- miss count
- hit ratio
- bypass count
- stale count
- average rebuild time
- maximum rebuild time
- last read timestamp
- last invalidation timestamp
- indexed entry count
- estimated size

Include filters for repository, tier, and low-hit-ratio operations.
:::

---

### 9. Add cache event log

Admins need an audit trail for destructive operations and cache health debugging.

Events to log:

- entry deleted
- group flushed
- tag invalidated
- domain invalidated
- all plugin cache flushed
- cache index reset
- slow rebuild
- cache driver fallback
- cache monitor failed to inspect driver
- stale value served
- cron bypass used

:::task-stub{title="Add Cache Monitor event log"}
Add a cache event log view.

Use existing telemetry/history/logging infrastructure if suitable. Otherwise, create a lightweight cache-event persistence mechanism.

Capture:

- event type
- timestamp
- user ID for admin actions
- correlation ID
- cache group
- key hash
- operation ID
- tags/domains
- affected count
- elapsed milliseconds
- message/context

Render a paginated `Events` tab in the Cache Monitor.

Ensure sensitive values and full cache keys are not exposed unnecessarily.
:::

---

### 10. Support driver-specific detail panels

Different drivers have different operational characteristics.

Examples:

#### DB driver

Show:

- table size
- row count
- expired row count
- largest rows
- cleanup action

#### Redis driver

Show:

- connection status
- configured host/port/db
- plugin key prefix
- memory usage if available
- warning if key listing is disabled or unsafe

#### WP Object Cache driver

Show:

- whether persistent object cache is detected
- limitations around key listing
- group names

#### Array driver

Show:

- request-local only warning
- entries visible only for current request

#### Session driver

Show:

- session status
- session cache entry count

:::task-stub{title="Add driver-specific Cache Monitor panels"}
Add driver-specific status sections to `AIPS_Cache_Monitor_Service`.

For each supported driver, expose:

- capabilities
- health status
- limitations
- storage stats where available
- cleanup operations where safe

Render a `Driver` tab in the Cache Monitor.

Do not expose sensitive Redis credentials or environment secrets.
:::

---

### 11. Add cache cleanup and maintenance tools

The monitor should help keep cache storage healthy.

Useful maintenance actions:

- prune expired DB cache rows
- prune orphaned cache index entries
- rebuild cache index from DB cache table where possible
- reset tag versions
- compact cache metrics
- export cache diagnostics bundle
- validate cache policy definitions

:::task-stub{title="Add cache maintenance tools"}
Add a `Maintenance` tab to the Cache Monitor.

Implement actions:

- prune expired cache entries
- prune orphaned cache index entries
- rebuild cache index from DB-backed cache rows where possible
- reset selected tag versions
- validate repository cache policies
- compact cache event/metrics tables
- export diagnostics as JSON

All actions must require nonce verification and `manage_options`.
:::

---

### 12. Add safety controls and permissions

Cache Monitor is powerful and potentially dangerous. It should have guardrails.

Recommended safety controls:

- All destructive actions require nonce confirmation.
- “Flush all plugin cache” requires an extra confirmation checkbox.
- Emergency flush only available in dev tools or debug mode.
- Full value inspection only in debug/dev mode.
- Rate-limit bulk destructive actions.
- Log all destructive admin actions.
- Avoid showing secrets in cached values.
- Avoid flushing global object cache outside plugin-owned groups.

:::task-stub{title="Add Cache Monitor safety controls"}
Implement safety controls for Cache Monitor actions.

Requirements:

- require `manage_options`
- verify nonces for all destructive actions
- require explicit confirmation for flush-all and emergency actions
- hide full cache value inspection unless a debug/dev option is enabled
- redact sensitive fields from previews
- log user ID and action details for destructive operations
- prevent accidental flushing of non-plugin cache data
- display warnings for drivers where key deletion/listing is approximate or unsupported
:::

---

### 13. Add AJAX endpoints for live monitoring

The Cache Monitor can be a normal admin page initially, but live data is valuable.

AJAX actions:

- refresh summary cards
- search entries
- inspect entry
- delete entry
- flush group
- invalidate tag
- invalidate domain
- load operation metrics
- load event log
- run maintenance action

:::task-stub{title="Add AJAX API for Cache Monitor"}
Create AJAX handlers in `AIPS_Cache_Monitor_Controller`.

Register actions through `AIPS_Ajax_Registry`.

Suggested actions:

- `aips_cache_monitor_summary`
- `aips_cache_monitor_entries`
- `aips_cache_monitor_inspect`
- `aips_cache_monitor_delete_entry`
- `aips_cache_monitor_flush_group`
- `aips_cache_monitor_invalidate_tag`
- `aips_cache_monitor_invalidate_domain`
- `aips_cache_monitor_operations`
- `aips_cache_monitor_events`
- `aips_cache_monitor_maintenance`

Use `AIPS_Ajax_Response` for JSON responses.

Each endpoint must verify nonce and capability.
:::

---

### 14. Add cache monitor settings

Admins should be able to configure how much data is tracked.

Suggested options:

- enable/disable cache index
- enable/disable operation metrics
- cache event retention days
- maximum indexed entries
- value preview length
- enable full value inspection in debug mode
- enable live refresh
- live refresh interval
- enable automatic expired-entry cleanup
- cache monitor visibility: System Status / Dev Tools / dedicated menu

:::task-stub{title="Add Cache Monitor settings"}
Add Cache Monitor settings to the plugin settings registry.

Update centralized defaults in `AIPS_Config::get_instance()->get_default_options()`.

Add settings such as:

- `aips_cache_monitor_enabled`
- `aips_cache_monitor_index_enabled`
- `aips_cache_monitor_metrics_enabled`
- `aips_cache_monitor_event_retention_days`
- `aips_cache_monitor_max_index_entries`
- `aips_cache_monitor_preview_length`
- `aips_cache_monitor_full_value_debug_only`
- `aips_cache_monitor_live_refresh_enabled`
- `aips_cache_monitor_live_refresh_interval`

Register sanitize callbacks in `AIPS_Settings`.

Render settings in the appropriate settings UI section.
:::

---

### 15. Add cache monitor cron maintenance

Some cleanup should run automatically.

Suggested cron hook:

- `aips_cache_monitor_maintenance`

Tasks:

- prune expired index entries
- prune old event logs
- compact operation metrics
- remove orphaned index rows
- optionally prune expired DB cache entries

:::task-stub{title="Add Cache Monitor maintenance cron"}
Add a recurring cron hook named `aips_cache_monitor_maintenance`.

Register it in plugin cron setup.

Implement a service method such as:

- `AIPS_Cache_Monitor_Service::run_maintenance()`

Maintenance should:

- prune expired cache index entries
- prune old cache monitor events
- compact old metrics
- remove orphaned index rows
- prune expired DB cache rows when the DB driver is active or index metadata indicates DB-backed entries

Use configurable retention settings.
:::

---

### 16. Proposed Cache Monitor UI structure

Recommended tabs:

1. **Overview**
   - health cards
   - active driver
   - total entries
   - total estimated size
   - hit/miss ratio
   - recent invalidations

2. **Entries**
   - searchable table of cached objects
   - inspect/delete/bulk actions

3. **Tags**
   - tag versions
   - invalidate tag
   - entries per tag

4. **Domains**
   - dependency map
   - invalidate domain
   - affected tags/operations

5. **Operations**
   - operation-level hit/miss/rebuild analytics

6. **Events**
   - audit/debug event log

7. **Driver**
   - driver capabilities and storage stats

8. **Maintenance**
   - prune, rebuild index, export diagnostics, validate policies

:::task-stub{title="Build multi-tab Cache Monitor UI"}
Implement a multi-tab admin UI in `templates/admin/cache-monitor.php`.

Tabs:

- Overview
- Entries
- Tags
- Domains
- Operations
- Events
- Driver
- Maintenance

Use existing admin styling conventions and enqueue any needed JS/CSS through `AIPS_Admin_Assets`.

Ensure all dynamic values are escaped with `esc_html()`, `esc_attr()`, `esc_url()`, or `wp_kses_post()` as appropriate.
:::

---

### 17. Suggested rollout order

1. Add driver capability/introspection interface.
2. Add cache index metadata.
3. Add Cache Monitor service/controller/template.
4. Add Overview and Entries tabs.
5. Add individual delete and group flush.
6. Add tag/domain invalidation.
7. Add operation analytics.
8. Add event log.
9. Add driver-specific panels.
10. Add maintenance tools.
11. Add settings.
12. Add cron maintenance.
13. Add tests.

:::task-stub{title="Roll out Cache Monitor incrementally"}
Implement Cache Monitor in staged releases.

Stage 1:

- Overview tab
- Entries tab
- cache index
- individual delete
- group flush

Stage 2:

- tag explorer
- domain explorer
- tag/domain invalidation
- repository operation analytics

Stage 3:

- event log
- driver-specific panels
- maintenance tools
- settings
- cron maintenance

Stage 4:

- live AJAX refresh
- diagnostics export
- full policy validation
- advanced debug-only value inspection
:::

---