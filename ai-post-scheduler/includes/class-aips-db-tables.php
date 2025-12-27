<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * DB Tables helper
 *
 * Provides a single source of truth for plugin table names (without repeating
 * $wpdb->prefix . 'table_name' everywhere). Methods are static to make usage
 * simple from procedural code.
 */
class AIPS_DB_Tables {
    /**
     * Map of logical keys to table names (without prefix).
     * Add new tables here when creating new tables.
     *
     * @var array
     */
    private static $tables = array(
        'aips_history' => 'aips_history',
        'aips_templates' => 'aips_templates',
        'aips_schedule' => 'aips_schedule',
        'aips_voices' => 'aips_voices',
        'aips_article_structures' => 'aips_article_structures',
        'aips_prompt_sections' => 'aips_prompt_sections',
        'aips_trending_topics' => 'aips_trending_topics',
    );

    /**
     * Get the full (prefixed) table name for a logical key.
     *
     * Usage: AIPS_DB_Tables::get('aips_templates')
     *
     * @param string $key Logical key (one of the keys in self::$tables).
     * @return string Full table name including WP prefix.
     */
    public static function get($key) {
        global $wpdb;
        if (!isset(self::$tables[$key])) {
            // If unknown key, assume key is actual table name (without prefix).
            $table = $key;
        } else {
            $table = self::$tables[$key];
        }

        return isset($wpdb) ? $wpdb->prefix . $table : $table;
    }

    /**
     * Get an associative array of all full table names keyed by logical key.
     *
     * @return array
     */
    public static function get_all() {
        global $wpdb;
        $full = array();
        foreach (self::$tables as $key => $table) {
            $full[$key] = isset($wpdb) ? $wpdb->prefix . $table : $table;
        }
        return $full;
    }
}

