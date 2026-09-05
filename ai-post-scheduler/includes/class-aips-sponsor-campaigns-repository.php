<?php
/**
 * Sponsor Campaigns Repository
 *
 * Handles database persistence for direct sponsor campaigns, FTC disclosures, and brand partnerships.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Sponsor_Campaigns_Repository {

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
		$this->table = $wpdb->prefix . 'aips_sponsor_campaigns';
	}

	/**
	 * Get a single sponsor campaign by ID.
	 *
	 * @param int $id Campaign ID.
	 * @return object|null
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
	 * Check if a specific campaign is currently active (status active and within start/end dates).
	 *
	 * @param object|null $campaign
	 * @return bool
	 */
	public function is_campaign_active( $campaign ) {
		if ( ! $campaign || empty( $campaign->status ) || 'active' !== $campaign->status ) {
			return false;
		}

		$today = current_time( 'Y-m-d' );
		if ( ! empty( $campaign->start_date ) && $campaign->start_date > $today ) {
			return false;
		}
		if ( ! empty( $campaign->end_date ) && $campaign->end_date < $today ) {
			return false;
		}

		return true;
	}

	/**
	 * Get an active campaign by ID (must be active and within start/end dates).
	 *
	 * @param int $id Campaign ID.
	 * @return object|null
	 */
	public function get_active_by_id( $id ) {
		$campaign = $this->get_by_id( $id );
		return $this->is_campaign_active( $campaign ) ? $campaign : null;
	}

	/**
	 * Get all campaigns.
	 *
	 * @param bool $active_only
	 * @return object[]
	 */
	public function get_all( $active_only = false ) {
		$where = $active_only ? "WHERE status = 'active'" : '';
		return $this->wpdb->get_results(
			"SELECT * FROM {$this->table} {$where} ORDER BY id DESC"
		);
	}

	/**
	 * Get currently active campaigns taking start and end dates into account.
	 *
	 * @return object[]
	 */
	public function get_active_campaigns() {
		$today = current_time( 'Y-m-d' );
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} 
				WHERE status = 'active' 
				AND (start_date IS NULL OR start_date = '' OR start_date <= %s) 
				AND (end_date IS NULL OR end_date = '' OR end_date >= %s) 
				ORDER BY id DESC",
				$today,
				$today
			)
		);
	}

	/**
	 * Find the best matching active campaign for a set of keywords or category IDs.
	 *
	 * @param string[] $keywords Array of keyword strings.
	 * @param int[]    $category_ids Array of WordPress category IDs.
	 * @return object|null Matching campaign or null.
	 */
	public function match_campaign( array $keywords = array(), array $category_ids = array() ) {
		$campaigns = $this->get_active_campaigns();
		if ( empty( $campaigns ) ) {
			return null;
		}

		$normalized_keywords = array_map( 'strtolower', array_map( 'trim', $keywords ) );

		foreach ( $campaigns as $campaign ) {
			// Check category match
			if ( ! empty( $campaign->category_ids ) && ! empty( $category_ids ) ) {
				$camp_cats = array_map( 'absint', array_filter( explode( ',', (string) $campaign->category_ids ) ) );
				if ( ! empty( array_intersect( $camp_cats, $category_ids ) ) ) {
					return $campaign;
				}
			}

			// Check keyword match
			if ( ! empty( $campaign->keywords ) && ! empty( $normalized_keywords ) ) {
				$camp_kw = array_map( 'strtolower', array_map( 'trim', explode( ',', (string) $campaign->keywords ) ) );
				foreach ( $camp_kw as $kw ) {
					if ( ! empty( $kw ) && in_array( $kw, $normalized_keywords, true ) ) {
						return $campaign;
					}
				}
			}
		}

		return null;
	}

	/**
	 * Save or update a sponsor campaign.
	 *
	 * @param array $data Campaign data.
	 * @return int|false
	 */
	public function save( array $data ) {
		$id  = ! empty( $data['id'] ) ? absint( $data['id'] ) : 0;
		$now = time();

		$start_date = ! empty( $data['start_date'] ) ? sanitize_text_field( $data['start_date'] ) : null;
		if ( $start_date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date ) ) {
			$start_date = null;
		}

		$end_date = ! empty( $data['end_date'] ) ? sanitize_text_field( $data['end_date'] ) : null;
		if ( $end_date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_date ) ) {
			$end_date = null;
		}

		$brand_name = ! empty( $data['brand_name'] ) ? $data['brand_name'] : ( $data['name'] ?? '' );
		$record     = array(
			'brand_name'      => sanitize_text_field( $brand_name ),
			'logo_url'        => esc_url_raw( $data['logo_url'] ?? '' ),
			'target_url'      => esc_url_raw( $data['target_url'] ?? '' ),
			'cta_text'        => sanitize_text_field( $data['cta_text'] ?? '' ),
			'disclosure_text' => sanitize_textarea_field( $data['disclosure_text'] ?? '' ),
			'category_ids'    => sanitize_text_field( $data['category_ids'] ?? '' ),
			'keywords'        => sanitize_text_field( $data['keywords'] ?? '' ),
			'start_date'      => $start_date,
			'end_date'        => $end_date,
			'status'          => in_array( $data['status'] ?? '', array( 'active', 'paused', 'completed' ), true ) ? $data['status'] : 'active',
			'updated_at'      => $now,
		);

		$formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' );

		if ( $id > 0 ) {
			$result = $this->wpdb->update(
				$this->table,
				$record,
				array( 'id' => $id ),
				$formats,
				array( '%d' )
			);
			return false !== $result ? $id : false;
		}

		$record['created_at'] = $now;
		$formats[]            = '%d';

		$result = $this->wpdb->insert( $this->table, $record, $formats );
		return false !== $result ? (int) $this->wpdb->insert_id : false;
	}

	/**
	 * Toggle status between active and paused.
	 *
	 * @param int $id Campaign ID.
	 * @return bool
	 */
	public function toggle_status( $id ) {
		$campaign = $this->get_by_id( $id );
		if ( ! $campaign ) {
			return false;
		}

		$new_status = ( 'active' === $campaign->status ) ? 'paused' : 'active';
		$result     = $this->wpdb->update(
			$this->table,
			array(
				'status'     => $new_status,
				'updated_at' => time(),
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%d' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete a campaign.
	 *
	 * @param int $id Campaign ID.
	 * @return bool
	 */
	public function delete( $id ) {
		$result = $this->wpdb->delete(
			$this->table,
			array( 'id' => absint( $id ) ),
			array( '%d' )
		);
		return false !== $result;
	}
}
