<?php
/**
 * Prompt Profiles Repository
 *
 * Database abstraction layer for Prompt Profiles CRUD and caching.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!trait_exists('AIPS_Cacheable_Repository')) {
	require_once __DIR__ . '/trait-aips-cacheable-repository.php';
}

/**
 * Class AIPS_Prompt_Profiles_Repository
 *
 * Repository pattern implementation for Prompt Profile data access.
 */
class AIPS_Prompt_Profiles_Repository {
	use AIPS_Cacheable_Repository;

	/**
	 * @var self|null Singleton instance.
	 */
	private static $instance = null;

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

	/**
	 * @var string Table name (with prefix).
	 */
	private $table_name;

	/**
	 * @var wpdb WordPress database abstraction object.
	 */
	private $wpdb;

	/**
	 * Initialize the repository.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table_name = $wpdb->prefix . 'aips_prompt_profiles';
	}

	/**
	 * Get all prompt profiles.
	 *
	 * @param bool $active_only Whether to return only active profiles.
	 * @return array Array of profile objects.
	 */
	public function get_all($active_only = false) {
		$active_only = (bool) $active_only;
		return $this->cache_read(
			'prompt_profiles.get_all',
			array('active_only' => $active_only),
			function() use ($active_only) {
				$where = $active_only ? "WHERE is_active = 1" : "";
				return $this->wpdb->get_results("SELECT * FROM {$this->table_name} {$where} ORDER BY is_default DESC, name ASC");
			}
		);
	}

	/**
	 * Get a single prompt profile by ID.
	 *
	 * @param int $id Profile ID.
	 * @return object|null Profile object or null if not found.
	 */
	public function get_by_id($id) {
		$id = absint($id);
		if (!$id) {
			return null;
		}

		return $this->cache_read(
			'prompt_profiles.get_by_id',
			array('profile_id' => $id),
			function() use ($id) {
				return $this->wpdb->get_row($this->wpdb->prepare(
					"SELECT * FROM {$this->table_name} WHERE id = %d LIMIT 1",
					$id
				));
			}
		);
	}

	/**
	 * Get a single prompt profile by slug.
	 *
	 * @param string $slug Profile slug.
	 * @return object|null Profile object or null if not found.
	 */
	public function get_by_slug($slug) {
		$slug = sanitize_title($slug);
		if (empty($slug)) {
			return null;
		}

		return $this->cache_read(
			'prompt_profiles.get_by_slug',
			array('slug' => $slug),
			function() use ($slug) {
				return $this->wpdb->get_row($this->wpdb->prepare(
					"SELECT * FROM {$this->table_name} WHERE slug = %s LIMIT 1",
					$slug
				));
			}
		);
	}

	/**
	 * Get the global default prompt profile.
	 *
	 * @return object|null Default profile object or null.
	 */
	public function get_default() {
		return $this->cache_read(
			'prompt_profiles.get_default',
			array(),
			function() {
				$profile = $this->wpdb->get_row(
					"SELECT * FROM {$this->table_name} WHERE is_default = 1 AND is_active = 1 LIMIT 1"
				);

				if (!$profile) {
					$profile = $this->wpdb->get_row(
						"SELECT * FROM {$this->table_name} WHERE is_active = 1 ORDER BY id ASC LIMIT 1"
					);
				}

				return $profile;
			}
		);
	}

	/**
	 * Create a new prompt profile.
	 *
	 * @param array $data Profile data.
	 * @return int|WP_Error Inserted profile ID or WP_Error on failure.
	 */
	public function create(array $data) {
		$clean = $this->sanitize_profile_data($data);
		if (is_wp_error($clean)) {
			return $clean;
		}

		if (empty($clean['name'])) {
			return new WP_Error('empty_name', __('Profile name is required.', 'ai-post-scheduler'));
		}

		if (empty($clean['slug'])) {
			$clean['slug'] = sanitize_title($clean['name']);
		}

		// Ensure unique slug
		$clean['slug'] = $this->generate_unique_slug($clean['slug']);

		$now = AIPS_DateTime::now()->timestamp();
		$clean['created_at'] = $now;
		$clean['updated_at'] = $now;

		if (!empty($clean['is_default'])) {
			$this->clear_all_defaults();
		}

		$result = $this->wpdb->insert($this->table_name, $clean);
		if ($result === false) {
			return new WP_Error('db_insert_failed', $this->wpdb->last_error ?: __('Failed to create prompt profile.', 'ai-post-scheduler'));
		}

		$this->cache_invalidate_all();
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Update an existing prompt profile.
	 *
	 * @param int   $id   Profile ID.
	 * @param array $data Profile data.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function update($id, array $data) {
		$id = absint($id);
		if (!$id) {
			return new WP_Error('invalid_id', __('Invalid profile ID.', 'ai-post-scheduler'));
		}

		$clean = $this->sanitize_profile_data($data, true);
		if (is_wp_error($clean)) {
			return $clean;
		}

		if (isset($clean['name']) && empty($clean['name'])) {
			return new WP_Error('empty_name', __('Profile name cannot be empty.', 'ai-post-scheduler'));
		}

		if (!empty($clean['slug'])) {
			$clean['slug'] = $this->generate_unique_slug($clean['slug'], $id);
		}

		if (!empty($clean['is_default'])) {
			$this->clear_all_defaults($id);
		}

		$clean['updated_at'] = AIPS_DateTime::now()->timestamp();

		$result = $this->wpdb->update($this->table_name, $clean, array('id' => $id));
		if ($result === false) {
			return new WP_Error('db_update_failed', $this->wpdb->last_error ?: __('Failed to update prompt profile.', 'ai-post-scheduler'));
		}

		$this->cache_invalidate_all();
		return true;
	}

	/**
	 * Delete a prompt profile.
	 *
	 * @param int $id Profile ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete($id) {
		$id = absint($id);
		if (!$id) {
			return new WP_Error('invalid_id', __('Invalid profile ID.', 'ai-post-scheduler'));
		}

		$profile = $this->get_by_id($id);
		if (!$profile) {
			return new WP_Error('not_found', __('Prompt profile not found.', 'ai-post-scheduler'));
		}

		$result = $this->wpdb->delete($this->table_name, array('id' => $id), array('%d'));
		if ($result === false) {
			return new WP_Error('db_delete_failed', $this->wpdb->last_error ?: __('Failed to delete prompt profile.', 'ai-post-scheduler'));
		}

		// If this was the default, make the first remaining active profile default
		if (!empty($profile->is_default)) {
			$first = $this->wpdb->get_row("SELECT id FROM {$this->table_name} WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
			if ($first) {
				$this->wpdb->update($this->table_name, array('is_default' => 1), array('id' => (int) $first->id));
			}
		}

		$this->cache_invalidate_all();
		return true;
	}

	/**
	 * Set a profile as the global default.
	 *
	 * @param int $id Profile ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function set_default($id) {
		$id = absint($id);
		if (!$id) {
			return new WP_Error('invalid_id', __('Invalid profile ID.', 'ai-post-scheduler'));
		}

		$profile = $this->get_by_id($id);
		if (!$profile) {
			return new WP_Error('not_found', __('Prompt profile not found.', 'ai-post-scheduler'));
		}

		$this->clear_all_defaults($id);
		$this->wpdb->update(
			$this->table_name,
			array(
				'is_default' => 1,
				'updated_at' => AIPS_DateTime::now()->timestamp(),
			),
			array('id' => $id)
		);

		$this->cache_invalidate_all();
		return true;
	}

	/**
	 * Unmark all profiles as default except optionally an excluded ID.
	 *
	 * @param int $except_id Optional profile ID to preserve.
	 * @return void
	 */
	private function clear_all_defaults($except_id = 0) {
		if ($except_id > 0) {
			$this->wpdb->query($this->wpdb->prepare(
				"UPDATE {$this->table_name} SET is_default = 0 WHERE id != %d",
				$except_id
			));
		} else {
			$this->wpdb->query("UPDATE {$this->table_name} SET is_default = 0");
		}
	}

	/**
	 * Generate a unique slug.
	 *
	 * @param string $slug Desired slug.
	 * @param int    $exclude_id Profile ID to exclude.
	 * @return string Unique slug.
	 */
	private function generate_unique_slug($slug, $exclude_id = 0) {
		$slug = sanitize_title($slug);
		$original_slug = $slug;
		$suffix = 1;

		while (true) {
			$query = "SELECT id FROM {$this->table_name} WHERE slug = %s";
			$params = array($slug);
			if ($exclude_id > 0) {
				$query .= " AND id != %d";
				$params[] = $exclude_id;
			}
			$query .= " LIMIT 1";

			$exists = $this->wpdb->get_var($this->wpdb->prepare($query, $params));
			if (!$exists) {
				return $slug;
			}

			$suffix++;
			$slug = $original_slug . '-' . $suffix;
		}
	}

	/**
	 * Sanitize profile data array.
	 *
	 * @param array $data Raw input data.
	 * @param bool  $is_update Whether this is an update.
	 * @return array Sanitized data.
	 */
	private function sanitize_profile_data(array $data, $is_update = false) {
		$clean = array();

		if (isset($data['name'])) {
			$clean['name'] = sanitize_text_field($data['name']);
		}
		if (isset($data['slug'])) {
			$clean['slug'] = sanitize_title($data['slug']);
		}
		if (isset($data['description'])) {
			$clean['description'] = sanitize_textarea_field($data['description']);
		}
		if (isset($data['is_default'])) {
			$clean['is_default'] = !empty($data['is_default']) ? 1 : 0;
		}
		if (isset($data['is_active'])) {
			$clean['is_active'] = !empty($data['is_active']) ? 1 : 0;
		}

		$prompt_fields = array(
			'title_prompt',
			'title_followup_prompt',
			'content_prompt',
			'excerpt_prompt',
			'excerpt_followup_prompt',
			'featured_image_prompt',
			'topic_ideas_prompt',
			'metadata_prompt',
			'taxonomy_prompt',
		);

		foreach ($prompt_fields as $field) {
			if (isset($data[$field])) {
				// Retain formatting and template placeholders, trim whitespace
				$val = trim((string) $data[$field]);
				$clean[$field] = $val !== '' ? $val : null;
			}
		}

		return $clean;
	}
}
