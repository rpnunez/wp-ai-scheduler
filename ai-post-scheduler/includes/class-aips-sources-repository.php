<?php
/**
 * Sources Repository
 *
 * Database abstraction layer for trusted sources used to guide AI content generation.
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Sources_Repository
 *
 * Repository pattern implementation for trusted source data access.
 * Encapsulates all database operations related to the aips_sources table.
 */
class AIPS_Sources_Repository {

	/**
	 * @var string The sources table name (with prefix).
	 */
	private $table_name;

	/**
	 * @var string The source group terms table name (with prefix).
	 */
	private $groups_table;

	/**
	 * @var string The source dossier records table name (with prefix).
	 */
	private $dossiers_table;

	/**
	 * @var wpdb WordPress database abstraction object.
	 */
	private $wpdb;

	/**
	 * Initialize the repository.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb          = $wpdb;
		$this->table_name    = $wpdb->prefix . 'aips_sources';
		$this->groups_table  = $wpdb->prefix . 'aips_source_group_terms';
		$this->dossiers_table = $wpdb->prefix . 'aips_source_dossiers';
	}

	/**
	 * Get all sources with optional filtering.
	 *
	 * @param bool $active_only Return only active sources. Default false.
	 * @return array Array of source objects.
	 */
	public function get_all($active_only = false) {
		$where = $active_only ? 'WHERE is_active = 1' : '';
		return $this->wpdb->get_results(
			"SELECT * FROM {$this->table_name} {$where} ORDER BY created_at ASC"
		);
	}

	/**
	 * Get a single source by ID.
	 *
	 * @param int $id Source ID.
	 * @return object|null Source object or null if not found.
	 */
	public function get_by_id($id) {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$id
			)
		);
	}

	/**
	 * Get only the URLs of all active sources.
	 *
	 * Used by the prompt builder to inject trusted domains into AI prompts.
	 *
	 * @return string[] Array of URL strings.
	 */
	public function get_active_urls() {
		$rows = $this->wpdb->get_results(
			"SELECT url FROM {$this->table_name} WHERE is_active = 1 ORDER BY created_at ASC"
		);

		return array_map(function ($row) {
			return $row->url;
		}, $rows);
	}

	/**
	 * Create a new source.
	 *
	 * @param array $data {
	 *     Source data.
	 *
	 *     @type string $url         The source URL (required).
	 *     @type string $label       Short human-readable label.
	 *     @type string $description Optional notes about the source.
	 *     @type int    $is_active   Active flag (1 or 0). Default 1.
	 * }
	 * @return int|false Inserted ID on success, false on failure.
	 */
	public function create($data) {
		$insert_data = array(
			'url'         => esc_url_raw($data['url']),
			'label'       => isset($data['label']) ? sanitize_text_field($data['label']) : '',
			'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
			'is_active'   => isset($data['is_active']) ? (int) $data['is_active'] : 1,
		);

		$format = array('%s', '%s', '%s', '%d');

		$result = $this->wpdb->insert($this->table_name, $insert_data, $format);

		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Update an existing source.
	 *
	 * @param int   $id   Source ID.
	 * @param array $data Data to update (same structure as create).
	 * @return bool True on success, false on failure.
	 */
	public function update($id, $data) {
		$update_data = array();
		$format      = array();

		if (isset($data['url'])) {
			$update_data['url'] = esc_url_raw($data['url']);
			$format[]           = '%s';
		}

		if (isset($data['label'])) {
			$update_data['label'] = sanitize_text_field($data['label']);
			$format[]             = '%s';
		}

		if (isset($data['description'])) {
			$update_data['description'] = sanitize_textarea_field($data['description']);
			$format[]                   = '%s';
		}

		if (isset($data['is_active'])) {
			$update_data['is_active'] = (int) $data['is_active'];
			$format[]                 = '%d';
		}

		if (empty($update_data)) {
			return false;
		}

		return $this->wpdb->update(
			$this->table_name,
			$update_data,
			array('id' => $id),
			$format,
			array('%d')
		) !== false;
	}

	/**
	 * Delete a source by ID.
	 *
	 * @param int $id Source ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete($id) {
		return $this->wpdb->delete(
			$this->table_name,
			array('id' => $id),
			array('%d')
		) !== false;
	}

	/**
	 * Set the active status for a source.
	 *
	 * @param int  $id        Source ID.
	 * @param bool $is_active Whether the source should be active.
	 * @return bool True on success, false on failure.
	 */
	public function set_active($id, $is_active) {
		return $this->update($id, array('is_active' => $is_active ? 1 : 0));
	}

	/**
	 * Check whether a URL already exists in the table (case-insensitive).
	 *
	 * @param string $url        URL to check.
	 * @param int    $exclude_id Optional. Exclude this ID from the check. Default 0.
	 * @return bool True if the URL already exists.
	 */
	public function url_exists($url, $exclude_id = 0) {
		$url = esc_url_raw($url);

		if ($exclude_id > 0) {
			$count = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->table_name} WHERE url = %s AND id != %d",
					$url,
					$exclude_id
				)
			);
		} else {
			$count = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->table_name} WHERE url = %s",
					$url
				)
			);
		}

		return $count > 0;
	}

	/**
	 * Get the term IDs (source group IDs) associated with a source.
	 *
	 * @param int $source_id Source ID.
	 * @return int[] Array of term IDs.
	 */
	/**
	 * Get the source group term IDs for a single source.
	 *
	 * @param int $source_id Source ID.
	 * @return int[] Array of term IDs.
	 */
	public function get_source_term_ids($source_id) {
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT term_id FROM {$this->groups_table} WHERE source_id = %d",
				$source_id
			)
		);

		return array_map(function ($row) {
			return (int) $row->term_id;
		}, $rows);
	}

	/**
	 * Get source group term IDs for multiple sources in a single query.
	 *
	 * @param int[] $source_ids Array of source IDs.
	 * @return array<int,int[]> Map of source_id => array of term_ids.
	 */
	public function get_term_ids_for_sources(array $source_ids) {
		if (empty($source_ids)) {
			return array();
		}

		$source_ids   = array_map('intval', $source_ids);
		$placeholders = implode(',', array_fill(0, count($source_ids), '%d'));

		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT source_id, term_id FROM {$this->groups_table} WHERE source_id IN ($placeholders)",
				...$source_ids
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		$map = array();
		foreach ($rows as $row) {
			$sid = (int) $row->source_id;
			if (!isset($map[$sid])) {
				$map[$sid] = array();
			}
			$map[$sid][] = (int) $row->term_id;
		}
		return $map;
	}

	/**
	 * Set the source group term assignments for a source.
	 *
	 * Replaces all existing term assignments with the provided term IDs.
	 *
	 * @param int   $source_id Source ID.
	 * @param int[] $term_ids  Array of term IDs (may be empty to clear all groups).
	 * @return void
	 */
	public function set_source_terms($source_id, array $term_ids) {
		// Remove all existing assignments for this source.
		$this->wpdb->delete(
			$this->groups_table,
			array('source_id' => $source_id),
			array('%d')
		);

		foreach ($term_ids as $term_id) {
			$term_id = (int) $term_id;
			if ($term_id > 0) {
				$this->wpdb->insert(
					$this->groups_table,
					array('source_id' => $source_id, 'term_id' => $term_id),
					array('%d', '%d')
				);
			}
		}
	}

	/**
	 * Get the active URLs of sources belonging to specific source groups.
	 *
	 * @param int[] $term_ids   Term IDs (source group IDs) to filter by.
	 * @param bool  $active_only Only return active sources. Default true.
	 * @return string[] Array of URL strings.
	 */
	public function get_urls_by_group_term_ids(array $term_ids, $active_only = true) {
		if (empty($term_ids)) {
			return array();
		}

		$placeholders = implode(',', array_fill(0, count($term_ids), '%d'));
		$active_clause = $active_only ? 'AND s.is_active = 1' : '';

		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$query = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT DISTINCT s.url FROM {$this->table_name} s
			INNER JOIN {$this->groups_table} sgt ON sgt.source_id = s.id
			WHERE sgt.term_id IN ($placeholders) $active_clause
			ORDER BY s.created_at ASC",
			...$term_ids
		);
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		$rows = $this->wpdb->get_results($query);

		return array_map(function ($row) {
			return $row->url;
		}, $rows);
	}

	/**
	 * Delete all source-group term assignments for a given source (used on source delete).
	 *
	 * @param int $source_id Source ID.
	 * @return void
	 */
	public function delete_source_terms($source_id) {
		$this->wpdb->delete(
			$this->groups_table,
			array('source_id' => $source_id),
			array('%d')
		);
	}

	/**
	 * Get available dossier relation types.
	 *
	 * @return array<string,string> Map of relation type => label.
	 */
	public function get_dossier_relation_types() {
		return array(
			'author_topic'      => __('Author Topic', 'ai-post-scheduler'),
			'research_item'     => __('Research Item', 'ai-post-scheduler'),
			'story_budget_item' => __('Story Budget Item', 'ai-post-scheduler'),
		);
	}

	/**
	 * Get dossier verification status labels.
	 *
	 * @return array<string,string> Map of status => label.
	 */
	public function get_dossier_verification_statuses() {
		return array(
			'pending'      => __('Pending Verification', 'ai-post-scheduler'),
			'verified'     => __('Verified', 'ai-post-scheduler'),
			'needs_review' => __('Needs Review', 'ai-post-scheduler'),
			'disputed'     => __('Disputed', 'ai-post-scheduler'),
		);
	}

	/**
	 * Get dossier records with optional filtering.
	 *
	 * @param array $args Optional query args.
	 * @return array Array of dossier objects.
	 */
	public function get_dossiers($args = array()) {
		$defaults = array(
			'relation_type' => '',
			'relation_id'   => 0,
			'source_id'     => 0,
			'limit'         => 100,
		);

		$args = wp_parse_args($args, $defaults);

		$where = array('1=1');
		$values = array();

		if (!empty($args['relation_type'])) {
			$where[]  = 'd.relation_type = %s';
			$values[] = sanitize_key($args['relation_type']);
		}

		if (!empty($args['relation_id'])) {
			$where[]  = 'd.relation_id = %d';
			$values[] = absint($args['relation_id']);
		}

		if (!empty($args['source_id'])) {
			$where[]  = 'd.source_id = %d';
			$values[] = absint($args['source_id']);
		}

		$limit = max(1, min(500, absint($args['limit'])));

		$query = "
			SELECT d.*, s.label AS source_label
			FROM {$this->dossiers_table} d
			LEFT JOIN {$this->table_name} s ON s.id = d.source_id
			WHERE " . implode(' AND ', $where) . '
			ORDER BY d.updated_at DESC, d.id DESC
			LIMIT %d
		';

		$values[] = $limit;

		$results = $this->wpdb->get_results($this->wpdb->prepare($query, $values));

		return array_map(array($this, 'hydrate_dossier_record'), $results);
	}

	/**
	 * Get a single dossier record by ID.
	 *
	 * @param int $id Dossier ID.
	 * @return object|null Dossier object or null if not found.
	 */
	public function get_dossier_by_id($id) {
		$query = $this->wpdb->prepare(
			"SELECT d.*, s.label AS source_label
			FROM {$this->dossiers_table} d
			LEFT JOIN {$this->table_name} s ON s.id = d.source_id
			WHERE d.id = %d",
			$id
		);

		$row = $this->wpdb->get_row($query);

		return $row ? $this->hydrate_dossier_record($row) : null;
	}

	/**
	 * Create a new dossier record.
	 *
	 * @param array $data Dossier data.
	 * @return int|false Insert ID or false on failure.
	 */
	public function create_dossier($data) {
		$insert_data = $this->sanitize_dossier_data($data);
		$result      = $this->wpdb->insert(
			$this->dossiers_table,
			$insert_data,
			array('%d', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s')
		);

		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Update a dossier record.
	 *
	 * @param int   $id   Dossier ID.
	 * @param array $data Partial dossier data.
	 * @return bool True on success.
	 */
	public function update_dossier($id, $data) {
		$update_data = $this->sanitize_dossier_data($data, true);
		if (empty($update_data)) {
			return false;
		}

		$format = array();
		foreach ($update_data as $key => $value) {
			if (in_array($key, array('source_id', 'relation_id', 'trust_rating', 'citation_required'), true)) {
				$format[] = '%d';
			} else {
				$format[] = '%s';
			}
		}

		return $this->wpdb->update(
			$this->dossiers_table,
			$update_data,
			array('id' => $id),
			$format,
			array('%d')
		) !== false;
	}

	/**
	 * Delete a dossier record.
	 *
	 * @param int $id Dossier ID.
	 * @return bool True on success.
	 */
	public function delete_dossier($id) {
		return $this->wpdb->delete(
			$this->dossiers_table,
			array('id' => $id),
			array('%d')
		) !== false;
	}

	/**
	 * Get dossier records for a single editorial relation.
	 *
	 * @param string $relation_type Relation type.
	 * @param int    $relation_id   Relation ID.
	 * @return array Array of dossier records.
	 */
	public function get_dossiers_for_relation($relation_type, $relation_id) {
		return $this->get_dossiers(
			array(
				'relation_type' => $relation_type,
				'relation_id'   => $relation_id,
				'limit'         => 250,
			)
		);
	}

	/**
	 * Build pre-publish checklist data for author-topic-linked draft posts.
	 *
	 * @param int[] $topic_ids Topic IDs.
	 * @return array<int,array<string,mixed>> Topic ID => checklist data.
	 */
	public function get_dossier_checklist_by_author_topic_ids(array $topic_ids) {
		$topic_ids = array_values(array_filter(array_map('absint', $topic_ids)));
		if (empty($topic_ids)) {
			return array();
		}

		$placeholders = implode(',', array_fill(0, count($topic_ids), '%d'));

		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$query = $this->wpdb->prepare(
			"SELECT relation_id,
				COUNT(*) AS dossier_count,
				SUM(CASE WHEN citation_required = 1 THEN 1 ELSE 0 END) AS citation_required_count,
				SUM(CASE WHEN verification_status != 'verified' THEN 1 ELSE 0 END) AS unverified_count
			FROM {$this->dossiers_table}
			WHERE relation_type = 'author_topic'
				AND relation_id IN ($placeholders)
			GROUP BY relation_id",
			...$topic_ids
		);
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		$rows = $this->wpdb->get_results($query);
		$map  = array();

		foreach ($rows as $row) {
			$topic_id = (int) $row->relation_id;
			$citation_required = (int) $row->citation_required_count;
			$unverified        = (int) $row->unverified_count;

			$messages = array();
			if ($citation_required > 0) {
				$messages[] = sprintf(
					_n('%d dossier item still needs citation coverage', '%d dossier items still need citation coverage', $citation_required, 'ai-post-scheduler'),
					$citation_required
				);
			}
			if ($unverified > 0) {
				$messages[] = sprintf(
					_n('%d dossier item is not verified', '%d dossier items are not verified', $unverified, 'ai-post-scheduler'),
					$unverified
				);
			}

			$map[$topic_id] = array(
				'dossier_count'            => (int) $row->dossier_count,
				'citation_required_count'  => $citation_required,
				'unverified_count'         => $unverified,
				'needs_attention'          => ($citation_required > 0 || $unverified > 0),
				'summary'                  => empty($messages)
					? __('Research dossier checks are complete.', 'ai-post-scheduler')
					: implode('. ', $messages) . '.',
			);
		}

		return $map;
	}

	/**
	 * Sanitize dossier input before persistence.
	 *
	 * @param array $data Raw dossier data.
	 * @param bool  $partial True when sanitizing a partial update payload.
	 * @return array Sanitized dossier data.
	 */
	private function sanitize_dossier_data($data, $partial = false) {
		$allowed_relation_types       = array_keys($this->get_dossier_relation_types());
		$allowed_verification_status  = array_keys($this->get_dossier_verification_statuses());
		$sanitized                    = array();

		if (!$partial || array_key_exists('source_id', $data)) {
			$sanitized['source_id'] = !empty($data['source_id']) ? absint($data['source_id']) : 0;
		}

		if (!$partial || array_key_exists('relation_type', $data)) {
			$relation_type = isset($data['relation_type']) ? sanitize_key($data['relation_type']) : 'author_topic';
			$sanitized['relation_type'] = in_array($relation_type, $allowed_relation_types, true) ? $relation_type : 'author_topic';
		}

		if (!$partial || array_key_exists('relation_id', $data)) {
			$sanitized['relation_id'] = isset($data['relation_id']) ? absint($data['relation_id']) : 0;
		}

		if (!$partial || array_key_exists('source_url', $data)) {
			$sanitized['source_url'] = isset($data['source_url']) ? esc_url_raw($data['source_url']) : '';
		}

		if (!$partial || array_key_exists('source_type', $data)) {
			$sanitized['source_type'] = isset($data['source_type']) ? sanitize_text_field($data['source_type']) : '';
		}

		if (!$partial || array_key_exists('quote_summary', $data)) {
			$sanitized['quote_summary'] = isset($data['quote_summary']) ? sanitize_textarea_field($data['quote_summary']) : '';
		}

		if (!$partial || array_key_exists('trust_rating', $data)) {
			$trust_rating = isset($data['trust_rating']) ? absint($data['trust_rating']) : 3;
			$sanitized['trust_rating'] = min(5, max(1, $trust_rating));
		}

		if (!$partial || array_key_exists('citation_required', $data)) {
			$sanitized['citation_required'] = !empty($data['citation_required']) ? 1 : 0;
		}

		if (!$partial || array_key_exists('verification_status', $data)) {
			$verification_status = isset($data['verification_status']) ? sanitize_key($data['verification_status']) : 'pending';
			$sanitized['verification_status'] = in_array($verification_status, $allowed_verification_status, true) ? $verification_status : 'pending';
		}

		if (!$partial || array_key_exists('editor_notes', $data)) {
			$sanitized['editor_notes'] = isset($data['editor_notes']) ? sanitize_textarea_field($data['editor_notes']) : '';
		}

		return $sanitized;
	}

	/**
	 * Normalize dossier row values before returning them to callers.
	 *
	 * @param object $row Raw DB row.
	 * @return object Normalized dossier row.
	 */
	private function hydrate_dossier_record($row) {
		$row->id                = isset($row->id) ? (int) $row->id : 0;
		$row->source_id         = isset($row->source_id) ? (int) $row->source_id : 0;
		$row->relation_id       = isset($row->relation_id) ? (int) $row->relation_id : 0;
		$row->trust_rating      = isset($row->trust_rating) ? (int) $row->trust_rating : 3;
		$row->citation_required = !empty($row->citation_required) ? 1 : 0;

		return $row;
	}
}
