<?php
/**
 * Content Component Rules Repository
 *
 * @package AI_Post_Scheduler
 * @since 2.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Content_Component_Rules_Repository {

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * @var string
	 */
	private $table_name;

	/**
	 * @var AIPS_Cache
	 */
	private $cache;

	public function __construct() {
		global $wpdb;
		$this->wpdb       = $wpdb;
		$this->table_name = $wpdb->prefix . 'aips_content_component_rules';
		$this->cache      = AIPS_Cache_Factory::named( 'aips_content_component_rules_repository' );
	}

	/**
	 * Return enabled rules keyed by component ID.
	 *
	 * @param int[] $component_ids Component IDs.
	 * @return array<int,array<int,array<string,mixed>>>
	 */
	public function get_enabled_rules_for_component_ids( array $component_ids ) {
		$component_ids = array_values(
			array_filter(
				array_map( 'absint', $component_ids )
			)
		);

		if ( empty( $component_ids ) ) {
			return array();
		}

		sort( $component_ids );
		$cache_key = 'enabled:' . md5( wp_json_encode( $component_ids ) );
		if ( $this->cache->has( $cache_key ) ) {
			return (array) $this->cache->get( $cache_key );
		}

		$placeholders = implode( ',', array_fill( 0, count( $component_ids ), '%d' ) );
		$sql          = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE enabled = 1 AND component_id IN ({$placeholders}) ORDER BY priority DESC, component_id ASC, id ASC",
			$component_ids
		);
		$rows         = $this->wpdb->get_results( $sql );
		$rules        = array();

		foreach ( (array) $rows as $row ) {
			$component_id = (int) $row->component_id;
			if ( ! isset( $rules[ $component_id ] ) ) {
				$rules[ $component_id ] = array();
			}
			$rules[ $component_id ][] = $this->normalize_rule( $row );
		}

		$this->cache->set( $cache_key, $rules, 300 );

		return $rules;
	}

	/**
	 * Create or update the default Phase 1 rule for a component from the
	 * existing admin payload.
	 *
	 * @param int   $component_id Component ID.
	 * @param array $legacy_rules Existing UI rules payload.
	 * @param bool  $enabled Whether rule should be enabled.
	 * @param int   $priority Rule priority.
	 * @return bool
	 */
	public function upsert_legacy_rule_for_component( $component_id, array $legacy_rules, $enabled = true, $priority = 100 ) {
		$component_id = absint( $component_id );
		if ( $component_id < 1 ) {
			return false;
		}

		$existing = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE component_id = %d ORDER BY id ASC LIMIT 1",
				$component_id
			)
		);

		$normalized_payload = $this->normalize_legacy_payload( $legacy_rules );
		$timestamp          = AIPS_DateTime::now()->timestamp();

		$data = array(
			'component_id'     => $component_id,
			'priority'         => (int) $priority,
			'placement'        => $normalized_payload['placement'],
			'frequency_mode'   => $normalized_payload['frequency_mode'],
			'max_occurrences'  => $normalized_payload['max_occurrences'],
			'conditions_json'  => wp_json_encode( $normalized_payload['conditions_json'] ),
			'exclusions_json'  => wp_json_encode( $normalized_payload['exclusions_json'] ),
			'date_window_json' => wp_json_encode( $normalized_payload['date_window_json'] ),
			'enabled'          => $enabled ? 1 : 0,
			'updated_at'       => $timestamp,
		);

		if ( $existing ) {
			$result = false !== $this->wpdb->update(
				$this->table_name,
				$data,
				array( 'id' => (int) $existing->id ),
				array( '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d' ),
				array( '%d' )
			);
			if ( $result ) {
				$this->flush_caches();
			}
			return $result;
		}

		$data['created_at'] = $timestamp;

		$result = false !== $this->wpdb->insert(
			$this->table_name,
			$data,
			array( '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%d' )
		);
		if ( $result ) {
			$this->flush_caches();
		}
		return $result;
	}

	/**
	 * Flush repository and matcher caches after rule writes.
	 *
	 * @return void
	 */
	private function flush_caches() {
		$this->cache->flush();
		AIPS_Cache_Factory::named( 'aips_content_component_matcher' )->flush();
	}

	/**
	 * Parse the default rule placement and conditions from legacy admin JSON.
	 *
	 * @param array $legacy_rules Current content-components rules payload.
	 * @return array<string,mixed>
	 */
	public function normalize_legacy_payload( array $legacy_rules ) {
		$action     = isset( $legacy_rules['action'] ) ? sanitize_key( (string) $legacy_rules['action'] ) : 'add_at_end';
		$logic      = isset( $legacy_rules['logic'] ) && 'or' === sanitize_key( (string) $legacy_rules['logic'] ) ? 'or' : 'and';
		$conditions = array();

		foreach ( (array) ( $legacy_rules['conditions'] ?? array() ) as $condition ) {
			if ( ! is_array( $condition ) ) {
				continue;
			}

			$values = array_values(
				array_filter(
					array_map(
						static function ( $value ) {
							return sanitize_text_field( (string) $value );
						},
						(array) ( $condition['values'] ?? array() )
					),
					static function ( $value ) {
						return '' !== $value;
					}
				)
			);

			$conditions[] = array(
				'field'    => isset( $condition['field'] ) ? sanitize_key( (string) $condition['field'] ) : 'category',
				'operator' => isset( $condition['operator'] ) ? sanitize_key( (string) $condition['operator'] ) : 'is',
				'values'   => $values,
			);
		}

		$placement = 'end_of_post';
		if ( 'prepend_intro' === $action ) {
			$placement = 'after_intro';
		} elseif ( 'add_before_first_heading' === $action ) {
			$placement = 'before_content';
		} elseif ( 'add_middle_paragraph' === $action ) {
			$placement = 'after_nth_h2:2';
		} elseif ( 'replace_summary' === $action ) {
			$placement = 'before_conclusion';
		}

		return array(
			'placement'        => $placement,
			'frequency_mode'   => 'once_per_post',
			'max_occurrences'  => 1,
			'conditions_json'  => array(
				'logic'      => $logic,
				'conditions' => $conditions,
			),
			'exclusions_json'  => array(
				'logic'      => 'or',
				'conditions' => array(),
			),
			'date_window_json' => isset( $legacy_rules['date_window'] ) && is_array( $legacy_rules['date_window'] )
				? $legacy_rules['date_window']
				: array(),
		);
	}

	/**
	 * Normalize a DB row for service consumption.
	 *
	 * @param object $row Raw DB row.
	 * @return array<string,mixed>
	 */
	private function normalize_rule( $row ) {
		return array(
			'id'               => (int) $row->id,
			'component_id'     => (int) $row->component_id,
			'priority'         => (int) $row->priority,
			'placement'        => (string) $row->placement,
			'frequency_mode'   => (string) $row->frequency_mode,
			'max_occurrences'  => (int) $row->max_occurrences,
			'conditions_json'  => $this->decode_json_array( $row->conditions_json ),
			'exclusions_json'  => $this->decode_json_array( $row->exclusions_json ),
			'date_window_json' => $this->decode_json_array( $row->date_window_json ),
			'enabled'          => (int) $row->enabled,
		);
	}

	/**
	 * Decode JSON object/array to array.
	 *
	 * @param mixed $json JSON string.
	 * @return array
	 */
	private function decode_json_array( $json ) {
		$decoded = json_decode( (string) $json, true );
		return is_array( $decoded ) ? $decoded : array();
	}
}
