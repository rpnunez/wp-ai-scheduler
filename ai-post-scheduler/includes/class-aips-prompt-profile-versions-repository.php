<?php
/**
 * Prompt Profile Versions Repository
 *
 * Database abstraction layer for Prompt Profile version snapshots used by the
 * version history, diff engine, and rollback features.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!trait_exists('AIPS_Cacheable_Repository')) {
	require_once __DIR__ . '/trait-aips-cacheable-repository.php';
}

/**
 * Class AIPS_Prompt_Profile_Versions_Repository
 *
 * Repository pattern implementation for Prompt Profile version snapshot data access.
 * All $wpdb calls for the aips_prompt_profile_versions table live here only.
 */
class AIPS_Prompt_Profile_Versions_Repository {
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
		$this->table_name = $wpdb->prefix . 'aips_prompt_profile_versions';
	}

	/**
	 * Create a new version snapshot for a profile.
	 *
	 * The version_number is auto-computed as get_latest_version_number() + 1.
	 *
	 * @param int         $profile_id Profile ID this snapshot belongs to.
	 * @param array       $stages     Associative array of the 9 stage prompt fields.
	 * @param array       $parameters Reserved for Phase 2 (generation parameters). May be empty.
	 * @param string      $summary    Human-readable change summary.
	 * @param int|null    $user_id    WP user ID who triggered this snapshot, or null.
	 * @return int|WP_Error Inserted version row ID, or WP_Error on failure.
	 */
	public function create_snapshot($profile_id, array $stages, array $parameters, $summary, $user_id = null) {
		$profile_id = absint($profile_id);
		if (!$profile_id) {
			return new WP_Error('invalid_profile_id', __('Invalid profile ID.', 'ai-post-scheduler'));
		}

		$next_version = $this->get_latest_version_number($profile_id) + 1;

		$data = array(
			'profile_id'     => $profile_id,
			'version_number' => $next_version,
			'stages'         => wp_json_encode($stages),
			'parameters'     => !empty($parameters) ? wp_json_encode($parameters) : null,
			'change_summary' => is_string($summary) ? sanitize_textarea_field($summary) : '',
			'created_by'     => $user_id ? absint($user_id) : null,
			'created_at'     => AIPS_DateTime::now()->timestamp(),
		);

		$result = $this->wpdb->insert($this->table_name, $data);
		if ($result === false) {
			return new WP_Error('db_insert_failed', $this->wpdb->last_error ?: __('Failed to create prompt profile version snapshot.', 'ai-post-scheduler'));
		}

		$this->invalidate_cache_domain('prompt_profile_version', array('profile_id' => $profile_id), 'version_created');
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Get all versions for a profile, newest first.
	 *
	 * @param int $profile_id Profile ID.
	 * @return array Array of version objects with stages/parameters JSON-decoded to arrays.
	 */
	public function get_versions($profile_id) {
		$profile_id = absint($profile_id);
		if (!$profile_id) {
			return array();
		}

		$versions = $this->cache_read(
			'prompt_profile_versions.get_versions',
			array('profile_id' => $profile_id),
			function() use ($profile_id) {
				return $this->wpdb->get_results($this->wpdb->prepare(
					"SELECT * FROM {$this->table_name} WHERE profile_id = %d ORDER BY version_number DESC",
					$profile_id
				));
			}
		);

		if (is_array($versions)) {
			foreach ($versions as $version) {
				$this->decode_version($version);
			}
		}

		return is_array($versions) ? $versions : array();
	}

	/**
	 * Get a single version by its own ID.
	 *
	 * @param int $id Version row ID.
	 * @return object|null Version object with stages/parameters JSON-decoded, or null if not found.
	 */
	public function get_version($id) {
		$id = absint($id);
		if (!$id) {
			return null;
		}

		$version = $this->cache_read(
			'prompt_profile_versions.get_version',
			array('version_id' => $id),
			function() use ($id) {
				return $this->wpdb->get_row($this->wpdb->prepare(
					"SELECT * FROM {$this->table_name} WHERE id = %d LIMIT 1",
					$id
				));
			}
		);

		return $this->decode_version($version);
	}

	/**
	 * Get the latest (highest) version number recorded for a profile.
	 *
	 * @param int $profile_id Profile ID.
	 * @return int Latest version number, or 0 if none exist.
	 */
	public function get_latest_version_number($profile_id) {
		$profile_id = absint($profile_id);
		if (!$profile_id) {
			return 0;
		}

		$latest = $this->wpdb->get_var($this->wpdb->prepare(
			"SELECT MAX(version_number) FROM {$this->table_name} WHERE profile_id = %d",
			$profile_id
		));

		return $latest ? (int) $latest : 0;
	}

	/**
	 * Decode a version row's JSON columns (stages/parameters) into arrays.
	 *
	 * @param object|null $version Version row object.
	 * @return object|null Decorated version object.
	 */
	private function decode_version($version) {
		if (!$version || !is_object($version)) {
			return $version;
		}

		$version->stages = $this->decode_json_field($version->stages);
		$version->parameters = $this->decode_json_field($version->parameters);

		return $version;
	}

	/**
	 * Decode a stored JSON column value into an array.
	 *
	 * @param mixed $value Raw column value.
	 * @return array Decoded array, or an empty array if null/invalid.
	 */
	private function decode_json_field($value) {
		if (empty($value) || !is_string($value)) {
			return array();
		}

		$decoded = json_decode($value, true);
		return is_array($decoded) ? $decoded : array();
	}
}
