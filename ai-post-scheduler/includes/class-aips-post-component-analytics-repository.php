<?php
/**
 * Post Component Analytics Repository
 *
 * @package AI_Post_Scheduler
 * @since 2.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Post_Component_Analytics_Repository {

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * @var string
	 */
	private $table_name;

	public function __construct() {
		global $wpdb;
		$this->wpdb       = $wpdb;
		$this->table_name = $wpdb->prefix . 'aips_post_component_analytics';
	}

	/**
	 * Record an injection event in aggregate analytics.
	 *
	 * @param int  $component_id Component ID.
	 * @param bool $is_regeneration Whether this was a regeneration reinjection.
	 * @return void
	 */
	public function record_injection( $component_id, $is_regeneration = false ) {
		$component_id = absint( $component_id );
		if ( $component_id < 1 ) {
			return;
		}

		$existing = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE component_id = %d LIMIT 1",
				$component_id
			)
		);

		$timestamp = AIPS_DateTime::now()->timestamp();

		if ( $existing ) {
			$this->wpdb->update(
				$this->table_name,
				array(
					'impressions'               => (int) $existing->impressions + 1,
					'injections'                => (int) $existing->injections + 1,
					'regeneration_reinjections' => (int) $existing->regeneration_reinjections + ( $is_regeneration ? 1 : 0 ),
					'last_seen_at'              => $timestamp,
				),
				array( 'component_id' => $component_id ),
				array( '%d', '%d', '%d', '%d' ),
				array( '%d' )
			);
			return;
		}

		$this->wpdb->insert(
			$this->table_name,
			array(
				'component_id'              => $component_id,
				'impressions'               => 1,
				'injections'                => 1,
				'regeneration_reinjections' => $is_regeneration ? 1 : 0,
				'last_seen_at'              => $timestamp,
			),
			array( '%d', '%d', '%d', '%d', '%d' )
		);
	}
}
