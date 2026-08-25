<?php
/**
 * SEO Profiles Repository
 *
 * Database abstraction layer for SEO profiles. Allows creating, reading,
 * updating, and deleting reusable SEO profiles that define which specific
 * SEO fields to generate, target provider preference, field-specific prompts,
 * pattern overrides, Schema.org types, and Media SEO settings.
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

class AIPS_SEO_Profiles_Repository {
	use AIPS_Cacheable_Repository;

	/**
	 * @var self|null Singleton instance.
	 */
	private static $instance = null;

	/**
	 * @var string
	 */
	private $table_name;

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Get the shared singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table_name = $wpdb->prefix . 'aips_seo_profiles';
	}

	/**
	 * Get all SEO profiles.
	 *
	 * @param bool $active_only Whether to return only active profiles.
	 * @return array<int, object>
	 */
	public function get_all($active_only = false) {
		$active_only = (bool) $active_only;
		return $this->cache_read(
			'seo_profiles.get_all',
			array('active_only' => $active_only),
			function () use ($active_only) {
				$where = $active_only ? 'WHERE is_active = 1' : '';
				$results = $this->wpdb->get_results("SELECT * FROM {$this->table_name} $where ORDER BY name ASC");
				if (is_array($results)) {
					foreach ($results as $row) {
						$this->unserialize_row_fields($row);
					}
				}
				return $results ?: array();
			}
		);
	}

	/**
	 * Get a single SEO profile by ID.
	 *
	 * @param int $id Profile ID.
	 * @return object|null
	 */
	public function get_by_id($id) {
		$id = absint($id);
		if (!$id) {
			return null;
		}

		return $this->cache_read(
			'seo_profiles.get_by_id',
			array('profile_id' => $id),
			function () use ($id) {
				$row = $this->wpdb->get_row($this->wpdb->prepare(
					"SELECT * FROM {$this->table_name} WHERE id = %d",
					$id
				));
				if ($row) {
					$this->unserialize_row_fields($row);
				}
				return $row;
			}
		);
	}

	/**
	 * Create a new SEO profile.
	 *
	 * @param array $data Profile data.
	 * @return int|false Profile ID on success, false on failure.
	 */
	public function create($data) {
		$now = AIPS_DateTime::now()->timestamp();

		$fields = isset($data['fields']) ? (is_array($data['fields']) ? $data['fields'] : json_decode($data['fields'], true)) : array();
		if (!is_array($fields) || empty($fields)) {
			$fields = array('focus_keyword', 'seo_title', 'meta_description');
		}

		$field_modes = isset($data['field_modes']) ? (is_array($data['field_modes']) ? $data['field_modes'] : json_decode($data['field_modes'], true)) : array();
		$field_patterns = isset($data['field_patterns']) ? (is_array($data['field_patterns']) ? $data['field_patterns'] : json_decode($data['field_patterns'], true)) : array();
		$field_prompts = isset($data['field_prompts']) ? (is_array($data['field_prompts']) ? $data['field_prompts'] : json_decode($data['field_prompts'], true)) : array();
		$schema_types = isset($data['schema_types']) ? (is_array($data['schema_types']) ? $data['schema_types'] : json_decode($data['schema_types'], true)) : array('article', 'breadcrumbs');
		$media_seo_fields = isset($data['media_seo_fields']) ? (is_array($data['media_seo_fields']) ? $data['media_seo_fields'] : json_decode($data['media_seo_fields'], true)) : array('alt', 'title', 'caption', 'description');

		$insert_data = array(
			'name'                => sanitize_text_field($data['name']),
			'description'         => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
			'provider_id'         => isset($data['provider_id']) ? sanitize_key($data['provider_id']) : 'auto',
			'fields'              => wp_json_encode(array_values(array_unique(array_map('sanitize_key', $fields)))),
			'field_modes'         => wp_json_encode($field_modes),
			'field_patterns'      => wp_json_encode($field_patterns),
			'field_prompts'       => wp_json_encode($field_prompts),
			'title_prefix'        => isset($data['title_prefix']) ? sanitize_text_field($data['title_prefix']) : '',
			'title_suffix'        => isset($data['title_suffix']) ? sanitize_text_field($data['title_suffix']) : '',
			'meta_desc_prefix'    => isset($data['meta_desc_prefix']) ? sanitize_textarea_field($data['meta_desc_prefix']) : '',
			'meta_desc_suffix'    => isset($data['meta_desc_suffix']) ? sanitize_textarea_field($data['meta_desc_suffix']) : '',
			'custom_instructions'=> isset($data['custom_instructions']) ? sanitize_textarea_field($data['custom_instructions']) : '',
			'schema_types'        => wp_json_encode($schema_types),
			'media_seo_enabled'   => isset($data['media_seo_enabled']) ? (filter_var($data['media_seo_enabled'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : 1,
			'media_seo_mode'      => isset($data['media_seo_mode']) && $data['media_seo_mode'] === 'vision' ? 'vision' : 'text',
			'media_seo_fields'    => wp_json_encode($media_seo_fields),
			'is_active'           => !empty($data['is_active']) ? 1 : 0,
			'created_at'          => $now,
			'updated_at'          => $now,
		);

		$format = array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%d');

		$result = $this->wpdb->insert($this->table_name, $insert_data, $format);

		if ($result) {
			$this->invalidate_cache_domain('seo_profile', array(), 'seo_profile_created');
			return $this->wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Update an existing SEO profile.
	 *
	 * @param int   $id   Profile ID.
	 * @param array $data Data to update.
	 * @return bool True on success, false on failure.
	 */
	public function update($id, $data) {
		$id = absint($id);
		if (!$id) {
			return false;
		}

		$update_data = array();
		$format = array();

		if (isset($data['name'])) {
			$update_data['name'] = sanitize_text_field($data['name']);
			$format[] = '%s';
		}

		if (isset($data['description'])) {
			$update_data['description'] = sanitize_textarea_field($data['description']);
			$format[] = '%s';
		}

		if (isset($data['provider_id'])) {
			$update_data['provider_id'] = sanitize_key($data['provider_id']);
			$format[] = '%s';
		}

		if (isset($data['fields'])) {
			$fields = is_array($data['fields']) ? $data['fields'] : json_decode($data['fields'], true);
			$update_data['fields'] = wp_json_encode(is_array($fields) ? array_values(array_unique(array_map('sanitize_key', $fields))) : array());
			$format[] = '%s';
		}

		if (isset($data['field_modes'])) {
			$modes = is_array($data['field_modes']) ? $data['field_modes'] : json_decode($data['field_modes'], true);
			$update_data['field_modes'] = wp_json_encode(is_array($modes) ? $modes : array());
			$format[] = '%s';
		}

		if (isset($data['field_patterns'])) {
			$patterns = is_array($data['field_patterns']) ? $data['field_patterns'] : json_decode($data['field_patterns'], true);
			$update_data['field_patterns'] = wp_json_encode(is_array($patterns) ? $patterns : array());
			$format[] = '%s';
		}

		if (isset($data['field_prompts'])) {
			$prompts = is_array($data['field_prompts']) ? $data['field_prompts'] : json_decode($data['field_prompts'], true);
			$update_data['field_prompts'] = wp_json_encode(is_array($prompts) ? $prompts : array());
			$format[] = '%s';
		}

		if (isset($data['title_prefix'])) {
			$update_data['title_prefix'] = sanitize_text_field($data['title_prefix']);
			$format[] = '%s';
		}

		if (isset($data['title_suffix'])) {
			$update_data['title_suffix'] = sanitize_text_field($data['title_suffix']);
			$format[] = '%s';
		}

		if (isset($data['meta_desc_prefix'])) {
			$update_data['meta_desc_prefix'] = sanitize_textarea_field($data['meta_desc_prefix']);
			$format[] = '%s';
		}

		if (isset($data['meta_desc_suffix'])) {
			$update_data['meta_desc_suffix'] = sanitize_textarea_field($data['meta_desc_suffix']);
			$format[] = '%s';
		}

		if (isset($data['custom_instructions'])) {
			$update_data['custom_instructions'] = sanitize_textarea_field($data['custom_instructions']);
			$format[] = '%s';
		}

		if (isset($data['schema_types'])) {
			$schema = is_array($data['schema_types']) ? $data['schema_types'] : json_decode($data['schema_types'], true);
			$update_data['schema_types'] = wp_json_encode(is_array($schema) ? $schema : array());
			$format[] = '%s';
		}

		if (isset($data['media_seo_enabled'])) {
			$update_data['media_seo_enabled'] = filter_var($data['media_seo_enabled'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
			$format[] = '%d';
		}

		if (isset($data['media_seo_mode'])) {
			$update_data['media_seo_mode'] = $data['media_seo_mode'] === 'vision' ? 'vision' : 'text';
			$format[] = '%s';
		}

		if (isset($data['media_seo_fields'])) {
			$media_fields = is_array($data['media_seo_fields']) ? $data['media_seo_fields'] : json_decode($data['media_seo_fields'], true);
			$update_data['media_seo_fields'] = wp_json_encode(is_array($media_fields) ? $media_fields : array());
			$format[] = '%s';
		}

		if (isset($data['is_active'])) {
			$update_data['is_active'] = !empty($data['is_active']) ? 1 : 0;
			$format[] = '%d';
		}

		if (empty($update_data)) {
			return false;
		}

		$update_data['updated_at'] = AIPS_DateTime::now()->timestamp();
		$format[] = '%d';

		$result = $this->wpdb->update(
			$this->table_name,
			$update_data,
			array('id' => $id),
			$format,
			array('%d')
		) !== false;

		if ($result) {
			$this->invalidate_cache_domain('seo_profile', array('profile_id' => $id), 'seo_profile_updated');
		}

		return $result;
	}

	/**
	 * Delete an SEO profile.
	 *
	 * @param int $id Profile ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete($id) {
		$id = absint($id);
		if (!$id) {
			return false;
		}

		$result = $this->wpdb->delete($this->table_name, array('id' => $id), array('%d')) !== false;

		if ($result) {
			$this->invalidate_cache_domain('seo_profile', array('profile_id' => $id), 'seo_profile_deleted');
		}

		return $result;
	}

	/**
	 * Toggle profile active status.
	 *
	 * @param int  $id        Profile ID.
	 * @param bool $is_active Status.
	 * @return bool
	 */
	public function set_active($id, $is_active) {
		return $this->update($id, array('is_active' => (bool) $is_active));
	}

	/**
	 * Unserialize JSON fields on row object.
	 *
	 * @param object $row Database row object.
	 * @return void
	 */
	private function unserialize_row_fields($row) {
		$json_fields = array('fields', 'field_modes', 'field_patterns', 'field_prompts', 'schema_types', 'media_seo_fields');

		foreach ($json_fields as $prop) {
			if (isset($row->$prop) && is_string($row->$prop)) {
				$decoded = json_decode($row->$prop, true);
				$row->$prop = is_array($decoded) ? $decoded : array();
			}
		}
	}

	protected function repository_cache_group(): string {
		return 'aips_seo_profiles';
	}

	protected function repository_cache_policies(): array {
		return array(
			'seo_profiles.get_all'   => array(
				'tier'        => 'long',
				'tags'        => array('seo_profiles'),
				'description' => 'Cache SEO profiles list reads.',
			),
			'seo_profiles.get_by_id' => array(
				'tier'        => 'long',
				'tags'        => array('seo_profiles', 'seo_profile:{profile_id}'),
				'cache_null'  => false,
				'description' => 'Cache single SEO profile reads by ID.',
			),
		);
	}
}
