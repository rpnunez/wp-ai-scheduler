<?php
/**
 * Content Components Repository
 *
 * Database abstraction layer for content component CRUD plus runtime reads.
 *
 * @package AI_Post_Scheduler
 * @since 2.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Content_Components_Repository {

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
		$this->table_name = $wpdb->prefix . 'aips_content_components';
		$this->cache      = AIPS_Cache_Factory::named( 'aips_content_components_repository' );
	}

	/**
	 * Get all components.
	 *
	 * @param bool $active_only Whether to only return active rows.
	 * @return array<int,object>
	 */
	public function get_all( $active_only = false ) {
		$key = 'all:' . ( $active_only ? '1' : '0' );
		if ( $this->cache->has( $key ) ) {
			return (array) $this->cache->get( $key );
		}

		if ( $active_only ) {
			$sql = $this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE is_active = %d ORDER BY updated_at DESC, title ASC, id ASC",
				1
			);
		} else {
			$sql = "SELECT * FROM {$this->table_name} ORDER BY updated_at DESC, title ASC, id ASC";
		}

		$rows = $this->wpdb->get_results( $sql );
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		$this->cache->set( $key, $rows );

		return $rows;
	}

	/**
	 * Return all active components that can participate in injection.
	 *
	 * @return array<int,object>
	 */
	public function get_active_components() {
		$key = 'active_runtime';
		if ( $this->cache->has( $key ) ) {
			return (array) $this->cache->get( $key );
		}

		$sql  = "SELECT * FROM {$this->table_name} WHERE is_active = 1 AND (status = 'active' OR status = 'draft' OR status = '') ORDER BY id ASC";
		$rows = $this->wpdb->get_results( $sql );
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		$this->cache->set( $key, $rows, 300 );

		return $rows;
	}

	/**
	 * Get one component by ID.
	 *
	 * @param int $id Component ID.
	 * @return object|null
	 */
	public function get_by_id( $id ) {
		$key = 'id:' . absint( $id );
		if ( $this->cache->has( $key ) ) {
			return $this->cache->get( $key );
		}

		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				absint( $id )
			)
		);

		if ( $row ) {
			$this->cache->set( $key, $row );
		}

		return $row;
	}

	/**
	 * Check whether a component title already exists.
	 *
	 * @param string $title Title to check.
	 * @param int    $exclude_id Optional ID to exclude.
	 * @return bool
	 */
	public function title_exists( $title, $exclude_id = 0 ) {
		$title      = sanitize_text_field( (string) $title );
		$exclude_id = absint( $exclude_id );

		if ( $exclude_id > 0 ) {
			$count = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->table_name} WHERE title = %s AND id != %d",
					$title,
					$exclude_id
				)
			);
		} else {
			$count = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->table_name} WHERE title = %s",
					$title
				)
			);
		}

		return (int) $count > 0;
	}

	/**
	 * Create a component.
	 *
	 * @param array<string,mixed> $data Component payload.
	 * @return int|false
	 */
	public function create( $data ) {
		$now         = AIPS_DateTime::now()->timestamp();
		$insert_data = $this->normalize_write_data( $data );
		$insert_data['created_at'] = isset( $data['created_at'] ) ? absint( $data['created_at'] ) : $now;
		$insert_data['updated_at'] = isset( $data['updated_at'] ) ? absint( $data['updated_at'] ) : $now;

		$result = $this->wpdb->insert(
			$this->table_name,
			$insert_data,
			$this->get_create_formats()
		);

		if ( $result ) {
			$this->flush_cache();
		}

		return $result ? (int) $this->wpdb->insert_id : false;
	}

	/**
	 * Update a component.
	 *
	 * @param int                 $id Component ID.
	 * @param array<string,mixed> $data Component payload.
	 * @return int|false
	 */
	public function update( $id, $data ) {
		$id = absint( $id );
		if ( $id < 1 ) {
			return false;
		}

		$update_data = array();
		$formats     = array();

		$field_sanitizers = array(
			'title'           => static function ( $value ) {
				return sanitize_text_field( (string) $value );
			},
			'slug'            => static function ( $value ) {
				return sanitize_title( (string) $value );
			},
			'description'     => static function ( $value ) {
				return sanitize_textarea_field( (string) $value );
			},
			'status'          => function ( $value ) {
				return $this->normalize_status( $value );
			},
			'component_type'  => static function ( $value ) {
				return sanitize_key( (string) $value );
			},
			'content_mode'    => function ( $value ) {
				return $this->normalize_content_mode( $value );
			},
			'content'         => static function ( $value ) {
				return AIPS_Content_Component_Injection_Service::sanitize_content_preserving_markers( (string) $value );
			},
			'content_payload' => static function ( $value ) {
				return AIPS_Content_Component_Injection_Service::sanitize_content_preserving_markers( (string) $value );
			},
			'media_payload'   => static function ( $value ) {
				return wp_json_encode( $value );
			},
			'cta_payload'     => static function ( $value ) {
				return wp_json_encode( $value );
			},
			'rules_json'      => static function ( $value ) {
				return wp_json_encode( $value );
			},
			'qa_status'       => static function ( $value ) {
				return sanitize_key( (string) $value );
			},
			'qa_notes'        => static function ( $value ) {
				return sanitize_textarea_field( (string) $value );
			},
			'is_active'       => static function ( $value ) {
				return ! empty( $value ) ? 1 : 0;
			},
		);

		$field_formats = array(
			'title'           => '%s',
			'slug'            => '%s',
			'description'     => '%s',
			'status'          => '%s',
			'component_type'  => '%s',
			'content_mode'    => '%s',
			'content'         => '%s',
			'content_payload' => '%s',
			'media_payload'   => '%s',
			'cta_payload'     => '%s',
			'rules_json'      => '%s',
			'qa_status'       => '%s',
			'qa_notes'        => '%s',
			'is_active'       => '%d',
		);

		foreach ( $field_sanitizers as $field => $sanitize_callback ) {
			if ( ! array_key_exists( $field, $data ) ) {
				continue;
			}

			$update_data[ $field ] = call_user_func( $sanitize_callback, $data[ $field ] );
			$formats[]             = $field_formats[ $field ];
		}

		if ( empty( $update_data ) ) {
			return 0;
		}

		$update_data['updated_at'] = isset( $data['updated_at'] ) ? absint( $data['updated_at'] ) : AIPS_DateTime::now()->timestamp();
		$formats[]                 = '%d';

		$result = $this->wpdb->update(
			$this->table_name,
			$update_data,
			array( 'id' => $id ),
			$formats,
			array( '%d' )
		);

		if ( false !== $result ) {
			$this->flush_cache();
		}

		return $result;
	}

	/**
	 * Delete a component.
	 *
	 * @param int $id Component ID.
	 * @return int|false
	 */
	public function delete( $id ) {
		$result = $this->wpdb->delete(
			$this->table_name,
			array( 'id' => absint( $id ) ),
			array( '%d' )
		);

		if ( false !== $result ) {
			$this->flush_cache();
		}

		return $result;
	}

	/**
	 * Set active status.
	 *
	 * @param int  $id Component ID.
	 * @param bool $is_active Whether the row should be active.
	 * @return int|false
	 */
	public function set_active( $id, $is_active ) {
		return $this->update(
			$id,
			array(
				'is_active' => $is_active ? 1 : 0,
				'status'    => $is_active ? 'active' : 'draft',
			)
		);
	}

	/**
	 * Get list-page counts.
	 *
	 * @return array<string,int>
	 */
	public function get_counts() {
		$key = 'counts';
		if ( $this->cache->has( $key ) ) {
			return (array) $this->cache->get( $key );
		}

		$results = $this->wpdb->get_row(
			"SELECT
				COUNT(*) AS total,
				SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active,
				SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive,
				SUM(CASE WHEN qa_status = 'needs_review' THEN 1 ELSE 0 END) AS needs_review
			FROM {$this->table_name}",
			ARRAY_A
		);

		$counts = array(
			'total'        => isset( $results['total'] ) ? (int) $results['total'] : 0,
			'active'       => isset( $results['active'] ) ? (int) $results['active'] : 0,
			'inactive'     => isset( $results['inactive'] ) ? (int) $results['inactive'] : 0,
			'needs_review' => isset( $results['needs_review'] ) ? (int) $results['needs_review'] : 0,
		);

		$this->cache->set( $key, $counts );

		return $counts;
	}

	/**
	 * Populate extended Phase 1 columns for an existing component row.
	 *
	 * @param int   $component_id Component ID.
	 * @param array $data Extended component data.
	 * @return bool
	 */
	public function update_extended_fields( $component_id, array $data ) {
		return false !== $this->update( $component_id, $data );
	}

	/**
	 * @param array<string,mixed> $data Raw component payload.
	 * @return array<string,mixed>
	 */
	private function normalize_write_data( array $data ) {
		return array(
			'title'           => isset( $data['title'] ) ? sanitize_text_field( (string) $data['title'] ) : '',
			'slug'            => isset( $data['slug'] ) ? sanitize_title( (string) $data['slug'] ) : '',
			'description'     => isset( $data['description'] ) ? sanitize_textarea_field( (string) $data['description'] ) : '',
			'status'          => isset( $data['status'] ) ? $this->normalize_status( $data['status'] ) : 'draft',
			'component_type'  => isset( $data['component_type'] ) ? sanitize_key( (string) $data['component_type'] ) : 'custom',
			'content_mode'    => isset( $data['content_mode'] ) ? $this->normalize_content_mode( $data['content_mode'] ) : 'html',
			'content'         => isset( $data['content'] ) ? AIPS_Content_Component_Injection_Service::sanitize_content_preserving_markers( (string) $data['content'] ) : '',
			'content_payload' => isset( $data['content_payload'] ) ? AIPS_Content_Component_Injection_Service::sanitize_content_preserving_markers( (string) $data['content_payload'] ) : '',
			'media_payload'   => isset( $data['media_payload'] ) ? wp_json_encode( $data['media_payload'] ) : wp_json_encode( array() ),
			'cta_payload'     => isset( $data['cta_payload'] ) ? wp_json_encode( $data['cta_payload'] ) : wp_json_encode( array() ),
			'rules_json'      => isset( $data['rules_json'] ) ? wp_json_encode( $data['rules_json'] ) : wp_json_encode( array() ),
			'qa_status'       => isset( $data['qa_status'] ) ? sanitize_key( (string) $data['qa_status'] ) : 'untested',
			'qa_notes'        => isset( $data['qa_notes'] ) ? sanitize_textarea_field( (string) $data['qa_notes'] ) : '',
			'is_active'       => ! empty( $data['is_active'] ) ? 1 : 0,
		);
	}

	/**
	 * @return array<int,string>
	 */
	private function get_update_formats() {
		return array(
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%d',
			'%d',
		);
	}

	/**
	 * @return array<int,string>
	 */
	private function get_create_formats() {
		$formats   = $this->get_update_formats();
		$formats[] = '%d';
		return $formats;
	}

	/**
	 * @param mixed $status Status value.
	 * @return string
	 */
	private function normalize_status( $status ) {
		$status = sanitize_key( (string) $status );
		return in_array( $status, array( 'active', 'draft' ), true ) ? $status : 'draft';
	}

	/**
	 * @param mixed $content_mode Content mode value.
	 * @return string
	 */
	private function normalize_content_mode( $content_mode ) {
		$content_mode = sanitize_key( (string) $content_mode );
		return in_array( $content_mode, array( 'html', 'shortcode', 'template' ), true ) ? $content_mode : 'html';
	}

	/**
	 * Flush all cached repository reads.
	 *
	 * @return void
	 */
	private function flush_cache() {
		$this->cache->flush();
		AIPS_Cache_Factory::named( 'aips_content_component_matcher' )->flush();
	}
}
