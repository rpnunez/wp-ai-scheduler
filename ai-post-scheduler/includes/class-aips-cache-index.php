<?php
if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Cache_Index {

	private static $instance = null;
	private $repository;

	public static function get_instance() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct( AIPS_Cache_Monitor_Repository $repository = null ) {
		$this->repository = $repository ? $repository : new AIPS_Cache_Monitor_Repository();
	}

	public function record_set( $key, $value, $ttl = 0, $group = 'default', AIPS_Cache_Driver $driver = null, array $metadata = array() ) {
		if (!$this->is_enabled()) {
			return false;
		}
		$driver_name = $driver ? $this->driver_name( $driver ) : '';
		$metadata = array_merge( $this->infer_metadata( $key, $group ), $metadata );
		$serialized = maybe_serialize( $value );
		return $this->repository->upsert_index( array(
			'cache_key' => (string) $key,
			'key_hash' => $this->hash_key( $key ),
			'cache_group' => (string) $group,
			'driver' => $driver_name,
			'tier' => isset( $metadata['tier'] ) ? $metadata['tier'] : 'default',
			'operation_id' => isset( $metadata['operation_id'] ) ? $metadata['operation_id'] : '',
			'repository_class' => isset( $metadata['repository_class'] ) ? $metadata['repository_class'] : '',
			'tags' => isset( $metadata['tags'] ) ? $metadata['tags'] : array(),
			'domain' => isset( $metadata['domain'] ) ? $metadata['domain'] : '',
			'source' => isset( $metadata['source'] ) ? $metadata['source'] : 'cache_api',
			'ttl' => (int) $ttl,
			'estimated_size' => strlen( (string) $serialized ),
			'value_type' => $this->value_type( $value ),
		) );
	}

	public function record_delete( $key, $group = 'default' ) {
		if (!$this->is_enabled()) {
			return 0;
		}
		return $this->repository->delete_index( $key, $group );
	}

	public function record_flush() {
		if (!$this->is_enabled()) {
			return 0;
		}
		return $this->repository->reset_index();
	}

	public function record_access( $key, $group = 'default' ) {
		if (!$this->is_enabled()) {
			return false;
		}
		return $this->repository->touch_access( $key, $group );
	}

	private function infer_metadata( $key, $group ) {
		$metadata = array(
			'tags' => array(),
			'tier' => 'default',
			'source' => 'cache_api',
		);
		$group = (string) $group;
		$key = (string) $key;
		if (strpos( $group, 'repository' ) !== false || strpos( $key, 'repository' ) !== false) {
			$metadata['source'] = 'repository_cache';
			$metadata['tier'] = 'repository';
		}
		if (strpos( $group, 'aips_config' ) !== false) {
			$metadata['source'] = 'config_cache';
			$metadata['tier'] = 'request';
		}
		if (strpos( $group, 'template' ) !== false || strpos( $key, 'template' ) !== false) {
			$metadata['source'] = 'template_cache';
			$metadata['tags'][] = 'templates';
		}
		$metadata['operation_id'] = substr( sanitize_key( $group . '_' . preg_replace( '/[^a-zA-Z0-9_\-]/', '_', $key ) ), 0, 191 );
		return $metadata;
	}

	private function is_enabled() {
		$enabled = get_option( 'aips_cache_monitor_index_enabled', '1' );
		return $enabled !== '0' && $enabled !== 0 && $enabled !== false;
	}

	private function driver_name( AIPS_Cache_Driver $driver ) {
		$class = get_class( $driver );
		$class = str_replace( array( 'AIPS_Cache_', '_Driver' ), '', $class );
		return strtolower( str_replace( '_', '-', $class ) );
	}

	private function value_type( $value ) {
		if (is_object( $value )) {
			return 'object:' . get_class( $value );
		}
		if (is_array( $value )) {
			return 'array';
		}
		return gettype( $value );
	}

	private function hash_key( $key ) {
		return hash( 'sha256', (string) $key );
	}
}
