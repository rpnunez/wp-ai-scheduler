<?php
/**
 * Referral Programs Repository
 *
 * Handles database persistence, querying, status toggling, and multi-criteria
 * contextual matching for partner referral programs and affiliate network deals.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Referral_Programs_Repository {

	/**
	 * WordPress database instance.
	 *
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Table name including WordPress prefix.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Supported network provider identifiers.
	 *
	 * @var string[]
	 */
	const SUPPORTED_NETWORKS = array(
		'amazon',
		'shareasale',
		'cj',
		'impact',
		'awin',
		'rakuten',
		'direct',
	);

	/**
	 * Valid program status options.
	 *
	 * @var string[]
	 */
	const VALID_STATUSES = array( 'active', 'paused' );

	/**
	 * Constructor.
	 *
	 * @param wpdb|null $db Optional custom database instance.
	 */
	public function __construct( ?wpdb $db = null ) {
		global $wpdb;
		$this->wpdb  = $db ?: $wpdb;
		$this->table = $this->wpdb ? $this->wpdb->prefix . 'aips_referral_programs' : 'wp_aips_referral_programs';
	}

	/**
	 * Retrieve a single referral program by its database ID.
	 *
	 * @param int $id Program ID.
	 * @return array|null Program associative array or null if not found.
	 */
	public function get_by_id( int $id ): ?array {
		if ( $id <= 0 ) {
			return null;
		}

		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d LIMIT 1",
				absint( $id )
			),
			ARRAY_A
		);

		return $row ? $this->normalize_row( $row ) : null;
	}

	/**
	 * Retrieve a single referral program by its cloaked redirect slug.
	 *
	 * Performs primary lookup on the unique slug column. If no exact match is found,
	 * attempts a fallback match against the sanitized program name.
	 *
	 * @param string $slug Cloaked slug string.
	 * @return array|null Program associative array or null if not found.
	 */
	public function get_by_slug( string $slug ): ?array {
		$slug = sanitize_title( $slug );
		if ( empty( $slug ) ) {
			return null;
		}

		// 1. Primary lookup by slug
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE slug = %s LIMIT 1",
				$slug
			),
			ARRAY_A
		);

		// 2. Fallback lookup by sanitized name if slug direct match misses
		if ( ! $row ) {
			$like_name = str_replace( '-', ' ', $slug );
			$row       = $this->wpdb->get_row(
				$this->wpdb->prepare(
					"SELECT * FROM {$this->table} WHERE LOWER(name) = %s LIMIT 1",
					strtolower( $like_name )
				),
				ARRAY_A
			);
		}

		return $row ? $this->normalize_row( $row ) : null;
	}

	/**
	 * Retrieve all referral programs matching the given filter arguments.
	 *
	 * Supports filtering by status ('active', 'paused', 'all'), network_provider,
	 * text search, expiration inclusion, and sorting/pagination.
	 *
	 * @param array $args Query arguments:
	 *                    - status (string): 'active', 'paused', or 'all'.
	 *                    - network_provider (string): Filter by network.
	 *                    - search (string): Search name, promo code, keywords, or offer.
	 *                    - include_expired (bool): Whether to include expired programs. Default true.
	 *                    - orderby (string): Column to sort by. Default 'id'.
	 *                    - order (string): 'ASC' or 'DESC'. Default 'DESC'.
	 *                    - limit (int): Max rows to return.
	 *                    - offset (int): Row offset for pagination.
	 * @return array List of matching program associative arrays.
	 */
	public function get_all( array $args = array() ): array {
		$where        = array( '1=1' );
		$query_params = array();

		// Filter: Status
		if ( ! empty( $args['status'] ) && 'all' !== $args['status'] ) {
			$status = sanitize_text_field( $args['status'] );
			if ( in_array( $status, self::VALID_STATUSES, true ) ) {
				$where[]        = 'status = %s';
				$query_params[] = $status;
			}
		}

		// Filter: Network Provider
		if ( ! empty( $args['network_provider'] ) && 'all' !== $args['network_provider'] ) {
			$network_provider = sanitize_key( $args['network_provider'] );
			$where[]          = 'network_provider = %s';
			$query_params[]   = $network_provider;
		}

		// Filter: Search keyword across text fields
		if ( ! empty( $args['search'] ) ) {
			$search_like    = '%' . $this->wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[]        = '(name LIKE %s OR promo_code LIKE %s OR slug LIKE %s OR keywords LIKE %s OR discount_offer LIKE %s)';
			$query_params[] = $search_like;
			$query_params[] = $search_like;
			$query_params[] = $search_like;
			$query_params[] = $search_like;
			$query_params[] = $search_like;
		}

		// Filter: Expiration check (when include_expired is explicitly false)
		if ( isset( $args['include_expired'] ) && false === $args['include_expired'] ) {
			$today          = current_time( 'Y-m-d' );
			$where[]        = '(expiry_date IS NULL OR expiry_date = \'0000-00-00\' OR expiry_date >= %s)';
			$query_params[] = $today;
		}

		// Sorting validation
		$allowed_orderby = array( 'id', 'name', 'network_provider', 'status', 'created_at', 'updated_at', 'expiry_date' );
		$orderby         = ( ! empty( $args['orderby'] ) && in_array( $args['orderby'], $allowed_orderby, true ) )
			? $args['orderby']
			: 'id';

		$order = ( ! empty( $args['order'] ) && 'ASC' === strtoupper( $args['order'] ) ) ? 'ASC' : 'DESC';

		$sql = "SELECT * FROM {$this->table} WHERE " . implode( ' AND ', $where ) . " ORDER BY {$orderby} {$order}";

		// Pagination: limit and offset
		if ( isset( $args['limit'] ) && absint( $args['limit'] ) > 0 ) {
			$limit          = absint( $args['limit'] );
			$offset         = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;
			$sql           .= ' LIMIT %d OFFSET %d';
			$query_params[] = $limit;
			$query_params[] = $offset;
		}

		if ( ! empty( $query_params ) ) {
			$results = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $query_params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$results = $this->wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		if ( ! is_array( $results ) ) {
			return array();
		}

		return array_map( array( $this, 'normalize_row' ), $results );
	}

	/**
	 * Count total programs matching query filters.
	 *
	 * @param array $args Same filter arguments as get_all().
	 * @return int Total matching records.
	 */
	public function get_count( array $args = array() ): int {
		$where        = array( '1=1' );
		$query_params = array();

		if ( ! empty( $args['status'] ) && 'all' !== $args['status'] ) {
			$status = sanitize_text_field( $args['status'] );
			if ( in_array( $status, self::VALID_STATUSES, true ) ) {
				$where[]        = 'status = %s';
				$query_params[] = $status;
			}
		}

		if ( ! empty( $args['network_provider'] ) && 'all' !== $args['network_provider'] ) {
			$where[]        = 'network_provider = %s';
			$query_params[] = sanitize_key( $args['network_provider'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$search_like    = '%' . $this->wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[]        = '(name LIKE %s OR promo_code LIKE %s OR slug LIKE %s OR keywords LIKE %s OR discount_offer LIKE %s)';
			$query_params[] = $search_like;
			$query_params[] = $search_like;
			$query_params[] = $search_like;
			$query_params[] = $search_like;
			$query_params[] = $search_like;
		}

		if ( isset( $args['include_expired'] ) && false === $args['include_expired'] ) {
			$today          = current_time( 'Y-m-d' );
			$where[]        = '(expiry_date IS NULL OR expiry_date = \'0000-00-00\' OR expiry_date >= %s)';
			$query_params[] = $today;
		}

		$sql = "SELECT COUNT(*) FROM {$this->table} WHERE " . implode( ' AND ', $where );

		if ( ! empty( $query_params ) ) {
			return (int) $this->wpdb->get_var( $this->wpdb->prepare( $sql, $query_params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return (int) $this->wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Save (insert or update) a partner referral program.
	 *
	 * Validates required fields, enforces unique redirect slugs, normalizes category IDs
	 * and keywords, and stamps UTC timestamps via AIPS_DateTime.
	 *
	 * @param array $data Program input data:
	 *                    - id (int, optional): Existing ID to update.
	 *                    - name (string, required): Program/partner name.
	 *                    - referral_url (string, required): Destination target URL.
	 *                    - slug (string, optional): Cloaked redirect slug.
	 *                    - network_provider (string, optional): Provider identifier. Default 'direct'.
	 *                    - promo_code | coupon_code (string, optional): Coupon/discount code.
	 *                    - discount_offer | discount_description (string, optional): Offer copy.
	 *                    - commission_notes | commission_rate (string, optional): Rate or notes.
	 *                    - category_ids (array|string, optional): Matched category IDs.
	 *                    - keywords (array|string, optional): Matched keywords.
	 *                    - expiry_date (string, optional): Expiration date (YYYY-MM-DD).
	 *                    - status (string, optional): 'active' or 'paused'. Default 'active'.
	 * @return int|false Inserted/updated row ID or false on failure.
	 */
	public function save( array $data ) {
		$id   = ! empty( $data['id'] ) ? absint( $data['id'] ) : 0;
		$name = sanitize_text_field( $data['name'] ?? '' );
		if ( empty( $name ) ) {
			return false;
		}

		$referral_url = esc_url_raw( $data['referral_url'] ?? '' );
		if ( empty( $referral_url ) ) {
			return false;
		}

		// Slug resolution and uniqueness enforcement
		$raw_slug = ! empty( $data['slug'] ) ? sanitize_title( $data['slug'] ) : sanitize_title( $name );
		if ( empty( $raw_slug ) ) {
			$raw_slug = 'referral-' . time();
		}
		$slug = $this->generate_unique_slug( $raw_slug, $id );

		// Network provider sanitization
		$network_provider = sanitize_key( $data['network_provider'] ?? 'direct' );
		if ( empty( $network_provider ) ) {
			$network_provider = 'direct';
		}

		// Promo / coupon code
		$promo_code = sanitize_text_field( $data['promo_code'] ?? ( $data['coupon_code'] ?? '' ) );

		// Discount offer / description
		$discount_offer = sanitize_text_field( $data['discount_offer'] ?? ( $data['discount_description'] ?? '' ) );

		// Commission notes / rate
		$commission_notes = sanitize_text_field( $data['commission_notes'] ?? ( $data['commission_rate'] ?? '' ) );

		// Category IDs normalization
		$category_ids = '';
		if ( ! empty( $data['category_ids'] ) ) {
			if ( is_array( $data['category_ids'] ) ) {
				$clean_cats   = array_filter( array_map( 'absint', $data['category_ids'] ) );
				$category_ids = implode( ',', $clean_cats );
			} else {
				$clean_cats   = array_filter( array_map( 'absint', explode( ',', sanitize_text_field( $data['category_ids'] ) ) ) );
				$category_ids = implode( ',', $clean_cats );
			}
		}

		// Keywords normalization
		$keywords = '';
		if ( ! empty( $data['keywords'] ) ) {
			if ( is_array( $data['keywords'] ) ) {
				$clean_kw = array_filter( array_map( 'sanitize_text_field', array_map( 'trim', $data['keywords'] ) ) );
				$keywords = implode( ', ', $clean_kw );
			} else {
				$clean_kw = array_filter( array_map( 'sanitize_text_field', array_map( 'trim', explode( ',', sanitize_text_field( $data['keywords'] ) ) ) ) );
				$keywords = implode( ', ', $clean_kw );
			}
		}

		// Expiry date validation (YYYY-MM-DD)
		$expiry_date = null;
		if ( ! empty( $data['expiry_date'] ) ) {
			$exp = sanitize_text_field( $data['expiry_date'] );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $exp ) ) {
				$expiry_date = $exp;
			}
		}

		// Status validation
		$status = in_array( $data['status'] ?? '', self::VALID_STATUSES, true ) ? $data['status'] : 'active';

		$now = AIPS_DateTime::now()->timestamp();

		$record = array(
			'name'             => $name,
			'slug'             => $slug,
			'network_provider' => $network_provider,
			'referral_url'     => $referral_url,
			'promo_code'       => $promo_code,
			'discount_offer'   => $discount_offer,
			'commission_notes' => $commission_notes,
			'category_ids'     => $category_ids,
			'keywords'         => $keywords,
			'expiry_date'      => $expiry_date,
			'status'           => $status,
			'updated_at'       => $now,
		);

		$formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' );

		if ( $id > 0 ) {
			$updated = $this->wpdb->update(
				$this->table,
				$record,
				array( 'id' => $id ),
				$formats,
				array( '%d' )
			);
			return false !== $updated ? $id : false;
		}

		$record['created_at'] = $now;
		$formats[]            = '%d';

		$inserted = $this->wpdb->insert( $this->table, $record, $formats );
		return false !== $inserted ? (int) $this->wpdb->insert_id : false;
	}

	/**
	 * Delete a referral program by ID.
	 *
	 * @param int $id Program row ID.
	 * @return bool True on successful deletion, false otherwise.
	 */
	public function delete( int $id ): bool {
		if ( $id <= 0 ) {
			return false;
		}

		$result = $this->wpdb->delete(
			$this->table,
			array( 'id' => absint( $id ) ),
			array( '%d' )
		);

		return false !== $result && $result > 0;
	}

	/**
	 * Toggle program status between 'active' and 'paused'.
	 *
	 * @param int $id Program row ID.
	 * @return bool True on successful toggle, false otherwise.
	 */
	public function toggle_status( int $id ): bool {
		$program = $this->get_by_id( $id );
		if ( ! $program ) {
			return false;
		}

		$new_status = ( 'active' === $program['status'] ) ? 'paused' : 'active';
		$result     = $this->wpdb->update(
			$this->table,
			array(
				'status'     => $new_status,
				'updated_at' => AIPS_DateTime::now()->timestamp(),
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%d' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Match active, unexpired referral programs against post categories, tags, and content.
	 *
	 * Computes a weighted relevance match score:
	 * - Post category matches program category: +10 points
	 * - Post tag matches program keyword: +5 points
	 * - Post body content matches program keyword (word boundary): +2 points per keyword
	 *
	 * Returns all matching programs ordered by match score descending.
	 *
	 * @param array  $categories Array of WordPress category IDs (ints) or category names.
	 * @param array  $tags       Array of WordPress post tag names (strings).
	 * @param string $content    Raw or HTML post body content.
	 * @return array Array of matched program arrays with 'match_score' and 'match_reasons' attached.
	 */
	public function match_programs( array $categories, array $tags, string $content ): array {
		$today = current_time( 'Y-m-d' );

		// Query only active, unexpired programs
		$sql = "SELECT * FROM {$this->table} 
				WHERE status = 'active' 
				AND (expiry_date IS NULL OR expiry_date = '0000-00-00' OR expiry_date >= %s) 
				ORDER BY id DESC";

		$programs = $this->wpdb->get_results(
			$this->wpdb->prepare( $sql, $today ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		if ( empty( $programs ) || ! is_array( $programs ) ) {
			return array();
		}

		// Normalize inputs
		$target_category_ids = array_filter( array_map( 'absint', (array) $categories ) );
		$target_tags         = array_filter( array_map( 'strtolower', array_map( 'trim', (array) $tags ) ) );
		$clean_content       = strtolower( wp_strip_all_tags( $content ) );

		$matched_programs = array();

		foreach ( $programs as $raw_prog ) {
			$prog    = $this->normalize_row( $raw_prog );
			$score   = 0;
			$reasons = array();

			// 1. Category Matching
			if ( ! empty( $target_category_ids ) && ! empty( $prog['parsed_categories'] ) ) {
				$cat_intersection = array_intersect( $prog['parsed_categories'], $target_category_ids );
				if ( ! empty( $cat_intersection ) ) {
					$score                  += count( $cat_intersection ) * 10;
					$reasons['categories']   = array_values( $cat_intersection );
				}
			}

			// 2. Tag Matching against Program Keywords
			if ( ! empty( $target_tags ) && ! empty( $prog['parsed_keywords'] ) ) {
				$prog_lower_kws   = array_map( 'strtolower', $prog['parsed_keywords'] );
				$tag_intersection = array_intersect( $prog_lower_kws, $target_tags );
				if ( ! empty( $tag_intersection ) ) {
					$score            += count( $tag_intersection ) * 5;
					$reasons['tags']   = array_values( $tag_intersection );
				}
			}

			// 3. Content Body Keyword Matching (Word boundary checking)
			if ( ! empty( $clean_content ) && ! empty( $prog['parsed_keywords'] ) ) {
				$matched_content_kws = array();
				foreach ( $prog['parsed_keywords'] as $kw ) {
					$kw_lower = strtolower( trim( $kw ) );
					if ( empty( $kw_lower ) ) {
						continue;
					}

					// Use regex word boundary matching to avoid partial word collisions
					if ( preg_match( '/\b' . preg_quote( $kw_lower, '/' ) . '\b/iu', $clean_content ) ) {
						$matched_content_kws[] = $kw_lower;
						$score                += 2;
					}
				}

				if ( ! empty( $matched_content_kws ) ) {
					$reasons['keywords'] = $matched_content_kws;
				}
			}

			// If any match criterion was met, retain program in candidate list
			if ( $score > 0 ) {
				$prog['match_score']   = $score;
				$prog['match_reasons'] = $reasons;
				$matched_programs[]    = $prog;
			}
		}

		if ( empty( $matched_programs ) ) {
			return array();
		}

		// Sort by match score descending; break ties by newest ID descending
		usort(
			$matched_programs,
			function( array $a, array $b ): int {
				if ( $a['match_score'] === $b['match_score'] ) {
					return ( (int) $b['id'] ) <=> ( (int) $a['id'] );
				}
				return $b['match_score'] <=> $a['match_score'];
			}
		);

		return $matched_programs;
	}

	/**
	 * Check whether a redirect slug is already registered by another record.
	 *
	 * @param string $slug       Slug candidate to check.
	 * @param int    $exclude_id Optional row ID to exclude from collision check.
	 * @return bool True if slug exists, false otherwise.
	 */
	public function is_slug_taken( string $slug, int $exclude_id = 0 ): bool {
		$sql = "SELECT COUNT(*) FROM {$this->table} WHERE slug = %s";
		if ( $exclude_id > 0 ) {
			$sql .= $this->wpdb->prepare( ' AND id != %d', absint( $exclude_id ) );
		}

		$count = (int) $this->wpdb->get_var(
			$this->wpdb->prepare( $sql, $slug ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		return $count > 0;
	}

	/**
	 * Generate a unique slug by appending incremental numeric suffixes if collision occurs.
	 *
	 * @param string $slug_candidate Base slug proposal.
	 * @param int    $exclude_id      Row ID being edited (if updating).
	 * @return string Guaranteed unique slug.
	 */
	public function generate_unique_slug( string $slug_candidate, int $exclude_id = 0 ): string {
		$base_slug = sanitize_title( $slug_candidate );
		if ( empty( $base_slug ) ) {
			$base_slug = 'partner-' . time();
		}

		$slug   = $base_slug;
		$suffix = 2;

		while ( $this->is_slug_taken( $slug, $exclude_id ) ) {
			$slug = $base_slug . '-' . $suffix;
			$suffix++;
		}

		return $slug;
	}

	/**
	 * Normalize a raw database row: cast scalar types, provide aliases, and pre-parse arrays.
	 *
	 * @param array $row Raw database row.
	 * @return array Normalized associative array.
	 */
	private function normalize_row( array $row ): array {
		$row['id']         = (int) $row['id'];
		$row['created_at'] = (int) $row['created_at'];
		$row['updated_at'] = (int) $row['updated_at'];

		// Field aliasing
		$row['coupon_code']          = $row['promo_code'] ?? '';
		$row['discount_description'] = $row['discount_offer'] ?? '';
		$row['commission_rate']      = $row['commission_notes'] ?? '';

		// Pre-parse category IDs into array of ints
		$row['parsed_categories'] = ! empty( $row['category_ids'] )
			? array_filter( array_map( 'absint', explode( ',', (string) $row['category_ids'] ) ) )
			: array();

		// Pre-parse keywords into array of trimmed strings
		$row['parsed_keywords'] = ! empty( $row['keywords'] )
			? array_filter( array_map( 'trim', explode( ',', (string) $row['keywords'] ) ) )
			: array();

		return $row;
	}

	/**
	 * Delete all records from the table. Used exclusively for testing and reset procedures.
	 *
	 * @return int|false Number of deleted rows or false on failure.
	 */
	public function delete_all() {
		return $this->wpdb->query( "DELETE FROM {$this->table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
