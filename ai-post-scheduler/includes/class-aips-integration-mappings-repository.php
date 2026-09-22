<?php
/**
 * Integration Field Mappings Repository
 *
 * Database abstraction layer for aips_integration_field_mappings: which
 * fields of a third-party plugin's schema (e.g. an ACF field group) a
 * Template should generate content for, and any per-field custom prompt.
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!trait_exists('AIPS_Cacheable_Repository')) {
	require_once __DIR__ . '/trait-aips-cacheable-repository.php';
}

if (!trait_exists('AIPS_Repository_Tables')) {
	require_once __DIR__ . '/trait-aips-repository-tables.php';
}

/**
 * Class AIPS_Integration_Mappings_Repository
 *
 * Caching model: get_by_template() is read on every generation for templates
 * with integrations, so it is cached; every read carries the broad
 * `integration_mappings` tag, which every write bumps exactly once (bulk
 * writes such as sync_group_mappings() / clone_template_mappings() persist
 * their rows first and invalidate a single time at the end). The
 * insert-vs-update existence check in save_mapping() always reads the live
 * table.
 */
class AIPS_Integration_Mappings_Repository {

	use AIPS_Cacheable_Repository;
	use AIPS_Repository_Tables;

	/**
	 * Broad cache tag carried by every cached read and bumped by every write.
	 */
	const CACHE_TAG = 'integration_mappings';

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * @var string
	 */
	private $table_name;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table_name = $this->table('aips_integration_field_mappings');
	}

	/**
	 * Get all mapping rows for a template, optionally limited to active rows.
	 *
	 * @param int  $template_id Template ID.
	 * @param bool $active_only Optional. Return only active rows. Default true.
	 * @return array<int, object> Mapping rows.
	 */
	public function get_by_template($template_id, $active_only = true) {
		$template_id = absint($template_id);
		$active_only = (bool) $active_only;

		return $this->cache_read(
			'integration_mappings.get_by_template',
			array(
				'template_id' => $template_id,
				'active_only' => $active_only,
			),
			function() use ($template_id, $active_only) {
				$where = $active_only ? 'AND is_active = 1' : '';

				return $this->wpdb->get_results($this->wpdb->prepare(
					"SELECT * FROM {$this->table_name} WHERE template_id = %d $where ORDER BY id ASC",
					$template_id
				));
			}
		);
	}

	/**
	 * Get a single mapping row by ID.
	 *
	 * @param int $id Mapping ID.
	 * @return object|null
	 */
	public function get_by_id($id) {
		$id = absint($id);

		return $this->cache_read(
			'integration_mappings.get_by_id',
			array(
				'id' => $id,
			),
			function() use ($id) {
				return $this->wpdb->get_row($this->wpdb->prepare(
					"SELECT * FROM {$this->table_name} WHERE id = %d",
					$id
				));
			}
		);
	}

	/**
	 * Create or update a mapping row.
	 *
	 * Upserts on the (template_id, integration_id, field_key) unique key: if
	 * a row already exists for that combination it is updated in place,
	 * otherwise a new row is inserted.
	 *
	 * @param array $data {
	 *     @type int    $template_id    Template ID.
	 *     @type string $integration_id Integration identifier (e.g. 'acf').
	 *     @type string $source_key     Schema group identifier (e.g. ACF field group key).
	 *     @type string $field_key      Field identifier.
	 *     @type string $field_label    Field label (cached for display).
	 *     @type string $field_type     Native field type (cached for display).
	 *     @type string $custom_prompt  Optional per-field generation instruction.
	 *     @type bool   $is_active      Whether this field should be generated.
	 * }
	 * @return int|false Mapping ID on success, false on failure.
	 */
	public function save_mapping($data) {
		$result = $this->persist_mapping($data);

		if (false !== $result) {
			$this->invalidate_mappings_cache('integration_mapping_saved');
		}

		return $result;
	}

	/**
	 * Insert or update a mapping row without touching the cache.
	 *
	 * Callers are responsible for invalidating once their write completes.
	 *
	 * @param array $data Mapping data (see save_mapping()).
	 * @return int|false Mapping ID on success, false on failure.
	 */
	private function persist_mapping($data) {
		$template_id = !empty($data['template_id']) ? absint($data['template_id']) : null;
		$integration_id = sanitize_key($data['integration_id']);
		$field_key = sanitize_text_field($data['field_key']);

		if ($template_id) {
			$existing = $this->wpdb->get_var($this->wpdb->prepare(
				"SELECT id FROM {$this->table_name} WHERE integration_id = %s AND field_key = %s AND template_id = %d",
				$integration_id,
				$field_key,
				$template_id
			));
		} else {
			$existing = $this->wpdb->get_var($this->wpdb->prepare(
				"SELECT id FROM {$this->table_name} WHERE integration_id = %s AND field_key = %s AND template_id IS NULL",
				$integration_id,
				$field_key
			));
		}

		$now = AIPS_DateTime::now()->timestamp();

		$row = array(
			'template_id'    => $template_id,
			'integration_id' => $integration_id,
			'source_key'     => sanitize_text_field($data['source_key']),
			'field_key'      => $field_key,
			'field_label'    => isset($data['field_label']) ? sanitize_text_field($data['field_label']) : '',
			'field_type'     => isset($data['field_type']) ? sanitize_key($data['field_type']) : '',
			'custom_prompt'  => isset($data['custom_prompt']) ? sanitize_textarea_field($data['custom_prompt']) : '',
			'is_active'      => !empty($data['is_active']) ? 1 : 0,
			'updated_at'     => $now,
		);
		$format = array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d');

		if ($existing) {
			$this->wpdb->update($this->table_name, $row, array('id' => (int) $existing), $format, array('%d'));
			return (int) $existing;
		}

		$row['created_at'] = $now;
		$format[] = '%d';

		$result = $this->wpdb->insert($this->table_name, $row, $format);

		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Delete a single mapping row.
	 *
	 * @param int $id Mapping ID.
	 * @return bool
	 */
	public function delete_mapping($id) {
		$result = $this->wpdb->delete($this->table_name, array('id' => absint($id)), array('%d'));

		if ($result) {
			$this->invalidate_mappings_cache('integration_mapping_deleted');
		}

		return $result !== false;
	}

	/**
	 * Delete all mapping rows for a template (call when a template is deleted).
	 *
	 * @param int $template_id Template ID.
	 * @return bool
	 */
	public function delete_by_template($template_id) {
		$result = $this->wpdb->delete($this->table_name, array('template_id' => absint($template_id)), array('%d'));

		if ($result) {
			$this->invalidate_mappings_cache('integration_mappings_deleted_by_template');
		}

		return $result !== false;
	}

	/**
	 * Retire mappings left over from a previously-selected schema group.
	 *
	 * A template's saved mappings for one integration should always reflect
	 * exactly one selected group (e.g. one ACF field group) at a time. Call
	 * this before saving a new batch of mappings so switching groups doesn't
	 * leave the old group's rows active alongside the new one — otherwise
	 * both groups' fields would be generated on every post.
	 *
	 * @param int    $template_id    Template ID.
	 * @param string $integration_id Integration identifier (e.g. 'acf').
	 * @param string $source_key     The group identifier being kept.
	 * @return bool
	 */
	public function delete_stale_group_mappings($template_id, $integration_id, $source_key) {
		$template_id = absint($template_id);

		if (!$template_id) {
			return false;
		}

		$result = $this->wpdb->query($this->wpdb->prepare(
			"DELETE FROM {$this->table_name} WHERE template_id = %d AND integration_id = %s AND source_key != %s",
			$template_id,
			sanitize_key($integration_id),
			sanitize_text_field($source_key)
		));

		if ($result) {
			$this->invalidate_mappings_cache('integration_mappings_stale_group_deleted');
		}

		return $result !== false;
	}

	/**
	 * Synchronize all field mappings for a specific (template_id, integration_id, source_key) group.
	 *
	 * Deletes all existing mappings for this template + integration, then saves
	 * the provided mapping rows in a single clean pass.
	 *
	 * @param int               $template_id    Template ID.
	 * @param string            $integration_id Integration identifier.
	 * @param string            $source_key     Schema group identifier (e.g. ACF group key or post type).
	 * @param array<int, array> $mappings       Array of mapping data arrays.
	 * @return bool True on success, false on failure.
	 */
	public function sync_group_mappings($template_id, $integration_id, $source_key, array $mappings) {
		$template_id = absint($template_id);
		$integration_id = sanitize_key($integration_id);
		$source_key = sanitize_text_field($source_key);

		if (!$template_id || empty($integration_id)) {
			return false;
		}

		// Delete all existing mappings for this template + integration.
		$this->wpdb->delete(
			$this->table_name,
			array(
				'template_id'    => $template_id,
				'integration_id' => $integration_id,
			),
			array('%d', '%s')
		);

		foreach ($mappings as $mapping) {
			if (empty($mapping['field_key'])) {
				continue;
			}

			$mapping['template_id']    = $template_id;
			$mapping['integration_id'] = $integration_id;
			$mapping['source_key']     = $source_key;
			$this->persist_mapping($mapping);
		}

		// The delete above always changes (or confirms) the group's rows, so
		// invalidate once for the whole sync.
		$this->invalidate_mappings_cache('integration_mappings_synced');

		return true;
	}

	/**
	 * Duplicate all field mappings from a source template to a destination template.
	 *
	 * @param int $source_template_id      Source template ID.
	 * @param int $destination_template_id Destination template ID.
	 * @return bool True on success, false on failure.
	 */
	public function clone_template_mappings($source_template_id, $destination_template_id) {
		$source_template_id = absint($source_template_id);
		$destination_template_id = absint($destination_template_id);

		if (!$source_template_id || !$destination_template_id) {
			return false;
		}

		$existing = $this->get_by_template($source_template_id, false);

		if (empty($existing)) {
			return true;
		}

		foreach ($existing as $mapping) {
			$this->persist_mapping(array(
				'template_id'    => $destination_template_id,
				'integration_id' => $mapping->integration_id,
				'source_key'     => $mapping->source_key,
				'field_key'      => $mapping->field_key,
				'field_label'    => $mapping->field_label,
				'field_type'     => $mapping->field_type,
				'custom_prompt'  => $mapping->custom_prompt,
				'is_active'      => (int) $mapping->is_active,
			));
		}

		$this->invalidate_mappings_cache('integration_mappings_cloned');

		return true;
	}

	/**
	 * Return the repository cache group for integration-mapping reads.
	 *
	 * @return string
	 */
	protected function repository_cache_group(): string {
		return 'aips_integration_mappings';
	}

	/**
	 * Return the explicit repository cache policies for integration-mapping reads.
	 *
	 * @return array
	 */
	protected function repository_cache_policies(): array {
		return array(
			'integration_mappings.get_by_template' => array(
				'tier'        => 'medium',
				'ttl'         => 300,
				'tags'        => array(self::CACHE_TAG),
				'description' => 'Cache per-template field mappings; read on every generation for templates with integrations.',
			),
			'integration_mappings.get_by_id' => array(
				'tier'        => 'medium',
				'ttl'         => 300,
				'tags'        => array(self::CACHE_TAG),
				'cache_null'  => false,
				'description' => 'Cache single mapping reads by ID.',
			),
		);
	}

	/**
	 * Invalidate every cached mapping read after a write.
	 *
	 * @param string $reason Invalidation reason.
	 * @return void
	 */
	private function invalidate_mappings_cache($reason) {
		$this->invalidate_cache_tags(array(self::CACHE_TAG), (string) $reason);
	}
}

