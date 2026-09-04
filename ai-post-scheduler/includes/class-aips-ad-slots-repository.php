<?php
/**
 * Ad Slots Repository
 *
 * Handles database persistence and caching for ad slots.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Ad_Slots_Repository {

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * @var string
	 */
	private $table;

	/**
	 * Valid ad positions.
	 *
	 * @var string[]
	 */
	const VALID_POSITIONS = array( 'after_paragraph', 'mid_content', 'end_of_post', 'custom_shortcode' );

	/**
	 * Valid slot types.
	 *
	 * @var string[]
	 */
	const VALID_TYPES = array( 'custom_html', 'adsense', 'image_banner', 'sponsor_card' );

	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'aips_ad_slots';
	}

	/**
	 * Get a single ad slot by ID.
	 *
	 * @param int $id Slot ID.
	 * @return object|null Slot row or null if not found.
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
	 * Get all ad slots ordered by priority and name.
	 *
	 * @param bool $active_only Whether to return active slots only.
	 * @return object[] Array of slot rows.
	 */
	public function get_all( $active_only = false ) {
		$where = $active_only ? "WHERE status = 'active'" : '';
		return $this->wpdb->get_results(
			"SELECT * FROM {$this->table} {$where} ORDER BY priority ASC, name ASC"
		);
	}

	/**
	 * Get active ad slots configured for automatic runtime placement.
	 *
	 * Excludes 'custom_shortcode' positions which require manual block/shortcode insertion.
	 *
	 * @return object[]
	 */
	public function get_active_runtime_slots() {
		return $this->wpdb->get_results(
			"SELECT * FROM {$this->table} WHERE status = 'active' AND position != 'custom_shortcode' ORDER BY priority ASC, id ASC"
		);
	}

	/**
	 * Save or update an ad slot.
	 *
	 * @param array $data Slot data.
	 * @return int|false Inserted/updated slot ID or false on failure.
	 */
	public function save( array $data ) {
		$id  = ! empty( $data['id'] ) ? absint( $data['id'] ) : 0;
		$now = time();

		$record = array(
			'name'             => sanitize_text_field( $data['name'] ?? '' ),
			'slot_type'        => in_array( $data['slot_type'] ?? '', self::VALID_TYPES, true ) ? $data['slot_type'] : 'custom_html',
			'code'             => $data['code'] ?? '',
			'position'         => in_array( $data['position'] ?? '', self::VALID_POSITIONS, true ) ? $data['position'] : 'after_paragraph',
			'paragraph_offset' => max( 1, absint( $data['paragraph_offset'] ?? 2 ) ),
			'min_word_count'   => absint( $data['min_word_count'] ?? 300 ),
			'device_targeting' => in_array( $data['device_targeting'] ?? '', array( 'all', 'desktop', 'mobile' ), true ) ? $data['device_targeting'] : 'all',
			'status'           => in_array( $data['status'] ?? '', array( 'active', 'inactive' ), true ) ? $data['status'] : 'active',
			'priority'         => absint( $data['priority'] ?? 10 ),
			'css_classes'      => sanitize_text_field( $data['css_classes'] ?? '' ),
			'updated_at'       => $now,
		);

		$formats = array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%d' );

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
	 * Toggle status between active and inactive.
	 *
	 * @param int $id Slot ID.
	 * @return bool
	 */
	public function toggle_status( $id ) {
		$slot = $this->get_by_id( $id );
		if ( ! $slot ) {
			return false;
		}

		$new_status = ( 'active' === $slot->status ) ? 'inactive' : 'active';
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
	 * Delete an ad slot.
	 *
	 * @param int $id Slot ID.
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
