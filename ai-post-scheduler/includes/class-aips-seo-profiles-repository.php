<?php
/**
 * SEO Profiles Repository
 *
 * Database abstraction layer for SEO profiles. Allows creating, reading,
 * updating, and deleting reusable SEO profiles that define which specific
 * SEO fields to generate, target provider preference, and field-specific prompts.
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
			// Default to core 3 fields if none selected
			$fields = array('focus_keyword', 'seo_title', 'meta_description');
		}

		$field_prompts = isset($data['field_prompts']) ? (is_array($data['field_prompts']) ? $data['field_prompts'] : json_decode($data['field_prompts'], true)) : array();

		$insert_data = array(
			'name'                => sanitize_text_field($data['name']),
			'description'         => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
			'provider_id'         => isset($data['provider_id']) ? sanitize_key($data['provider_id']) : 'auto',
			'fields'              => wp_json_encode(array_values(array_unique(array_map('sanitize_key', $fields)))),
			'field_prompts'       => wp_json_encode($field_prompts),
			'custom_instructions'=> isset($data['custom_instructions']) ? sanitize_textarea_field($data['custom_instructions']) : '',
			'is_active'           => !empty($data['is_active']) ? 1 : 0,
			'created_at'          => $now,
			'updated_at'          => $now,
		);

		$format = array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d');

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

		if (isset($data['field_prompts'])) {
			$field_prompts = is_array($data['field_prompts']) ? $data['field_prompts'] : json_decode($data['field_prompts'], true);
			$update_data['field_prompts'] = wp_json_encode(is_array($field_prompts) ? $field_prompts : array());
			$format[] = '%s';
		}

		if (isset($data['custom_instructions'])) {
			$update_data['custom_instructions'] = sanitize_textarea_field($data['custom_instructions']);
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
		if (isset($row->fields) && is_string($row->fields)) {
			$decoded = json_decode($row->fields, true);
			$row->fields = is_array($decoded) ? $decoded : array();
		}
		if (isset($row->field_prompts) && is_string($row->field_prompts)) {
			$decoded = json_decode($row->field_prompts, true);
			$row->field_prompts = is_array($decoded) ? $decoded : array();
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
