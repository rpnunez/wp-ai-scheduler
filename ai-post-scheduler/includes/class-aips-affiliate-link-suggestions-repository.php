<?php
/**
 * Affiliate Link Suggestions Repository
 *
 * Handles persistence for retroactive affiliate link suggestions.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.20
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Affiliate_Link_Suggestions_Repository {

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * @var string
	 */
	private $table;

	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'aips_affiliate_link_suggestions';
	}

	/**
	 * Get a single suggestion by ID.
	 *
	 * @param int $id Row ID.
	 * @return object|null Row or null.
	 */
	public function get_by_id( $id ) {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT s.*, a.label AS offer_label, a.affiliate_url, p.post_title
				 FROM {$this->table} s
				 LEFT JOIN {$this->wpdb->prefix}aips_affiliate_links a ON s.affiliate_link_id = a.id
				 LEFT JOIN {$this->wpdb->posts} p ON s.post_id = p.ID
				 WHERE s.id = %d",
				absint( $id )
			)
		);
	}

	/**
	 * Get paginated suggestions with offer and post details.
	 *
	 * @param int    $per_page Number of items per page.
	 * @param int    $page     Page number (1-based).
	 * @param string $status   Filter by status ('pending', 'applied', 'rejected', or empty for all).
	 * @param int    $offer_id Optional filter by affiliate link offer ID.
	 * @return object[] Array of suggestion objects.
	 */
	public function get_paginated( $per_page = 20, $page = 1, $status = 'pending', $offer_id = 0 ) {
		$offset = max( 0, ( absint( $page ) - 1 ) * absint( $per_page ) );
		$where  = array( '1=1' );
		$args   = array();

		if ( ! empty( $status ) && 'all' !== $status ) {
			$where[] = 's.status = %s';
			$args[]  = sanitize_key( $status );
		}

		if ( ! empty( $offer_id ) ) {
			$where[] = 's.affiliate_link_id = %d';
			$args[]  = absint( $offer_id );
		}

		$where_clause = implode( ' AND ', $where );
		$query        = "SELECT s.*, a.label AS offer_label, a.affiliate_url, p.post_title, p.post_date
		                 FROM {$this->table} s
		                 LEFT JOIN {$this->wpdb->prefix}aips_affiliate_links a ON s.affiliate_link_id = a.id
		                 LEFT JOIN {$this->wpdb->posts} p ON s.post_id = p.ID
		                 WHERE {$where_clause}
		                 ORDER BY s.id DESC
		                 LIMIT %d OFFSET %d";
		$args[]       = absint( $per_page );
		$args[]       = absint( $offset );

		return $this->wpdb->get_results(
			$this->wpdb->prepare( $query, ...$args ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * Count total suggestions for given filters.
	 *
	 * @param string $status   Status filter.
	 * @param int    $offer_id Offer ID filter.
	 * @return int Total rows.
	 */
	public function count( $status = 'pending', $offer_id = 0 ) {
		$where = array( '1=1' );
		$args  = array();

		if ( ! empty( $status ) && 'all' !== $status ) {
			$where[] = 'status = %s';
			$args[]  = sanitize_key( $status );
		}

		if ( ! empty( $offer_id ) ) {
			$where[] = 'affiliate_link_id = %d';
			$args[]  = absint( $offer_id );
		}

		$where_clause = implode( ' AND ', $where );
		$query        = "SELECT COUNT(id) FROM {$this->table} WHERE {$where_clause}";

		if ( ! empty( $args ) ) {
			return (int) $this->wpdb->get_var( $this->wpdb->prepare( $query, ...$args ) );
		}

		return (int) $this->wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Insert a new suggestion.
	 *
	 * @param array $data Suggestion data.
	 * @return int|false Inserted ID or false.
	 */
	public function insert( array $data ) {
		$now = AIPS_DateTime::now()->timestamp();
		$res = $this->wpdb->insert(
			$this->table,
			array(
				'post_id'           => absint( $data['post_id'] ?? 0 ),
				'affiliate_link_id' => absint( $data['affiliate_link_id'] ?? 0 ),
				'anchor_text'       => sanitize_text_field( $data['anchor_text'] ?? '' ),
				'status'            => sanitize_key( $data['status'] ?? 'pending' ),
				'match_type'        => sanitize_key( $data['match_type'] ?? 'in_content' ),
				'before_snippet'    => wp_kses_post( $data['before_snippet'] ?? '' ),
				'after_snippet'     => wp_kses_post( $data['after_snippet'] ?? '' ),
				'applied_at'        => 0,
				'created_at'        => $now,
				'updated_at'        => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d' )
		);

		return $res ? $this->wpdb->insert_id : false;
	}

	/**
	 * Check if a suggestion already exists for this post and affiliate link.
	 *
	 * @param int $post_id           Post ID.
	 * @param int $affiliate_link_id Affiliate link ID.
	 * @return bool True if already exists.
	 */
	public function exists_for_post_and_link( $post_id, $affiliate_link_id ) {
		$count = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(id) FROM {$this->table} WHERE post_id = %d AND affiliate_link_id = %d",
				absint( $post_id ),
				absint( $affiliate_link_id )
			)
		);
		return (int) $count > 0;
	}

	/**
	 * Update suggestion status.
	 *
	 * @param int    $id     Row ID.
	 * @param string $status 'applied', 'rejected', or 'pending'.
	 * @return int|false Rows affected or false.
	 */
	public function update_status( $id, $status ) {
		$now  = AIPS_DateTime::now()->timestamp();
		$data = array(
			'status'     => sanitize_key( $status ),
			'updated_at' => $now,
		);
		$formats = array( '%s', '%d' );

		if ( 'applied' === $status ) {
			$data['applied_at'] = $now;
			$formats[]          = '%d';
		}

		return $this->wpdb->update(
			$this->table,
			$data,
			array( 'id' => absint( $id ) ),
			$formats,
			array( '%d' )
		);
	}

	/**
	 * Delete a suggestion by ID.
	 *
	 * @param int $id Row ID.
	 * @return int|false Rows affected or false.
	 */
	public function delete( $id ) {
		return $this->wpdb->delete(
			$this->table,
			array( 'id' => absint( $id ) ),
			array( '%d' )
		);
	}
}
