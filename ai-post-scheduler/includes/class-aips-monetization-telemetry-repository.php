<?php
/**
 * Monetization Telemetry Repository
 *
 * Handles atomic aggregated recording and reporting of ad impressions and clicks.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Monetization_Telemetry_Repository {

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * @var string
	 */
	private $table;

	/**
	 * Supported telemetry event types.
	 */
	const VALID_EVENT_TYPES = array( 'impression', 'click', 'smart_refresh', 'ad_block_detected' );

	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'aips_monetization_events';
	}

	/**
	 * Record a single event (impression, click, smart_refresh, or ad_block_detected) with atomic aggregation.
	 *
	 * @param int    $slot_id     Ad slot ID.
	 * @param int    $post_id     WordPress post ID.
	 * @param int    $campaign_id Sponsor campaign ID (0 if none).
	 * @param string $event_type  'impression', 'click', 'smart_refresh', or 'ad_block_detected'.
	 * @param string $device_type 'desktop', 'mobile', or 'tablet'.
	 * @param int    $count       Number of events.
	 * @return bool
	 */
	public function record_event( $slot_id, $post_id, $campaign_id = 0, $event_type = 'impression', $device_type = 'desktop', $count = 1 ) {
		$slot_id     = absint( $slot_id );
		$post_id     = absint( $post_id );
		$campaign_id = absint( $campaign_id );
		$count       = max( 1, absint( $count ) );
		$event_type  = in_array( $event_type, self::VALID_EVENT_TYPES, true ) ? $event_type : 'impression';
		$device_type = in_array( $device_type, array( 'desktop', 'mobile', 'tablet' ), true ) ? $device_type : 'desktop';
		$event_date  = current_time( 'Y-m-d' );

		$query = $this->wpdb->prepare(
			"INSERT INTO {$this->table} (slot_id, campaign_id, post_id, event_type, device_type, event_date, event_count)
			VALUES (%d, %d, %d, %s, %s, %s, %d)
			ON DUPLICATE KEY UPDATE event_count = event_count + VALUES(event_count)",
			$slot_id,
			$campaign_id,
			$post_id,
			$event_type,
			$device_type,
			$event_date,
			$count
		);

		return false !== $this->wpdb->query( $query );
	}

	/**
	 * Batch record multiple telemetry events.
	 *
	 * @param array $events Array of event arrays.
	 * @return int Number of successfully recorded events.
	 */
	public function record_events_batch( array $events ) {
		$recorded = 0;
		foreach ( $events as $event ) {
			if ( ! is_array( $event ) ) {
				continue;
			}
			$success = $this->record_event(
				$event['slot_id'] ?? 0,
				$event['post_id'] ?? 0,
				$event['campaign_id'] ?? 0,
				$event['event_type'] ?? 'impression',
				$event['device_type'] ?? 'desktop',
				$event['count'] ?? 1
			);
			if ( $success ) {
				$recorded++;
			}
		}
		return $recorded;
	}

	/**
	 * Get high-level summary metrics (Impressions, Clicks, CTR).
	 *
	 * @param string $start_date YYYY-MM-DD.
	 * @param string $end_date   YYYY-MM-DD.
	 * @return array
	 */
	public function get_summary( $start_date = '', $end_date = '' ) {
		if ( empty( $start_date ) ) {
			$start_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		}
		if ( empty( $end_date ) ) {
			$end_date = current_time( 'Y-m-d' );
		}

		$results = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT event_type, SUM(event_count) as total 
				FROM {$this->table} 
				WHERE event_date BETWEEN %s AND %s 
				GROUP BY event_type",
				$start_date,
				$end_date
			)
		);

		$impressions = 0;
		$clicks      = 0;
		$refreshes   = 0;
		$ad_blocks   = 0;

		foreach ( $results as $row ) {
			if ( 'impression' === $row->event_type ) {
				$impressions = (int) $row->total;
			} elseif ( 'click' === $row->event_type ) {
				$clicks = (int) $row->total;
			} elseif ( 'smart_refresh' === $row->event_type ) {
				$refreshes = (int) $row->total;
			} elseif ( 'ad_block_detected' === $row->event_type ) {
				$ad_blocks = (int) $row->total;
			}
		}

		$ctr           = ( $impressions > 0 ) ? round( ( $clicks / $impressions ) * 100, 2 ) : 0.0;
		$total_views   = $impressions + $ad_blocks;
		$ad_block_rate = ( $total_views > 0 ) ? round( ( $ad_blocks / $total_views ) * 100, 1 ) : 0.0;

		return array(
			'impressions'   => $impressions,
			'clicks'        => $clicks,
			'refreshes'     => $refreshes,
			'ad_blocks'     => $ad_blocks,
			'ad_block_rate' => $ad_block_rate,
			'ctr'           => $ctr,
			'start_date'    => $start_date,
			'end_date'      => $end_date,
		);
	}

	/**
	 * Get daily trends for charts.
	 *
	 * @param int $days Number of days back.
	 * @return array Array of daily stats {date, impressions, clicks}.
	 */
	public function get_daily_trends( $days = 14 ) {
		$days       = max( 1, min( 90, absint( $days ) ) );
		$start_date = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
		$end_date   = current_time( 'Y-m-d' );

		$results = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT event_date, event_type, SUM(event_count) as total
				FROM {$this->table}
				WHERE event_date BETWEEN %s AND %s
				GROUP BY event_date, event_type
				ORDER BY event_date ASC",
				$start_date,
				$end_date
			)
		);

		$daily_map = array();
		// Prepopulate days
		$current = strtotime( $start_date );
		$end_ts  = strtotime( $end_date );
		while ( $current <= $end_ts ) {
			$d = gmdate( 'Y-m-d', $current );
			$daily_map[ $d ] = array(
				'date'        => $d,
				'impressions' => 0,
				'clicks'      => 0,
			);
			$current = strtotime( '+1 day', $current );
		}

		foreach ( $results as $row ) {
			$d = $row->event_date;
			if ( isset( $daily_map[ $d ] ) ) {
				if ( 'impression' === $row->event_type ) {
					$daily_map[ $d ]['impressions'] = (int) $row->total;
				} elseif ( 'click' === $row->event_type ) {
					$daily_map[ $d ]['clicks'] = (int) $row->total;
				}
			}
		}

		return array_values( $daily_map );
	}

	/**
	 * Get top performing posts by impressions & clicks.
	 *
	 * @param int $limit Max posts.
	 * @return array
	 */
	public function get_top_posts( $limit = 10 ) {
		$limit = max( 1, min( 50, absint( $limit ) ) );

		$results = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT post_id,
					SUM(CASE WHEN event_type = 'impression' THEN event_count ELSE 0 END) as impressions,
					SUM(CASE WHEN event_type = 'click' THEN event_count ELSE 0 END) as clicks
				FROM {$this->table}
				WHERE post_id > 0
				GROUP BY post_id
				ORDER BY impressions DESC
				LIMIT %d",
				$limit
			)
		);

		$data = array();
		foreach ( $results as $row ) {
			$post_title = get_the_title( $row->post_id ) ?: ( 'Post #' . $row->post_id );
			$imp        = (int) $row->impressions;
			$clk        = (int) $row->clicks;
			$ctr        = ( $imp > 0 ) ? round( ( $clk / $imp ) * 100, 2 ) : 0.0;

			$data[] = array(
				'post_id'     => (int) $row->post_id,
				'post_title'  => $post_title,
				'edit_url'    => get_edit_post_link( $row->post_id, 'raw' ),
				'impressions' => $imp,
				'clicks'      => $clk,
				'ctr'         => $ctr,
			);
		}

		return $data;
	}

	/**
	 * Get slot performance breakdown.
	 *
	 * @return array
	 */
	public function get_slot_breakdown() {
		$slots_table = $this->wpdb->prefix . 'aips_ad_slots';
		$results = $this->wpdb->get_results(
			"SELECT m.slot_id, s.name as slot_name, s.slot_type, s.position,
				SUM(CASE WHEN m.event_type = 'impression' THEN m.event_count ELSE 0 END) as impressions,
				SUM(CASE WHEN m.event_type = 'click' THEN m.event_count ELSE 0 END) as clicks
			FROM {$this->table} m
			LEFT JOIN {$slots_table} s ON m.slot_id = s.id
			WHERE m.slot_id > 0
			GROUP BY m.slot_id
			ORDER BY impressions DESC"
		);

		$data = array();
		foreach ( $results as $row ) {
			$imp = (int) $row->impressions;
			$clk = (int) $row->clicks;
			$ctr = ( $imp > 0 ) ? round( ( $clk / $imp ) * 100, 2 ) : 0.0;

			$data[] = array(
				'slot_id'     => (int) $row->slot_id,
				'slot_name'   => $row->slot_name ?: ( 'Slot #' . $row->slot_id ),
				'slot_type'   => $row->slot_type ?: 'custom_html',
				'position'    => $row->position ?: 'after_paragraph',
				'impressions' => $imp,
				'clicks'      => $clk,
				'ctr'         => $ctr,
			);
		}

		return $data;
	}
}
