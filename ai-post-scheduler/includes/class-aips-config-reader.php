<?php
/**
 * AIPS Config Reader.
 *
 * Single, read-only source of truth for "what AIPS configuration exists on this
 * installation": the catalog of configuration tables and how to read their rows,
 * plus the taxonomy terms (categories, source groups) that configuration refers
 * to by ID.
 *
 * Both the snapshot exporter (scripts/export-aips-snapshot.php) and the
 * DevStackTips setup tool read the live installation through this class, so the
 * list of configuration entities is defined in exactly one place. It performs
 * SELECT queries only and never writes.
 *
 * It intentionally reads tables directly rather than through each repository:
 * the callers need the full, untransformed row (every column, including ones a
 * repository may not expose) keyed by primary-key ID, and they must agree on one
 * catalog and ordering. Writes still go through the repositories.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Config_Reader {

	/**
	 * Canonical catalog of AIPS configuration entities.
	 *
	 * Entity type (stable, used by callers) => table suffix (without prefix).
	 * The order is significant: the exporter numbers its output files in this
	 * order, so new entities are appended, not inserted.
	 *
	 * @var array<string,string>
	 */
	const CONFIG_TABLES = array(
		'campaigns'       => 'aips_campaigns',
		'templates'       => 'aips_templates',
		'schedules'       => 'aips_schedule',
		'authors'         => 'aips_authors',
		'voices'          => 'aips_voices',
		'structures'      => 'aips_article_structures',
		'prompt_sections' => 'aips_prompt_sections',
		'post_slices'     => 'aips_post_slices',
		'sources'         => 'aips_sources',
		'source_groups'   => 'aips_source_group_terms',
		'post_images'     => 'aips_post_images',
		'affiliate_links' => 'aips_affiliate_links',
		'taxonomy'        => 'aips_taxonomy',
	);

	/** @var wpdb */
	private $wpdb;

	/**
	 * @param wpdb|null $wpdb Database handle; defaults to the global $wpdb.
	 */
	public function __construct($wpdb = null) {
		$this->wpdb = $wpdb ?: $GLOBALS['wpdb'];
	}

	/**
	 * Configuration entity types, in catalog order.
	 *
	 * @return string[]
	 */
	public function config_entity_types() {
		return array_keys(self::CONFIG_TABLES);
	}

	/**
	 * Full, prefixed table name for an entity type or a raw suffix.
	 *
	 * @param string $type_or_suffix Entity type (e.g. 'templates') or suffix (e.g. 'aips_templates').
	 * @return string|null Prefixed table name, or null for an unknown type.
	 */
	public function table_for($type_or_suffix) {
		if (isset(self::CONFIG_TABLES[$type_or_suffix])) {
			return $this->wpdb->prefix . self::CONFIG_TABLES[$type_or_suffix];
		}

		if (in_array($type_or_suffix, self::CONFIG_TABLES, true)) {
			return $this->wpdb->prefix . $type_or_suffix;
		}

		return null;
	}

	/**
	 * Whether a table exists.
	 *
	 * @param string $table Full (prefixed) table name.
	 * @return bool
	 */
	public function table_exists($table) {
		return $this->wpdb->get_var(
			$this->wpdb->prepare('SHOW TABLES LIKE %s', $this->wpdb->esc_like($table))
		) === $table;
	}

	/**
	 * Read every row of one configuration table, ordered by its first column (ID).
	 *
	 * @param string $type_or_suffix Entity type or table suffix.
	 * @param string $output         ARRAY_A (default) or OBJECT.
	 * @return array Rows (empty when the table does not exist).
	 */
	public function read_table($type_or_suffix, $output = ARRAY_A) {
		$table = $this->table_for($type_or_suffix);

		if (null === $table || !$this->table_exists($table)) {
			return array();
		}

		$rows = $this->wpdb->get_results("SELECT * FROM `{$table}` ORDER BY 1", $output);

		return is_array($rows) ? $rows : array();
	}

	/**
	 * Read every configuration table that exists.
	 *
	 * @param string $output ARRAY_A (default) or OBJECT.
	 * @return array<string,array> Entity type => rows, for present tables only, in catalog order.
	 */
	public function read_all($output = ARRAY_A) {
		$result = array();

		foreach (self::CONFIG_TABLES as $type => $suffix) {
			$table = $this->wpdb->prefix . $suffix;
			if ($this->table_exists($table)) {
				$result[$type] = $this->read_table($suffix, $output);
			}
		}

		return $result;
	}

	/**
	 * Read the terms of a taxonomy (id, name, slug, parent, count).
	 *
	 * Used for the taxonomies AIPS configuration refers to by term ID:
	 * 'category' for post categories and 'aips_source_group' for source groups.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param string $output   ARRAY_A (default) or OBJECT.
	 * @return array Term rows (empty when the taxonomy has no terms).
	 */
	public function read_terms($taxonomy, $output = ARRAY_A) {
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT t.term_id, t.name, t.slug, tt.taxonomy, tt.parent, tt.count
				 FROM {$this->wpdb->terms} t
				 JOIN {$this->wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
				 WHERE tt.taxonomy = %s
				 ORDER BY t.name",
				$taxonomy
			),
			$output
		);

		return is_array($rows) ? $rows : array();
	}
}
