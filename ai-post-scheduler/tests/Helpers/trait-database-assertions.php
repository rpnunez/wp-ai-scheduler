<?php
/**
 * Database assertions helper trait for workflow tests
 * Provides high-level assertions for verifying database state
 */
trait Trait_Database_Assertions {

	/**
	 * Assert a database record exists with given attributes
	 *
	 * @param string $table The table name (without wp_ prefix)
	 * @param array  $attributes Key-value pairs to match
	 * @throws Exception
	 */
	public function assertDatabaseHas( $table, array $attributes ) {
		global $wpdb;

		$table = $wpdb->prefix . $table;
		$where = '';
		foreach ( $attributes as $key => $value ) {
			if ( $where ) {
				$where .= ' AND ';
			}
			$where .= $wpdb->prepare( "{$key} = %s", $value );
		}

		$result = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
		$this->assertGreaterThan(
			0,
			$result,
			"Database has no record in '{$table}' matching " . wp_json_encode( $attributes )
		);
	}

	/**
	 * Assert a database record does not exist with given attributes
	 *
	 * @param string $table The table name (without wp_ prefix)
	 * @param array  $attributes Key-value pairs to match
	 * @throws Exception
	 */
	public function assertDatabaseMissing( $table, array $attributes ) {
		global $wpdb;

		$table = $wpdb->prefix . $table;
		$where = '';
		foreach ( $attributes as $key => $value ) {
			if ( $where ) {
				$where .= ' AND ';
			}
			$where .= $wpdb->prepare( "{$key} = %s", $value );
		}

		$result = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
		$this->assertEquals(
			0,
			$result,
			"Database unexpectedly has record in '{$table}' matching " . wp_json_encode( $attributes )
		);
	}

	/**
	 * Assert a table has exactly N records matching given attributes
	 *
	 * @param string  $table The table name (without wp_ prefix)
	 * @param int     $count Expected count
	 * @param array   $attributes Optional key-value pairs to filter by
	 * @throws Exception
	 */
	public function assertDatabaseCount( $table, $count, $attributes = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . $table;
		$where = '';
		if ( ! empty( $attributes ) ) {
			foreach ( $attributes as $key => $value ) {
				if ( $where ) {
					$where .= ' AND ';
				}
				$where .= $wpdb->prepare( "{$key} = %s", $value );
			}
			$where = ' WHERE ' . $where;
		}

		$result = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}{$where}" );
		$this->assertEquals(
			$count,
			$result,
			"Expected {$count} records in '{$table}' but found {$result}"
		);
	}

	/**
	 * Assert post metadata exists with expected value
	 *
	 * @param int         $post_id Post ID
	 * @param string      $meta_key Meta key
	 * @param mixed|null  $expected_value Expected value (optional)
	 * @throws Exception
	 */
	public function assertPostMeta( $post_id, $meta_key, $expected_value = null ) {
		$meta_value = get_post_meta( $post_id, $meta_key, true );

		if ( null === $expected_value ) {
			$this->assertNotEmpty(
				$meta_value,
				"Post {$post_id} has no '{$meta_key}' meta"
			);
		} else {
			$this->assertEquals(
				$expected_value,
				$meta_value,
				"Post {$post_id} meta '{$meta_key}' mismatch"
			);
		}
	}

	/**
	 * Assert WordPress option exists with expected value
	 *
	 * @param string      $option_key Option name
	 * @param mixed|null  $expected_value Expected value (optional)
	 * @throws Exception
	 */
	public function assertWpOption( $option_key, $expected_value = null ) {
		$option_value = get_option( $option_key );

		if ( null === $expected_value ) {
			$this->assertNotEmpty(
				$option_value,
				"WordPress option '{$option_key}' is empty"
			);
		} else {
			$this->assertEquals(
				$expected_value,
				$option_value,
				"WordPress option '{$option_key}' mismatch"
			);
		}
	}

	/**
	 * Assert a history record exists for given criteria
	 *
	 * @param string $event_type Event type
	 * @param array  $attributes Optional additional attributes to match
	 * @throws Exception
	 */
	public function assertHistoryRecordExists( $event_type, $attributes = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'aips_history';
		$where = $wpdb->prepare( 'event_type = %s', $event_type );

		foreach ( $attributes as $key => $value ) {
			$where .= ' AND ' . $wpdb->prepare( "{$key} = %s", $value );
		}

		$result = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
		$this->assertGreaterThan(
			0,
			$result,
			"No history record found for event_type '{$event_type}' with " . wp_json_encode( $attributes )
		);
	}

	/**
	 * Assert a history record does not exist
	 *
	 * @param string $event_type Event type
	 * @param array  $attributes Optional additional attributes to match
	 * @throws Exception
	 */
	public function assertHistoryRecordMissing( $event_type, $attributes = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'aips_history';
		$where = $wpdb->prepare( 'event_type = %s', $event_type );

		foreach ( $attributes as $key => $value ) {
			$where .= ' AND ' . $wpdb->prepare( "{$key} = %s", $value );
		}

		$result = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
		$this->assertEquals(
			0,
			$result,
			"Unexpectedly found history record for event_type '{$event_type}' with " . wp_json_encode( $attributes )
		);
	}
}
