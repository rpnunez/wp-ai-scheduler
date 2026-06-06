<?php
/**
 * Internal Links Repository
 *
 * Handles persistence for suggested internal links between posts.
 *
 * @package AI_Post_Scheduler
 * @since 2.3.0
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!trait_exists('AIPS_Cacheable_Repository')) {
	require_once __DIR__ . '/trait-aips-cacheable-repository.php';
}

/**
 * Class AIPS_Internal_Links_Repository
 *
 * Manages CRUD operations for the aips_internal_links table.
 */
class AIPS_Internal_Links_Repository {
	use AIPS_Cacheable_Repository;

	/**
	 * @var wpdb WordPress database object.
	 */
	private $wpdb;

	/**
	 * @var string Table name with prefix.
	 */
	private $table;

	/**
	 * Valid status values for internal links.
	 *
	 * @var string[]
	 */
	const VALID_STATUSES = array( 'pending', 'accepted', 'rejected', 'inserted' );

	/**
	 * Initialize the repository.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'aips_internal_links';
	}

	/**
	 * Get a single suggestion by ID.
	 *
	 * @param int $id Row ID.
	 * @return object|null Row object with source/target post titles, or null if not found.
	 */
	public function get_by_id($id) {
		$id = absint( $id );

		return $this->cache_read(
			'internal_links.get_by_id',
			array(
				'internal_link_id' => $id,
			),
			function() use ( $id ) {
				return $this->wpdb->get_row(
					$this->wpdb->prepare(
						"SELECT il.*,
							sp.post_title AS source_post_title,
							tp.post_title AS target_post_title
						FROM {$this->table} il
						LEFT JOIN {$this->wpdb->posts} sp ON il.source_post_id = sp.ID
						LEFT JOIN {$this->wpdb->posts} tp ON il.target_post_id = tp.ID
						WHERE il.id = %d",
						$id
					)
				);
			}
		);
	}

	/**
	 * Get all internal link suggestions for a source post.
	 *
	 * @param int    $source_post_id Source post ID.
	 * @param string $status         Optional. Filter by status. Empty string returns all.
	 * @return object[] Array of row objects.
	 */
	public function get_by_source_post($source_post_id, $status = '') {
		$source_post_id = absint( $source_post_id );
		$status         = in_array( $status, self::VALID_STATUSES, true ) ? $status : '';

		return $this->cache_read(
			'internal_links.get_by_source_post',
			array(
				'source_post_id' => $source_post_id,
				'status'         => $status,
			),
			function() use ( $source_post_id, $status ) {
				$where = $this->wpdb->prepare(
					'WHERE source_post_id = %d',
					$source_post_id
				);

				if ( $status ) {
					$where .= $this->wpdb->prepare( ' AND status = %s', $status );
				}

				return $this->wpdb->get_results(
					"SELECT * FROM {$this->table} {$where} ORDER BY similarity_score DESC"
				);
			}
		);
	}

	/**
	 * Get paginated internal links across all posts.
	 *
	 * @param int    $per_page Number of results per page.
	 * @param int    $page     1-based page number.
	 * @param string $status   Optional. Filter by status.
	 * @param string $search   Optional. Search term applied to source/target post titles.
	 * @return object[] Array of row objects with extra post title columns.
	 */
	public function get_paginated($per_page = 20, $page = 1, $status = '', $search = '') {
		$per_page = max(1, absint($per_page));
		$offset   = ($page - 1) * $per_page;
		$status   = in_array( $status, self::VALID_STATUSES, true ) ? $status : '';
		$search   = sanitize_text_field( $search );

		return $this->cache_read(
			'internal_links.get_paginated',
			array(
				'per_page' => $per_page,
				'page'     => absint( $page ),
				'status'   => $status,
				'search'   => $search,
			),
			function() use ( $per_page, $offset, $status, $search ) {
				$where_clauses = array('1=1');
				$params        = array();

				if ( $status ) {
					$where_clauses[] = 'il.status = %s';
					$params[]        = $status;
				}

				if ( ! empty( $search ) ) {
					$like              = '%' . $this->wpdb->esc_like($search) . '%';
					$where_clauses[]   = '(sp.post_title LIKE %s OR tp.post_title LIKE %s)';
					$params[]          = $like;
					$params[]          = $like;
				}

				$where    = 'WHERE ' . implode(' AND ', $where_clauses);
				$params[] = $per_page;
				$params[] = $offset;

				return $this->wpdb->get_results(
					$this->wpdb->prepare(
						"SELECT il.*,
							sp.post_title AS source_post_title,
							tp.post_title AS target_post_title,
							sp.post_status AS source_post_status,
							tp.post_status AS target_post_status
						FROM {$this->table} il
						LEFT JOIN {$this->wpdb->posts} sp ON il.source_post_id = sp.ID
						LEFT JOIN {$this->wpdb->posts} tp ON il.target_post_id = tp.ID
						{$where}
						ORDER BY il.created_at DESC
						LIMIT %d OFFSET %d",
						...$params
					)
				);
			}
		);
	}

	/**
	 * Get the total count for paginated queries (mirrors get_paginated filters).
	 *
	 * @param string $status Optional. Filter by status.
	 * @param string $search Optional. Search term.
	 * @return int Total count.
	 */
	public function get_paginated_count($status = '', $search = '') {
		$status = in_array( $status, self::VALID_STATUSES, true ) ? $status : '';
		$search = sanitize_text_field( $search );

		return $this->cache_read(
			'internal_links.get_paginated_count',
			array(
				'status' => $status,
				'search' => $search,
			),
			function() use ( $status, $search ) {
				$where_clauses = array('1=1');
				$params        = array();

				if ( $status ) {
					$where_clauses[] = 'il.status = %s';
					$params[]        = $status;
				}

				if ( ! empty( $search ) ) {
					$like            = '%' . $this->wpdb->esc_like($search) . '%';
					$where_clauses[] = '(sp.post_title LIKE %s OR tp.post_title LIKE %s)';
					$params[]        = $like;
					$params[]        = $like;
				}

				$where = 'WHERE ' . implode(' AND ', $where_clauses);

				if (!empty($params)) {
					return (int) $this->wpdb->get_var(
						$this->wpdb->prepare(
							"SELECT COUNT(*)
							FROM {$this->table} il
							LEFT JOIN {$this->wpdb->posts} sp ON il.source_post_id = sp.ID
							LEFT JOIN {$this->wpdb->posts} tp ON il.target_post_id = tp.ID
							{$where}",
							...$params
						)
					);
				}

				return (int) $this->wpdb->get_var(
					"SELECT COUNT(*)
					FROM {$this->table} il
					LEFT JOIN {$this->wpdb->posts} sp ON il.source_post_id = sp.ID
					LEFT JOIN {$this->wpdb->posts} tp ON il.target_post_id = tp.ID
					{$where}"
				);
			}
		);
	}

	/**
	 * Check whether a specific source→target pair already exists.
	 *
	 * @param int $source_post_id Source post ID.
	 * @param int $target_post_id Target post ID.
	 * @return bool
	 */
	public function exists($source_post_id, $target_post_id) {
		$source_post_id = absint( $source_post_id );
		$target_post_id = absint( $target_post_id );

		return $this->cache_read(
			'internal_links.exists',
			array(
				'source_post_id' => $source_post_id,
				'target_post_id' => $target_post_id,
			),
			function() use ( $source_post_id, $target_post_id ) {
				$count = $this->wpdb->get_var(
					$this->wpdb->prepare(
						"SELECT COUNT(*) FROM {$this->table} WHERE source_post_id = %d AND target_post_id = %d",
						$source_post_id,
						$target_post_id
					)
				);

				return (int) $count > 0;
			}
		);
	}

	/**
	 * Insert a new internal link suggestion.
	 *
	 * @param int    $source_post_id   Source post ID.
	 * @param int    $target_post_id   Target post ID.
	 * @param float  $similarity_score Cosine similarity score (0–1).
	 * @param string $anchor_text      Optional. Suggested anchor text.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public function insert($source_post_id, $target_post_id, $similarity_score, $anchor_text = '') {
		$now = AIPS_DateTime::now()->timestamp();

		$result = $this->wpdb->insert(
			$this->table,
			array(
				'source_post_id'   => absint($source_post_id),
				'target_post_id'   => absint($target_post_id),
				'similarity_score' => (float) $similarity_score,
				'anchor_text'      => sanitize_text_field($anchor_text),
				'status'           => 'pending',
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array('%d', '%d', '%f', '%s', '%s', '%d', '%d')
		);

		if ( $result ) {
			$this->invalidate_internal_links_cache(
				array(
					'internal_link_id' => $this->wpdb->insert_id,
					'source_post_id'   => absint( $source_post_id ),
					'target_post_id'   => absint( $target_post_id ),
				),
				'internal_link_inserted'
			);
		}

		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Update the status of an internal link.
	 *
	 * @param int    $id     Row ID.
	 * @param string $status New status (pending|accepted|rejected|inserted).
	 * @return int|false Number of updated rows or false on failure.
	 */
	public function update_status($id, $status) {
		if (!in_array($status, self::VALID_STATUSES, true)) {
			return false;
		}

		$row = $this->get_by_id( $id );

		$result = $this->wpdb->update(
			$this->table,
			array(
				'status'     => $status,
				'updated_at' => AIPS_DateTime::now()->timestamp(),
			),
			array('id' => absint($id)),
			array('%s', '%d'),
			array('%d')
		);

		if ( false !== $result ) {
			$this->invalidate_internal_links_cache(
				array(
					'internal_link_id' => absint( $id ),
					'source_post_id'   => $row ? absint( $row->source_post_id ) : 0,
					'target_post_id'   => $row ? absint( $row->target_post_id ) : 0,
				),
				'internal_link_status_updated'
			);
		}

		return $result;
	}

	/**
	 * Update the anchor text of a suggestion.
	 *
	 * @param int    $id          Row ID.
	 * @param string $anchor_text New anchor text.
	 * @return int|false Number of updated rows or false on failure.
	 */
	public function update_anchor_text($id, $anchor_text) {
		$row = $this->get_by_id( $id );

		$result = $this->wpdb->update(
			$this->table,
			array(
				'anchor_text' => sanitize_text_field($anchor_text),
				'updated_at'  => AIPS_DateTime::now()->timestamp(),
			),
			array('id' => absint($id)),
			array('%s', '%d'),
			array('%d')
		);

		if ( false !== $result ) {
			$this->invalidate_internal_links_cache(
				array(
					'internal_link_id' => absint( $id ),
					'source_post_id'   => $row ? absint( $row->source_post_id ) : 0,
					'target_post_id'   => $row ? absint( $row->target_post_id ) : 0,
				),
				'internal_link_anchor_updated'
			);
		}

		return $result;
	}

	/**
	 * Delete a specific suggestion by ID.
	 *
	 * @param int $id Row ID.
	 * @return int|false Number of deleted rows or false on failure.
	 */
	public function delete($id) {
		$row    = $this->get_by_id( $id );
		$result = $this->wpdb->delete(
			$this->table,
			array('id' => absint($id)),
			array('%d')
		);

		if ( false !== $result ) {
			$this->invalidate_internal_links_cache(
				array(
					'internal_link_id' => absint( $id ),
					'source_post_id'   => $row ? absint( $row->source_post_id ) : 0,
					'target_post_id'   => $row ? absint( $row->target_post_id ) : 0,
				),
				'internal_link_deleted'
			);
		}

		return $result;
	}

	/**
	 * Delete all suggestions for a source post.
	 *
	 * @param int $source_post_id Source post ID.
	 * @return int|false Number of deleted rows or false on failure.
	 */
	public function delete_by_source_post($source_post_id) {
		$source_post_id = absint( $source_post_id );
		$result         = $this->wpdb->delete(
			$this->table,
			array('source_post_id' => $source_post_id),
			array('%d')
		);

		if ( false !== $result ) {
			$this->invalidate_internal_links_cache(
				array(
					'source_post_id' => $source_post_id,
				),
				'internal_links_deleted_by_source'
			);
		}

		return $result;
	}

	/**
	 * Delete only PENDING suggestions for a source post.
	 *
	 * Accepted, rejected, and inserted suggestions are preserved so that
	 * editorial decisions are not lost when suggestions are regenerated.
	 *
	 * @param int $source_post_id Source post ID.
	 * @return int|false Number of deleted rows or false on failure.
	 */
	public function delete_pending_by_source_post($source_post_id) {
		$source_post_id = absint( $source_post_id );
		$result         = $this->wpdb->delete(
			$this->table,
			array(
				'source_post_id' => $source_post_id,
				'status'         => 'pending',
			),
			array('%d', '%s')
		);

		if ( false !== $result ) {
			$this->invalidate_internal_links_cache(
				array(
					'source_post_id' => $source_post_id,
				),
				'internal_links_pending_deleted_by_source'
			);
		}

		return $result;
	}

	/**
	 * Delete all suggestions for a target post (e.g. when a post is trashed).
	 *
	 * @param int $target_post_id Target post ID.
	 * @return int|false Number of deleted rows or false on failure.
	 */
	public function delete_by_target_post($target_post_id) {
		$target_post_id = absint( $target_post_id );
		$result         = $this->wpdb->delete(
			$this->table,
			array('target_post_id' => $target_post_id),
			array('%d')
		);

		if ( false !== $result ) {
			$this->invalidate_internal_links_cache(
				array(
					'target_post_id' => $target_post_id,
				),
				'internal_links_deleted_by_target'
			);
		}

		return $result;
	}

	/**
	 * Delete all suggestions (both as source or target) for a post.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return int Total rows deleted.
	 */
	public function delete_all_for_post($post_id) {
		$post_id = absint($post_id);
		$a       = (int) $this->wpdb->delete( $this->table, array('source_post_id' => $post_id), array('%d') );
		$b       = (int) $this->wpdb->delete( $this->table, array('target_post_id' => $post_id), array('%d') );

		$this->invalidate_internal_links_cache(
			array(
				'source_post_id' => $post_id,
				'target_post_id' => $post_id,
			),
			'internal_links_deleted_for_post'
		);

		return $a + $b;
	}

	/**
	 * Get per-status summary counts.
	 *
	 * @return array Associative array of status => count.
	 */
	public function get_status_counts() {
		return $this->cache_read(
			'internal_links.get_status_counts',
			array(),
			function() {
				$rows = $this->wpdb->get_results(
					"SELECT status, COUNT(*) AS cnt FROM {$this->table} GROUP BY status"
				);

				$counts = array_fill_keys(self::VALID_STATUSES, 0);
				foreach ($rows as $row) {
					$counts[$row->status] = (int) $row->cnt;
				}

				return $counts;
			}
		);
	}

	/**
	 * Delete all link suggestions.
	 *
	 * @return int|false Number of rows deleted or false on failure.
	 */
	public function delete_all() {
		$result = $this->wpdb->query( "DELETE FROM {$this->table}" );
		if ( false !== $result ) {
			$this->invalidate_cache_domain( 'internal_link', array(), 'internal_links_deleted_all' );
		}
		return $result;
	}

	/**
	 * Return the repository cache group for internal-links reads.
	 *
	 * @return string
	 */
	protected function repository_cache_group(): string {
		return 'aips_internal_links';
	}

	/**
	 * Declare repository cache policies.
	 *
	 * @return array
	 */
	protected function repository_cache_policies(): array {
		return array(
			'internal_links.get_by_id' => array(
				'tier'        => 'medium',
				'cache_null'  => false,
				'description' => 'Cache enriched single-row internal link lookups.',
			),
			'internal_links.get_by_source_post' => array(
				'tier'        => 'medium',
				'description' => 'Cache source-post scoped internal link lists.',
			),
			'internal_links.get_paginated' => array(
				'tier'        => 'medium',
				'description' => 'Cache paginated internal-link admin list queries.',
			),
			'internal_links.get_paginated_count' => array(
				'tier'        => 'medium',
				'description' => 'Cache internal-link pagination count queries.',
			),
			'internal_links.exists' => array(
				'tier'        => 'medium',
				'description' => 'Cache source-target existence checks.',
			),
			'internal_links.get_status_counts' => array(
				'tier'        => 'medium',
				'description' => 'Cache per-status internal-link summary counts.',
			),
		);
	}

	/**
	 * Invalidate internal-link-domain cache tags.
	 *
	 * @param array  $context Invalidation context.
	 * @param string $reason Invalidation reason.
	 * @return void
	 */
	private function invalidate_internal_links_cache( array $context, $reason ) {
		$this->invalidate_cache_domain( 'internal_link', $context, $reason );
	}
}