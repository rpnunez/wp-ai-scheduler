<?php
/** Atomic, expiring ownership for manual and scheduled source fetching. */
if (!defined('ABSPATH')) { exit; }

class AIPS_Sources_Fetch_Lease {
	const OPTION = 'aips_sources_fetch_lease';
	const TTL = 300;

	private $option_name;

	public function __construct( $option_name = self::OPTION ) {
		$this->option_name = $option_name;
	}

	public function acquire(): string|false {
		global $wpdb;
		$owner = wp_generate_uuid4();
		$value = array( 'owner' => $owner, 'expires' => AIPS_DateTime::now()->timestamp() + self::TTL );
		// Older supported WordPress versions implement add_option() as an upsert.
		// Only an insert-only statement lets the unique index grant one owner.
		$inserted = $wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
			$this->option_name, maybe_serialize( $value )
		) );
		if ( 1 === $inserted ) {
			$this->clear_cache();
			return $owner;
		}
		// Read directly: object caches must never decide ownership.
		$old = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $this->option_name ) );
		$lease = maybe_unserialize( $old );
		if ( ! is_array( $lease ) || (int) ( $lease['expires'] ?? 0 ) > AIPS_DateTime::now()->timestamp() ) {
			return false;
		}
		$changed = $wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
			maybe_serialize( $value ), $this->option_name, $old
		) );
		$this->clear_cache();
		return 1 === $changed ? $owner : false;
	}

	public function release( string $owner ): void {
		global $wpdb;
		$old = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $this->option_name ) );
		$lease = maybe_unserialize( $old );
		if ( is_array( $lease ) && hash_equals( (string) ( $lease['owner'] ?? '' ), $owner ) ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $this->option_name, $old ) );
			$this->clear_cache();
		}
	}

	private function clear_cache(): void {
		wp_cache_delete( $this->option_name, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
	}
}
