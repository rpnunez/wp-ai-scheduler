<?php
/**
 * Prompt Profile Version Service
 *
 * Orchestration layer for Prompt Profile version history, auto-snapshotting,
 * diffing, and rollback. Depends on AIPS_Prompt_Profiles_Repository and
 * AIPS_Prompt_Profile_Versions_Repository for persistence and on
 * AIPS_Prompt_Profile_Diff_Engine for change-summary generation. Performs no
 * direct $wpdb calls.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Prompt_Profile_Version_Service
 */
class AIPS_Prompt_Profile_Version_Service {

	/**
	 * The 9 known stage field keys shared across Prompt Profiles.
	 *
	 * @var string[]
	 */
	const STAGE_KEYS = array(
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

	/**
	 * Human-readable labels for each stage key, used to build change summaries.
	 *
	 * @var array<string, string>
	 */
	private static $stage_labels = null;

	/**
	 * @var AIPS_Prompt_Profiles_Repository
	 */
	private $profiles_repo;

	/**
	 * @var AIPS_Prompt_Profile_Versions_Repository
	 */
	private $versions_repo;

	/**
	 * @var AIPS_Prompt_Profile_Diff_Engine
	 */
	private $diff_engine;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Prompt_Profiles_Repository|null          $profiles_repo Optional profiles repository.
	 * @param AIPS_Prompt_Profile_Versions_Repository|null  $versions_repo Optional versions repository.
	 * @param AIPS_Prompt_Profile_Diff_Engine|null           $diff_engine   Optional diff engine.
	 */
	public function __construct($profiles_repo = null, $versions_repo = null, $diff_engine = null) {
		$this->profiles_repo = $profiles_repo ?: AIPS_Prompt_Profiles_Repository::instance();
		$this->versions_repo = $versions_repo ?: AIPS_Prompt_Profile_Versions_Repository::instance();
		$this->diff_engine   = $diff_engine ?: new AIPS_Prompt_Profile_Diff_Engine();
	}

	/**
	 * Update a profile while automatically snapshotting its PRE-mutation state.
	 *
	 * This is the single call controllers should use for any update that may
	 * change stage prompts: it loads the profile's current (pre-mutation)
	 * state, snapshots it via create_snapshot() with a diff-derived change
	 * summary, then performs the actual update through the profiles
	 * repository. Wrapping the mutation end-to-end means the controller has
	 * one call to make and cannot forget to version an edit.
	 *
	 * @param int      $profile_id Profile ID to update.
	 * @param array    $data       New profile data (same shape accepted by AIPS_Prompt_Profiles_Repository::update()).
	 * @param int|null $user_id    WP user ID performing the update, or null.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function update_with_snapshot($profile_id, array $data, $user_id = null) {
		$profile_id = absint($profile_id);
		if (!$profile_id) {
			return new WP_Error('invalid_profile_id', __('Invalid profile ID.', 'ai-post-scheduler'));
		}

		$current = $this->profiles_repo->get_by_id($profile_id);
		if (!$current) {
			return new WP_Error('not_found', __('Prompt profile not found.', 'ai-post-scheduler'));
		}

		$old_stages = $this->extract_stages($current);
		$new_stages = $this->merge_stage_updates($old_stages, $data);

		$summary = $this->build_change_summary($old_stages, $new_stages);

		// Snapshot the PRE-mutation (current, about-to-be-overwritten) state so
		// the version history always records what the profile looked like
		// immediately before this edit, with a summary describing what this
		// edit is about to change.
		$snapshot_result = $this->versions_repo->create_snapshot($profile_id, $old_stages, array(), $summary, $user_id);
		if (is_wp_error($snapshot_result)) {
			return $snapshot_result;
		}

		return $this->profiles_repo->update($profile_id, $data);
	}

	/**
	 * Restore ALL 9 stage fields of a profile from a historical version snapshot.
	 *
	 * Validates the version belongs to the given profile, snapshots the
	 * profile's current state first (so the restore itself is undoable), then
	 * writes every stage field from the version snapshot back onto the live
	 * profile.
	 *
	 * @param int $profile_id Profile ID to restore.
	 * @param int $version_id Version row ID to restore from.
	 * @param int|null $user_id WP user ID performing the restore, or null.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function restore_version($profile_id, $version_id, $user_id = null) {
		$profile_id = absint($profile_id);
		$version_id = absint($version_id);

		$validation = $this->validate_version_for_profile($profile_id, $version_id);
		if (is_wp_error($validation)) {
			return $validation;
		}

		list($profile, $version) = $validation;

		$old_stages = $this->extract_stages($profile);
		$restore_stages = $this->normalize_stage_values($version->stages);

		$summary = sprintf(
			/* translators: %d: version number being restored. */
			__('Snapshot before restoring version %d.', 'ai-post-scheduler'),
			(int) $version->version_number
		);

		$snapshot_result = $this->versions_repo->create_snapshot($profile_id, $old_stages, array(), $summary, $user_id);
		if (is_wp_error($snapshot_result)) {
			return $snapshot_result;
		}

		return $this->profiles_repo->update($profile_id, $restore_stages);
	}

	/**
	 * Restore a single named stage field of a profile from a historical version
	 * snapshot, leaving the other 8 stage fields untouched on the live profile.
	 *
	 * @param int      $profile_id Profile ID to restore.
	 * @param int      $version_id Version row ID to restore from.
	 * @param string   $stage      Stage key to restore (must be one of STAGE_KEYS).
	 * @param int|null $user_id    WP user ID performing the restore, or null.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function restore_stage($profile_id, $version_id, $stage, $user_id = null) {
		$profile_id = absint($profile_id);
		$version_id = absint($version_id);
		$stage      = sanitize_key($stage);

		if (!in_array($stage, self::STAGE_KEYS, true)) {
			return new WP_Error('invalid_stage', __('Invalid stage key.', 'ai-post-scheduler'));
		}

		$validation = $this->validate_version_for_profile($profile_id, $version_id);
		if (is_wp_error($validation)) {
			return $validation;
		}

		list($profile, $version) = $validation;

		$old_stages = $this->extract_stages($profile);
		$version_stages = $this->normalize_stage_values($version->stages);

		$label = $this->get_stage_label($stage);
		$summary = sprintf(
			/* translators: %1$s: stage label, %2$d: version number being restored from. */
			__('Snapshot before restoring stage "%1$s" from version %2$d.', 'ai-post-scheduler'),
			$label,
			(int) $version->version_number
		);

		$snapshot_result = $this->versions_repo->create_snapshot($profile_id, $old_stages, array(), $summary, $user_id);
		if (is_wp_error($snapshot_result)) {
			return $snapshot_result;
		}

		$update_data = array(
			$stage => isset($version_stages[$stage]) ? $version_stages[$stage] : null,
		);

		return $this->profiles_repo->update($profile_id, $update_data);
	}

	/**
	 * Build a diff between two arbitrary historical versions, or between a
	 * single historical version and the current LIVE profile state.
	 *
	 * @param int      $profile_id  Profile ID the versions must belong to.
	 * @param int      $version_id_a First version ID to compare ("old" side).
	 * @param int|string|null $version_id_b Second version ID to compare ("new" side),
	 *                                       or 0/null/'current' to diff against the
	 *                                       live profile state instead of a stored version.
	 * @return array|WP_Error Array shaped like AIPS_Prompt_Profile_Diff_Engine::diff_stages(), or WP_Error.
	 */
	public function diff_versions($profile_id, $version_id_a, $version_id_b = null) {
		$profile_id = absint($profile_id);
		$profile = $this->profiles_repo->get_by_id($profile_id);
		if (!$profile) {
			return new WP_Error('not_found', __('Prompt profile not found.', 'ai-post-scheduler'));
		}

		$version_a = $this->get_validated_version($profile_id, $version_id_a);
		if (is_wp_error($version_a)) {
			return $version_a;
		}
		$stages_a = $this->normalize_stage_values($version_a->stages);

		if ($this->is_current_marker($version_id_b)) {
			$stages_b = $this->extract_stages($profile);
			$label_b  = 'current';
		} else {
			$version_b = $this->get_validated_version($profile_id, $version_id_b);
			if (is_wp_error($version_b)) {
				return $version_b;
			}
			$stages_b = $this->normalize_stage_values($version_b->stages);
			$label_b  = (int) $version_b->version_number;
		}

		return array(
			'version_a' => (int) $version_a->version_number,
			'version_b' => $label_b,
			'stages'    => $this->diff_engine->diff_stages($stages_a, $stages_b),
		);
	}

	/**
	 * Determine whether a "second version" argument means "diff against the
	 * current live profile state" rather than a stored version ID.
	 *
	 * @param mixed $value Raw value (int, numeric string, 'current', empty, or null).
	 * @return bool
	 */
	private function is_current_marker($value) {
		if ($value === null || $value === '' || $value === 0 || $value === '0') {
			return true;
		}

		if (is_string($value) && strtolower(trim($value)) === 'current') {
			return true;
		}

		return false;
	}

	/**
	 * Load and validate a version row belongs to the given profile.
	 *
	 * @param int $profile_id Profile ID.
	 * @param int $version_id Version row ID.
	 * @return object|WP_Error Version object on success, WP_Error otherwise.
	 */
	private function get_validated_version($profile_id, $version_id) {
		$version_id = absint($version_id);
		if (!$version_id) {
			return new WP_Error('invalid_version_id', __('Invalid version ID.', 'ai-post-scheduler'));
		}

		$version = $this->versions_repo->get_version($version_id);
		if (!$version) {
			return new WP_Error('version_not_found', __('Prompt profile version not found.', 'ai-post-scheduler'));
		}

		if ((int) $version->profile_id !== (int) $profile_id) {
			return new WP_Error('version_profile_mismatch', __('This version does not belong to the specified prompt profile.', 'ai-post-scheduler'));
		}

		return $version;
	}

	/**
	 * Validate a profile ID and a version ID together, returning both loaded
	 * objects on success.
	 *
	 * @param int $profile_id Profile ID.
	 * @param int $version_id Version row ID.
	 * @return array{0: object, 1: object}|WP_Error [profile, version] on success, WP_Error otherwise.
	 */
	private function validate_version_for_profile($profile_id, $version_id) {
		$profile_id = absint($profile_id);
		if (!$profile_id) {
			return new WP_Error('invalid_profile_id', __('Invalid profile ID.', 'ai-post-scheduler'));
		}

		$profile = $this->profiles_repo->get_by_id($profile_id);
		if (!$profile) {
			return new WP_Error('not_found', __('Prompt profile not found.', 'ai-post-scheduler'));
		}

		$version = $this->get_validated_version($profile_id, $version_id);
		if (is_wp_error($version)) {
			return $version;
		}

		return array($profile, $version);
	}

	/**
	 * Extract the 9 stage fields from a live profile row into a plain array.
	 *
	 * @param object $profile Profile row object.
	 * @return array<string, string>
	 */
	private function extract_stages($profile) {
		$stages = array();
		foreach (self::STAGE_KEYS as $stage_key) {
			$stages[$stage_key] = isset($profile->$stage_key) ? (string) $profile->$stage_key : '';
		}
		return $stages;
	}

	/**
	 * Normalize a decoded stages array (from a version snapshot) to always
	 * contain all 9 known keys as strings.
	 *
	 * @param array $stages Raw decoded stages array.
	 * @return array<string, string>
	 */
	private function normalize_stage_values($stages) {
		$normalized = array();
		foreach (self::STAGE_KEYS as $stage_key) {
			$value = isset($stages[$stage_key]) ? $stages[$stage_key] : '';
			$normalized[$stage_key] = $value === null ? '' : (string) $value;
		}
		return $normalized;
	}

	/**
	 * Merge incoming update data (which may use alternate/legacy stage key
	 * aliases) onto the current stage snapshot to compute the NEW stage state,
	 * without mutating the database. Only known stage keys present in $data
	 * override the current value; everything else is left as-is.
	 *
	 * @param array $old_stages Current stage snapshot.
	 * @param array $data       Incoming update payload.
	 * @return array<string, string> New stage snapshot.
	 */
	private function merge_stage_updates(array $old_stages, array $data) {
		$new_stages = $old_stages;

		foreach (self::STAGE_KEYS as $stage_key) {
			if (array_key_exists($stage_key, $data)) {
				$value = $data[$stage_key];
				$new_stages[$stage_key] = $value === null ? '' : (string) $value;
			}
		}

		return $new_stages;
	}

	/**
	 * Build a human-readable change summary from the stages that differ
	 * between two snapshots, e.g. "Changed: Post Title, Content Shell".
	 *
	 * @param array $old_stages Previous stage snapshot.
	 * @param array $new_stages New stage snapshot.
	 * @return string Change summary, or a generic fallback if nothing changed.
	 */
	private function build_change_summary(array $old_stages, array $new_stages) {
		$changed_keys = $this->diff_engine->get_changed_stage_keys($old_stages, $new_stages);

		if (empty($changed_keys)) {
			return __('No stage prompt changes.', 'ai-post-scheduler');
		}

		$labels = array_map(array($this, 'get_stage_label'), $changed_keys);

		return sprintf(
			/* translators: %s: comma-separated list of changed stage labels. */
			__('Changed: %s', 'ai-post-scheduler'),
			implode(', ', $labels)
		);
	}

	/**
	 * Get the human-readable label for a stage key.
	 *
	 * @param string $stage_key Stage key.
	 * @return string Human-readable label.
	 */
	private function get_stage_label($stage_key) {
		$labels = $this->get_stage_labels();
		return isset($labels[$stage_key]) ? $labels[$stage_key] : $stage_key;
	}

	/**
	 * Lazily build and memoize the stage key => human label map.
	 *
	 * @return array<string, string>
	 */
	private function get_stage_labels() {
		if (self::$stage_labels === null) {
			self::$stage_labels = array(
				'title_prompt'            => __('Post Title', 'ai-post-scheduler'),
				'title_followup_prompt'   => __('Follow-up Title', 'ai-post-scheduler'),
				'content_prompt'          => __('Content Shell', 'ai-post-scheduler'),
				'excerpt_prompt'          => __('Post Excerpt', 'ai-post-scheduler'),
				'excerpt_followup_prompt' => __('Follow-up Excerpt', 'ai-post-scheduler'),
				'featured_image_prompt'   => __('Featured Image', 'ai-post-scheduler'),
				'topic_ideas_prompt'      => __('Author Topic Ideas', 'ai-post-scheduler'),
				'metadata_prompt'         => __('SEO & Metadata', 'ai-post-scheduler'),
				'taxonomy_prompt'         => __('Taxonomy Classification', 'ai-post-scheduler'),
			);
		}
		return self::$stage_labels;
	}
}
