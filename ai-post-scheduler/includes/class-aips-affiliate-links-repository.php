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

class AIPS_Affiliate_Links_Repository {

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
		$this->table = $wpdb->prefix . 'aips_affiliate_links';
	}

	/**
	 * Get a single mapping by ID.
	 *
	 * @param int $id Row ID.
	 * @return object|null Row object or null if not found.
	 */
	public function get_by_id( $id ) {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d",
				absint( $id )
			)
		);
	}

	/**
	 * Get all mappings, ordered by tag.
	 *
	 * @param bool $enabled_only Whether to return only enabled mappings.
	 * @return object[]
	 */
	public function get_all( $enabled_only = false ) {
		$where = $enabled_only ? 'WHERE enabled = 1' : '';
		return $this->wpdb->get_results(
			"SELECT * FROM {$this->table} {$where} ORDER BY tag ASC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

		$placeholders = implode( ', ', array_fill( 0, count( $tags ), '%s' ) );
		$lower_tags   = array_map( 'strtolower', $tags );

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE enabled = 1 AND LOWER(tag) IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$lower_tags
			)
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
		$offset   = ( $page - 1 ) * $per_page;

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

	/**
	 * Total count for paginated queries.
	 *
	 * @param string $search Optional. Filter by tag or label.
	 * @return int
	 */
	public function get_paginated_count( $search = '' ) {
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
				'keywords'             => sanitize_text_field( $data['keywords'] ?? '' ),
				'category_ids'         => is_array( $data['category_ids'] ?? null ) ? (string) wp_json_encode( array_map( 'absint', $data['category_ids'] ) ) : sanitize_text_field( $data['category_ids'] ?? '' ),
				'prompt_guidance'      => sanitize_textarea_field( $data['prompt_guidance'] ?? '' ),
				'is_global_fallback'   => isset( $data['is_global_fallback'] ) ? (int) (bool) $data['is_global_fallback'] : 0,
				'created_at'           => $now,
				'updated_at'           => $now,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d' )
		);

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
			'keywords'           => array( 'sanitize_text_field', '%s' ),
			'prompt_guidance'    => array( 'sanitize_textarea_field', '%s' ),
		);

		if ( array_key_exists( 'category_ids', $data ) ) {
			$update['category_ids'] = is_array( $data['category_ids'] ) ? (string) wp_json_encode( array_map( 'absint', $data['category_ids'] ) ) : sanitize_text_field( $data['category_ids'] );
			$format[]               = '%s';
		}

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

		if ( array_key_exists( 'is_global_fallback', $data ) ) {
			$update['is_global_fallback'] = (int) (bool) $data['is_global_fallback'];
			$format[]                     = '%d';
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

		return $this->wpdb->update(
			$this->table,
			$update,
			array( 'id' => absint( $id ) ),
			$format,
			array( '%d' )
		);
	}

	/**
	 * Toggle the enabled state of a mapping.
	 *
	 * @param int  $id      Row ID.
	 * @param bool $enabled New state.
	 * @return int|false
	 */
	public function set_enabled( $id, $enabled ) {
		return $this->wpdb->update(
			$this->table,
			array(
				'enabled'    => (int) (bool) $enabled,
				'updated_at' => AIPS_DateTime::now()->timestamp(),
			),
			array( 'id' => absint( $id ) ),
			array( '%d', '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Delete a mapping by ID.
	 *
	 * @param int $id Row ID.
	 * @return int|false
	 */
	public function delete( $id ) {
		return $this->wpdb->delete(
			$this->table,
			array( 'id' => absint( $id ) ),
			array( '%d' )
		);
	}

	/**
	 * Delete all mappings.
	 *
	 * @return int|false
	 */
	public function delete_all() {
		return $this->wpdb->query( "DELETE FROM {$this->table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get all enabled global fallback mappings.
	 *
	 * @return object[]
	 */
	public function get_enabled_fallbacks() {
		return $this->wpdb->get_results(
			"SELECT * FROM {$this->table} WHERE enabled = 1 AND is_global_fallback = 1 ORDER BY id ASC" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * Find enabled affiliate offers matching topic keywords, category, or tags.
	 *
	 * @param string          $topic        Post topic or title string.
	 * @param int|string|null $category_id  Category ID or comma-separated category IDs.
	 * @param array           $tags         Array of tag names or comma-separated tags.
	 * @return object[] Array of matched affiliate offer rows.
	 */
	public function find_matching_offers( $topic = '', $category_id = null, array $tags = array() ) {
		$all_enabled = $this->get_all( true );
		if ( empty( $all_enabled ) ) {
			return array();
		}

		if ( is_array( $topic ) ) {
			$tags  = array_merge( $tags, $topic );
			$topic = '';
		}

		$normalized_tags = array_map( 'strtolower', array_filter( array_map( 'trim', $tags ) ) );
		$topic_lower     = strtolower( (string) $topic );
		$cat_list        = array();

		if ( ! empty( $category_id ) ) {
			$cat_list = array_map( 'trim', explode( ',', (string) $category_id ) );
		}

		$matches = array();

		foreach ( $all_enabled as $mapping ) {
			$is_match = false;

			// 1. Tag matching.
			if ( ! empty( $mapping->tag ) && ! empty( $normalized_tags ) ) {
				if ( in_array( strtolower( trim( $mapping->tag ) ), $normalized_tags, true ) ) {
					$is_match = true;
				}
			}

			// 2. Keyword matching against topic / title.
			if ( ! $is_match && ! empty( $mapping->keywords ) && ! empty( $topic_lower ) ) {
				$keywords = array_filter( array_map( 'trim', explode( ',', strtolower( $mapping->keywords ) ) ) );
				foreach ( $keywords as $kw ) {
					if ( '' !== $kw && strpos( $topic_lower, $kw ) !== false ) {
						$is_match = true;
						break;
					}
				}
			}

			// 3. Category matching.
			if ( ! $is_match && ! empty( $mapping->category_ids ) && ! empty( $cat_list ) ) {
				$decoded = json_decode( (string) $mapping->category_ids, true );
				if ( is_array( $decoded ) ) {
					$mapping_cats = array_map( 'strval', $decoded );
				} else {
					$mapping_cats = array_filter( array_map( 'trim', explode( ',', (string) $mapping->category_ids ) ) );
				}
				$string_cat_list = array_map( 'strval', $cat_list );
				if ( ! empty( array_intersect( $mapping_cats, $string_cat_list ) ) ) {
					$is_match = true;
				}
			}

			if ( $is_match ) {
				$matches[] = $mapping;
			}
		}

		return $matches;
	}

	/**
	 * Count posts that contain a specific URL.
	 *
	 * Checks both aips_link_index (if populated) and post_content.
	 *
	 * @param string $url Target affiliate URL.
	 * @return int Number of matching posts.
	 */
	public function count_posts_with_url( $url ) {
		$url = trim( (string) $url );
		if ( empty( $url ) ) {
			return 0;
		}

		$link_index_table = $this->wpdb->prefix . 'aips_link_index';
		$count            = 0;

		if ( $this->wpdb->get_var( $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $link_index_table ) ) === $link_index_table ) {
			$count = (int) $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT COUNT(DISTINCT source_post_id) FROM {$link_index_table} WHERE target_url = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$url
				)
			);
		}

		if ( $count === 0 ) {
			$like  = '%' . $this->wpdb->esc_like( $url ) . '%';
			$count = (int) $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT COUNT(ID) FROM {$this->wpdb->posts} WHERE post_status IN ('publish', 'draft', 'future') AND post_content LIKE %s",
					$like
				)
			);
		}

		return $count;
	}

	/**
	 * Get posts that contain a specific URL.
	 *
	 * @param string $url   Target affiliate URL.
	 * @param int    $limit Maximum posts to return.
	 * @param int    $offset Pagination offset.
	 * @return object[] Array of post objects with ID, post_title, post_status, post_date.
	 */
	public function get_posts_with_url( $url, $limit = 50, $offset = 0 ) {
		$url = trim( (string) $url );
		if ( empty( $url ) ) {
			return array();
		}

		$link_index_table = $this->wpdb->prefix . 'aips_link_index';

		if ( $this->wpdb->get_var( $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $link_index_table ) ) === $link_index_table ) {
			$posts = $this->wpdb->get_results(
				$this->wpdb->prepare(
					"SELECT p.ID, p.post_title, p.post_status, p.post_date
					 FROM {$this->wpdb->posts} p
					 INNER JOIN {$link_index_table} li ON p.ID = li.source_post_id
					 WHERE li.target_url = %s
					 GROUP BY p.ID
					 ORDER BY p.post_date DESC
					 LIMIT %d OFFSET %d",
					$url,
					absint( $limit ),
					absint( $offset )
				)
			);

			if ( ! empty( $posts ) ) {
				return $posts;
			}
		}

		$like = '%' . $this->wpdb->esc_like( $url ) . '%';
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT ID, post_title, post_status, post_date
				 FROM {$this->wpdb->posts}
				 WHERE post_status IN ('publish', 'draft', 'future') AND post_content LIKE %s
				 ORDER BY post_date DESC
				 LIMIT %d OFFSET %d",
				$like,
				absint( $limit ),
				absint( $offset )
			)
		);
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
}
