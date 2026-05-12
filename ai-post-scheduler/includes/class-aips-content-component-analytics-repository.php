<?php
/**
 * Content Component Analytics Repository
 *
 * @package AI_Post_Scheduler
 * @since 2.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Content_Component_Analytics_Repository {

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * @var string
	 */
	private $table_name;

	/**
	 * @var string
	 */
	private $injections_table;

	public function __construct() {
		global $wpdb;
		$this->wpdb             = $wpdb;
		$this->table_name       = $wpdb->prefix . 'aips_content_component_analytics';
		$this->injections_table = $wpdb->prefix . 'aips_content_component_injections';
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

		$this->upsert_counter(
			$component_id,
			array(
				'impressions'               => 1,
				'injections'                => 1,
				'regeneration_reinjections' => $is_regeneration ? 1 : 0,
				'matched_count'             => 0,
				'skipped_conflict_count'    => 0,
				'skipped_exclusion_count'   => 0,
				'dry_run_matches'           => 0,
				'dry_run_total'             => 0,
			)
		);
	}

	/**
	 * Record a non-injection analytics event.
	 *
	 * @param int    $component_id Component ID.
	 * @param string $event_type Event type.
	 * @return void
	 */
	public function record_event( $component_id, $event_type ) {
		$component_id = absint( $component_id );
		if ( $component_id < 1 ) {
			return;
		}

		$counters = array(
			'impressions'               => 0,
			'injections'                => 0,
			'regeneration_reinjections' => 0,
			'matched_count'             => 0,
			'skipped_conflict_count'    => 0,
			'skipped_exclusion_count'   => 0,
			'dry_run_matches'           => 0,
			'dry_run_total'             => 0,
		);

		switch ( sanitize_key( (string) $event_type ) ) {
			case 'matched':
				$counters['matched_count'] = 1;
				break;
			case 'skipped_conflict':
				$counters['skipped_conflict_count'] = 1;
				break;
			case 'skipped_exclusion':
				$counters['skipped_exclusion_count'] = 1;
				break;
			case 'reinjected_on_regeneration':
				$counters['regeneration_reinjections'] = 1;
				break;
			default:
				return;
		}

		$this->upsert_counter( $component_id, $counters );
	}

	/**
	 * Record dry-run analytics for a component.
	 *
	 * @param int  $component_id Component ID.
	 * @param bool $matched Whether the component matched in the simulation.
	 * @return void
	 */
	public function record_dry_run( $component_id, $matched ) {
		$component_id = absint( $component_id );
		if ( $component_id < 1 ) {
			return;
		}

		$this->upsert_counter(
			$component_id,
			array(
				'impressions'               => 0,
				'injections'                => 0,
				'regeneration_reinjections' => 0,
				'matched_count'             => 0,
				'skipped_conflict_count'    => 0,
				'skipped_exclusion_count'   => 0,
				'dry_run_matches'           => $matched ? 1 : 0,
				'dry_run_total'             => 1,
			)
		);
	}

	/**
	 * Return aggregate usage stats keyed by component ID.
	 *
	 * @return array<int,array<string,int|float|string>>
	 */
	public function get_usage_map() {
		$analytics_rows = $this->wpdb->get_results(
			"SELECT component_id, impressions, injections, regeneration_reinjections, matched_count, skipped_conflict_count, skipped_exclusion_count, dry_run_matches, dry_run_total, last_seen_at
			FROM {$this->table_name}"
		);
		$injection_rows = $this->wpdb->get_results(
			"SELECT component_id, COUNT(DISTINCT post_id) AS unique_posts, MAX(inserted_at) AS last_injected_at
			FROM {$this->injections_table}
			GROUP BY component_id"
		);

		$usage = array();

		foreach ( (array) $analytics_rows as $row ) {
			$dry_run_total  = (int) $row->dry_run_total;
			$dry_run_rate   = $dry_run_total > 0 ? round( ( (int) $row->dry_run_matches / $dry_run_total ) * 100, 2 ) : 0;
			$usage[ (int) $row->component_id ] = array(
				'impressions'               => (int) $row->impressions,
				'injections'                => (int) $row->injections,
				'regeneration_reinjections' => (int) $row->regeneration_reinjections,
				'matched_count'             => (int) $row->matched_count,
				'skipped_conflict_count'    => (int) $row->skipped_conflict_count,
				'skipped_exclusion_count'   => (int) $row->skipped_exclusion_count,
				'dry_run_matches'           => (int) $row->dry_run_matches,
				'dry_run_total'             => $dry_run_total,
				'dry_run_match_rate'        => $dry_run_rate,
				'last_seen_at'              => (int) $row->last_seen_at,
				'unique_posts'              => 0,
				'last_injected_at'          => 0,
			);
		}

		foreach ( (array) $injection_rows as $row ) {
			$component_id = (int) $row->component_id;
			if ( ! isset( $usage[ $component_id ] ) ) {
				$usage[ $component_id ] = $this->get_default_usage_payload();
			}
			$usage[ $component_id ]['unique_posts']     = (int) $row->unique_posts;
			$usage[ $component_id ]['last_injected_at'] = (int) $row->last_injected_at;
		}

		return $usage;
	}

	/**
	 * @param int                $component_id Component ID.
	 * @param array<string,int>  $delta Counter deltas.
	 * @return void
	 */
	private function upsert_counter( $component_id, array $delta ) {
		$existing  = $this->wpdb->get_row(
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
					'impressions'               => (int) $existing->impressions + (int) $delta['impressions'],
					'injections'                => (int) $existing->injections + (int) $delta['injections'],
					'regeneration_reinjections' => (int) $existing->regeneration_reinjections + (int) $delta['regeneration_reinjections'],
					'matched_count'             => (int) $existing->matched_count + (int) $delta['matched_count'],
					'skipped_conflict_count'    => (int) $existing->skipped_conflict_count + (int) $delta['skipped_conflict_count'],
					'skipped_exclusion_count'   => (int) $existing->skipped_exclusion_count + (int) $delta['skipped_exclusion_count'],
					'dry_run_matches'           => (int) $existing->dry_run_matches + (int) $delta['dry_run_matches'],
					'dry_run_total'             => (int) $existing->dry_run_total + (int) $delta['dry_run_total'],
					'last_seen_at'              => $timestamp,
				),
				array( 'component_id' => $component_id ),
				array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d' ),
				array( '%d' )
			);
			return;
		}

		$this->wpdb->insert(
			$this->table_name,
			array(
				'component_id'              => $component_id,
				'impressions'               => (int) $delta['impressions'],
				'injections'                => (int) $delta['injections'],
				'regeneration_reinjections' => (int) $delta['regeneration_reinjections'],
				'matched_count'             => (int) $delta['matched_count'],
				'skipped_conflict_count'    => (int) $delta['skipped_conflict_count'],
				'skipped_exclusion_count'   => (int) $delta['skipped_exclusion_count'],
				'dry_run_matches'           => (int) $delta['dry_run_matches'],
				'dry_run_total'             => (int) $delta['dry_run_total'],
				'last_seen_at'              => $timestamp,
			),
			array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d' )
		);
	}

	/**
	 * @return array<string,int|float>
	 */
	private function get_default_usage_payload() {
		return array(
			'impressions'               => 0,
			'injections'                => 0,
			'regeneration_reinjections' => 0,
			'matched_count'             => 0,
			'skipped_conflict_count'    => 0,
			'skipped_exclusion_count'   => 0,
			'dry_run_matches'           => 0,
			'dry_run_total'             => 0,
			'dry_run_match_rate'        => 0,
			'last_seen_at'              => 0,
			'unique_posts'              => 0,
			'last_injected_at'          => 0,
		);
	}
}
