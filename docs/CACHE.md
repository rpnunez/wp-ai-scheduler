# Cache Framework

The AI Post Scheduler plugin includes a pluggable cache framework that lets administrators choose how and where plugin data is cached, without affecting existing plugin logic. The framework is available for future use by plugin features that benefit from caching.

## Overview

The cache system is built around a **driver pattern**: a central `AIPS_Cache` class delegates all storage operations to a concrete `AIPS_Cache_Driver` implementation. A `AIPS_Cache_Factory` reads the admin-configured driver from settings and wires everything together, falling back to the safe in-memory `ArrayDriver` when the chosen driver cannot initialise.

## Architecture

```
AIPS_Cache_Factory::instance()
        │
        ▼
    AIPS_Cache
        │  delegates to
        ▼
AIPS_Cache_Driver (interface)
    ├── AIPS_Cache_Array_Driver
    ├── AIPS_Cache_Db_Driver
    ├── AIPS_Cache_Redis_Driver
    └── AIPS_Cache_Wp_Object_Cache_Driver
```

### Files

| File | Class / Interface | Purpose |
|------|-------------------|---------|
| `includes/interface-aips-cache-driver.php` | `AIPS_Cache_Driver` | Driver contract |
| `includes/class-aips-cache.php` | `AIPS_Cache` | High-level cache API |
| `includes/class-aips-cache-factory.php` | `AIPS_Cache_Factory` | Driver instantiation + singleton |
| `includes/class-aips-cache-array-driver.php` | `AIPS_Cache_Array_Driver` | In-memory (request-scoped) |
| `includes/class-aips-cache-db-driver.php` | `AIPS_Cache_Db_Driver` | Persistent DB-backed |
| `includes/class-aips-cache-redis-driver.php` | `AIPS_Cache_Redis_Driver` | Persistent Redis-backed |
| `includes/class-aips-cache-wp-object-cache-driver.php` | `AIPS_Cache_Wp_Object_Cache_Driver` | WordPress Object Cache API |

## Cache Drivers

### Array Driver (`array`)

**Default driver.** Stores values in a PHP array for the lifetime of the current request. No persistence across page loads.

- No configuration required.
- Always available — used as the hard fallback when other drivers fail.
- Ideal for unit tests and environments where persistence is not needed.

### Database Driver (`db`)

Stores serialized values in the `{prefix}aips_cache` DB table.

- Provides **cross-request persistence** using the existing plugin database.
- Handles TTL-based expiration automatically on read.
- Includes a `purge_expired()` method for periodic cleanup.
- Optional: **DB Cache Key Prefix** to isolate entries per environment.

**DB table schema:**

```sql
CREATE TABLE {prefix}aips_cache (
    id         bigint(20)    NOT NULL AUTO_INCREMENT,
    cache_key  varchar(191)  NOT NULL,
    cache_group varchar(100) NOT NULL DEFAULT 'default',
    value      longtext      NOT NULL,
    expires_at datetime      DEFAULT NULL,
    updated_at datetime      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    UNIQUE KEY cache_key_group (cache_key, cache_group),
    KEY expires_at (expires_at)
);
```

### Redis Driver (`redis`)

Uses the PHP [`redis` extension (phpredis)](https://github.com/phpredis/phpredis) for fast, persistent caching.

- Requires the **PHP `redis` extension** (`pecl install redis`).
- Falls back to the Array driver automatically if the extension is missing or the connection fails.
- An admin notice is displayed when a fallback occurs.

**Required configuration:**

| Setting | Default | Description |
|---------|---------|-------------|
| Redis Host | `127.0.0.1` | Redis server hostname or IP |
| Redis Port | `6379` | Redis server port |
| Redis Password | *(empty)* | Auth password; leave empty if not needed |
| Redis Database Index | `0` | Database index (0–15) |
| Redis Key Prefix | `aips` | Prefix applied to all cache keys |

### WP Object Cache Driver (`wp_object_cache`)

Delegates to the WordPress Object Cache API (`wp_cache_get`, `wp_cache_set`, `wp_cache_delete`, `wp_cache_flush`).

- Automatically uses any **persistent object-cache drop-in** installed on the site (e.g., Redis, Memcached via a WP drop-in).
- Without a drop-in, behaves like the Array driver (request-scoped only).
- Groups are namespaced as `aips_{group}` to avoid collisions.

## Admin Settings

Settings are in **Settings → AI Post Scheduler → Cache** tab.

| Setting | Option name | Description |
|---------|-------------|-------------|
| Cache Driver | `aips_cache_driver` | `array`, `db`, `redis`, or `wp_object_cache` |
| Default TTL | `aips_cache_default_ttl` | Default time-to-live in seconds (0 = no expiration) |
| DB Cache Key Prefix | `aips_cache_db_prefix` | Optional prefix for DB driver keys |
| Redis Host | `aips_cache_redis_host` | Redis hostname |
| Redis Port | `aips_cache_redis_port` | Redis port |
| Redis Password | `aips_cache_redis_password` | Redis auth password |
| Redis Database Index | `aips_cache_redis_db` | Redis DB index |
| Redis Key Prefix | `aips_cache_redis_prefix` | Redis key prefix |

Redis-specific fields are only shown when the Redis driver is selected. DB-specific fields are only shown when the DB driver is selected.

## Usage

The cache framework is introduced in v2.3.0 but is **not yet used by existing plugin features**. It is available for future integration.

### Basic Usage

```php
// Get the shared singleton cache instance.
$cache = AIPS_Cache_Factory::instance();

// Store a value for 1 hour.
$cache->set('my_key', $my_value, 3600);

// Retrieve (returns null on miss).
$value = $cache->get('my_key');

// Retrieve with a default fallback.
$value = $cache->get('my_key', 'default', 'fallback_value');

// Cache-aside: compute once, cache for 30 minutes.
$result = $cache->remember('expensive_key', 1800, function() {
    return some_expensive_computation();
});

// Check existence.
if ($cache->has('my_key')) { ... }

// Delete.
$cache->delete('my_key');

// Flush everything.
$cache->flush();
```

### Groups / Namespaces

All methods accept an optional `$group` parameter to logically namespace keys:

```php
$cache->set('post_123', $post_data, 3600, 'posts');
$post = $cache->get('post_123', 'posts');
$cache->delete('post_123', 'posts');
```

### Counters

```php
$cache->increment('view_count');       // 1
$cache->increment('view_count', 5);   // 6
$cache->decrement('view_count', 2);   // 4
```

### Dependency Injection

For code that requires a specific driver (e.g., tests), inject a driver directly:

```php
$cache = new AIPS_Cache(new AIPS_Cache_Array_Driver());
```

## Implementing a Custom Driver

Implement the `AIPS_Cache_Driver` interface:

```php
class My_Custom_Driver implements AIPS_Cache_Driver {
    public function get($key, $group = 'default') { /* ... */ }
    public function set($key, $value, $ttl = 0, $group = 'default') { /* ... */ }
    public function delete($key, $group = 'default') { /* ... */ }
    public function flush() { /* ... */ }
    public function has($key, $group = 'default') { /* ... */ }
}

// Use directly:
$cache = new AIPS_Cache(new My_Custom_Driver());
```

## Fallback Behaviour

| Scenario | Result |
|----------|--------|
| Redis extension not installed | Silently falls back to ArrayDriver + admin notice |
| Redis connection fails | Silently falls back to ArrayDriver + admin notice |
| Unknown driver name in settings | Falls back to ArrayDriver |
| DB driver selected | Uses DB table (always available when plugin is active) |
