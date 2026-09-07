<?php
/**
 * Affiliate Links Repository
 *
 * Handles persistence for affiliate link mappings.
 *
 * @package AI_Post_Scheduler
 * @since 3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (!trait_exists('AIPS_Cacheable_Repository')) {
	require_once __DIR__ . '/trait-aips-cacheable-repository.php';
}

if (!trait_exists('AIPS_Repository_Tables')) {
	require_once __DIR__ . '/trait-aips-repository-tables.php';
}

class AIPS_Affiliate_Links_Repository {
	use AIPS_Cacheable_Repository;
	use AIPS_Repository_Tables;

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * @var string
	 */
	private $table;

	/**
	 * Valid CTA position values.
	 *
	 * @var string[]
	 */
	const VALID_POSITIONS = array( 'append', 'prepend', 'after_heading', 'after_text' );

	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		// table() is the AIPS_Repository_Tables trait method; $this->table is the
		// cached table-name property (PHP keeps method/property names separate).
		$this->table = $this->table('aips_affiliate_links');
	}

	/**
	 * Get a single mapping by ID.
	 *
	 * @param int $id Row ID.
	 * @return object|null Row object or null if not found.
	 */
	public function get_by_id( $id ) {
		return $this->cache_read(
			'affiliate_links.get_by_id',
			array(
				'id' => absint( $id ),
			),
			function() use ( $id ) {
				return $this->wpdb->get_row(
					$this->wpdb->prepare(
						"SELECT * FROM {$this->table} WHERE id = %d",
						absint( $id )
					)
				);
			}
		);
	}

	/**
	 * Get all mappings, ordered by tag.
	 *
	 * @param bool $enabled_only Whether to return only enabled mappings.
	 * @return object[]
	 */
	public function get_all( $enabled_only = false ) {
		return $this->cache_read(
			'affiliate_links.get_all',
			array(
				'enabled_only' => (bool) $enabled_only,
			),
			function() use ( $enabled_only ) {
				$where = $enabled_only ? 'WHERE enabled = 1' : '';
				return $this->wpdb->get_results(
					"SELECT * FROM {$this->table} {$where} ORDER BY tag ASC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				);
			}
		);
	}

	/**
	 * Get enabled mappings whose tag matches any of the given post tags.
	 *
	 * Matching is case-insensitive.
	 *
	 * @param string[] $tags Array of post tag names.
	 * @return object[] Matching mapping rows.
	 */
	public function get_enabled_by_tags( array $tags ) {
		if ( empty( $tags ) ) {
			return array();
		}

		$lower_tags = array_map( 'strtolower', $tags );
		sort( $lower_tags ); // Order-independent result; sort so equal tag sets share a cache key.

		return $this->cache_read(
			'affiliate_links.get_enabled_by_tags',
			array(
				'tags' => array_values( $lower_tags ),
			),
			function() use ( $lower_tags ) {
				$placeholders = implode( ', ', array_fill( 0, count( $lower_tags ), '%s' ) );

				return $this->wpdb->get_results(
					$this->wpdb->prepare(
						"SELECT * FROM {$this->table} WHERE enabled = 1 AND LOWER(tag) IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						...$lower_tags
					)
				);
			}
		);
	}

	/**
	 * Get paginated mappings for the admin list view.
	 *
	 * @param int    $per_page Number of results per page.
	 * @param int    $page     1-based page number.
	 * @param string $search   Optional. Filter by tag or label.
	 * @return object[]
	 */
	public function get_paginated( $per_page = 20, $page = 1, $search = '' ) {
		$per_page = max( 1, absint( $per_page ) );
		$page     = max( 1, absint( $page ) );

		return $this->cache_read(
			'affiliate_links.get_paginated',
			array(
				'per_page' => $per_page,
				'page'     => $page,
				'search'   => (string) $search,
			),
			function() use ( $per_page, $page, $search ) {
				$offset = ( $page - 1 ) * $per_page;

				if ( ! empty( $search ) ) {
					$like = '%' . $this->wpdb->esc_like( $search ) . '%';
					return $this->wpdb->get_results(
						$this->wpdb->prepare(
							"SELECT * FROM {$this->table} WHERE (tag LIKE %s OR label LIKE %s) ORDER BY tag ASC LIMIT %d OFFSET %d",
							$like,
							$like,
							$per_page,
							$offset
						)
					);
				}

				return $this->wpdb->get_results(
					$this->wpdb->prepare(
						"SELECT * FROM {$this->table} ORDER BY tag ASC LIMIT %d OFFSET %d",
						$per_page,
						$offset
					)
				);
			}
		);
	}

	/**
	 * Total count for paginated queries.
	 *
	 * @param string $search Optional. Filter by tag or label.
	 * @return int
	 */
	public function get_paginated_count( $search = '' ) {
		return $this->cache_read(
			'affiliate_links.get_paginated_count',
			array(
				'search' => (string) $search,
			),
			function() use ( $search ) {
				if ( ! empty( $search ) ) {
					$like = '%' . $this->wpdb->esc_like( $search ) . '%';
					return (int) $this->wpdb->get_var(
						$this->wpdb->prepare(
							"SELECT COUNT(*) FROM {$this->table} WHERE (tag LIKE %s OR label LIKE %s)",
							$like,
							$like
						)
					);
				}

				return (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		);
	}

	/**
	 * Insert a new affiliate link mapping.
	 *
	 * @param array $data Mapping data. Keys: tag, label, affiliate_url, enabled,
	 *                    cta_html, cta_position, cta_heading, cta_match_text,
	 *                    cta_max_insertions, use_ai_injection.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public function insert( array $data ) {
		$now    = AIPS_DateTime::now()->timestamp();
		$result = $this->wpdb->insert(
			$this->table,
			array(
				'tag'                  => sanitize_text_field( $data['tag'] ?? '' ),
				'label'                => sanitize_text_field( $data['label'] ?? '' ),
				'affiliate_url'        => esc_url_raw( $data['affiliate_url'] ?? '' ),
				'enabled'              => isset( $data['enabled'] ) ? (int) (bool) $data['enabled'] : 1,
				'cta_html'             => wp_kses_post( $data['cta_html'] ?? '' ),
				'cta_position'         => $this->sanitize_position( $data['cta_position'] ?? 'append' ),
				'cta_heading'          => sanitize_text_field( $data['cta_heading'] ?? '' ),
				'cta_match_text'       => sanitize_text_field( $data['cta_match_text'] ?? '' ),
				'cta_max_insertions'   => max( 1, absint( $data['cta_max_insertions'] ?? 1 ) ),
				'use_ai_injection'     => isset( $data['use_ai_injection'] ) ? (int) (bool) $data['use_ai_injection'] : 0,
				'created_at'           => $now,
				'updated_at'           => $now,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d' )
		);

		if ( $result ) {
			$this->invalidate_affiliate_links_cache( $this->wpdb->insert_id, 'affiliate_link_inserted' );
		}

		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Update an existing affiliate link mapping.
	 *
	 * @param int   $id   Row ID.
	 * @param array $data Fields to update (same keys as insert).
	 * @return int|false Rows updated or false on failure.
	 */
	public function update( $id, array $data ) {
		$update = array( 'updated_at' => AIPS_DateTime::now()->timestamp() );
		$format = array( '%d' );

		$map = array(
			'tag'                => array( 'sanitize_text_field', '%s' ),
			'label'              => array( 'sanitize_text_field', '%s' ),
			'affiliate_url'      => array( 'esc_url_raw', '%s' ),
			'cta_html'           => array( 'wp_kses_post', '%s' ),
			'cta_heading'        => array( 'sanitize_text_field', '%s' ),
			'cta_match_text'     => array( 'sanitize_text_field', '%s' ),
		);

		foreach ( $map as $key => $def ) {
			if ( array_key_exists( $key, $data ) ) {
				$update[ $key ] = call_user_func( $def[0], $data[ $key ] );
				$format[]       = $def[1];
			}
		}

		if ( array_key_exists( 'enabled', $data ) ) {
			$update['enabled'] = (int) (bool) $data['enabled'];
			$format[]          = '%d';
		}

		if ( array_key_exists( 'cta_position', $data ) ) {
			$update['cta_position'] = $this->sanitize_position( $data['cta_position'] );
			$format[]               = '%s';
		}

		if ( array_key_exists( 'cta_max_insertions', $data ) ) {
			$update['cta_max_insertions'] = max( 1, absint( $data['cta_max_insertions'] ) );
			$format[]                     = '%d';
		}

		if ( array_key_exists( 'use_ai_injection', $data ) ) {
			$update['use_ai_injection'] = (int) (bool) $data['use_ai_injection'];
			$format[]                   = '%d';
		}

		$result = $this->wpdb->update(
			$this->table,
			$update,
			array( 'id' => absint( $id ) ),
			$format,
			array( '%d' )
		);

		if ( $result ) {
			$this->invalidate_affiliate_links_cache( $id, 'affiliate_link_updated' );
		}

		return $result;
	}

	/**
	 * Toggle the enabled state of a mapping.
	 *
	 * @param int  $id      Row ID.
	 * @param bool $enabled New state.
	 * @return int|false
	 */
	public function set_enabled( $id, $enabled ) {
		$result = $this->wpdb->update(
			$this->table,
			array(
				'enabled'    => (int) (bool) $enabled,
				'updated_at' => AIPS_DateTime::now()->timestamp(),
			),
			array( 'id' => absint( $id ) ),
			array( '%d', '%d' ),
			array( '%d' )
		);

		if ( $result ) {
			$this->invalidate_affiliate_links_cache( $id, 'affiliate_link_enabled_toggled' );
		}

		return $result;
	}

	/**
	 * Delete a mapping by ID.
	 *
	 * @param int $id Row ID.
	 * @return int|false
	 */
	public function delete( $id ) {
		$result = $this->wpdb->delete(
			$this->table,
			array( 'id' => absint( $id ) ),
			array( '%d' )
		);

		if ( $result ) {
			$this->invalidate_affiliate_links_cache( $id, 'affiliate_link_deleted' );
		}

		return $result;
	}

	/**
	 * Delete all mappings.
	 *
	 * @return int|false
	 */
	public function delete_all() {
		$result = $this->wpdb->query( "DELETE FROM {$this->table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( $result ) {
			$this->invalidate_affiliate_links_cache( 0, 'affiliate_links_deleted_all' );
		}

		return $result;
	}

	/**
	 * Sanitize CTA position, falling back to 'append'.
	 *
	 * @param string $position Raw position value.
	 * @return string
	 */
	private function sanitize_position( $position ) {
		return in_array( $position, self::VALID_POSITIONS, true ) ? $position : 'append';
	}

	/**
	 * Return the repository cache group for affiliate-link reads.
	 *
	 * @return string
	 */
	protected function repository_cache_group(): string {
		return 'aips_affiliate_links';
	}

	/**
	 * Return the explicit repository cache policies for affiliate-link reads.
	 *
	 * All reads are over the affiliate-links table alone (no external joins), so
	 * every cached read carries the broad `affiliate_links` tag that every write
	 * invalidates.
	 *
	 * @return array
	 */
	protected function repository_cache_policies(): array {
		return array(
			'affiliate_links.get_by_id' => array(
				'tier'        => 'medium',
				'ttl'         => 300,
				'tags'        => array( 'affiliate_links', 'affiliate_link:{id}' ),
				'cache_null'  => false,
				'description' => 'Cache single affiliate-link mapping reads by ID.',
			),
			'affiliate_links.get_all' => array(
				'tier'        => 'medium',
				'ttl'         => 300,
				'tags'        => array( 'affiliate_links' ),
				'description' => 'Cache affiliate-link mapping list reads.',
			),
			'affiliate_links.get_enabled_by_tags' => array(
				'tier'        => 'medium',
				'ttl'         => 300,
				'tags'        => array( 'affiliate_links' ),
				'description' => 'Cache tag-matched enabled mapping reads used during CTA injection.',
			),
			'affiliate_links.get_paginated' => array(
				'tier'        => 'medium',
				'ttl'         => 300,
				'tags'        => array( 'affiliate_links' ),
				'description' => 'Cache paginated admin list reads.',
			),
			'affiliate_links.get_paginated_count' => array(
				'tier'        => 'medium',
				'ttl'         => 300,
				'tags'        => array( 'affiliate_links' ),
				'description' => 'Cache paginated admin list counts.',
			),
		);
	}

	/**
	 * Invalidate affiliate-link read caches after a write.
	 *
	 * Bumps the broad `affiliate_links` tag (present on every cached read) plus an
	 * optional id-scoped tag when a single mapping is affected.
	 *
	 * @param int    $id Mapping ID, or 0 when unknown/bulk.
	 * @param string $reason Invalidation reason.
	 * @return void
	 */
	private function invalidate_affiliate_links_cache( $id, $reason ) {
		$tags = array( 'affiliate_links' );

		$id = absint( $id );
		if ( $id > 0 ) {
			$tags[] = 'affiliate_link:' . $id;
		}

		$this->invalidate_cache_tags( $tags, (string) $reason );
	}
}
