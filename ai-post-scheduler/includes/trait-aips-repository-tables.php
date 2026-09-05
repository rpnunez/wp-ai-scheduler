<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Shared table-name resolution for repositories.
 *
 * Provides a single source of truth for resolving fully-prefixed plugin table
 * names so repositories no longer hardcode `$wpdb->prefix . 'aips_x'`. This trait
 * is intentionally decoupled from AIPS_Cacheable_Repository: a repository may
 * adopt table resolution independently of caching.
 *
 * This file is self-loading via the same trait_exists() require-guard used by
 * AIPS_Cacheable_Repository, because the autoloader only handles class-*.php and
 * interface-*.php files (not trait-*.php).
 */
trait AIPS_Repository_Tables {

	/**
	 * Resolve a single plugin table's full (prefixed) name by its bare key.
	 *
	 * Delegates to AIPS_DB_Manager::get_table() so the value is identical to the
	 * previously hardcoded `$wpdb->prefix . $key` string.
	 *
	 * @param string $key Bare table key (e.g. 'aips_authors').
	 * @return string Fully prefixed table name.
	 */
	protected function table(string $key): string {
		return AIPS_DB_Manager::get_table($key);
	}

	/**
	 * Convenience accessor for the WordPress database abstraction object.
	 *
	 * @return wpdb
	 */
	protected function db() {
		global $wpdb;
		return $wpdb;
	}
}
