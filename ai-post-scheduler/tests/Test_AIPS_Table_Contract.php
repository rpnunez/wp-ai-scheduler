<?php
/** Regression coverage for direct use of the installed plugin schema. */
class Test_AIPS_Table_Contract extends WP_UnitTestCase {
	private $fixture_post_ids = array();

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();
	}

	public function test_current_version_startup_does_not_probe_tables() {
		AIPS_Config::get_instance()->set_option( 'aips_db_version', AIPS_VERSION );
		$queries = array();
		$capture = static function( $sql ) use ( &$queries ) {
			$queries[] = $sql;
			return $sql;
		};
		add_filter( 'query', $capture );
		try {
			AIPS_DB_Migrations::check_and_run();
		} finally {
			remove_filter( 'query', $capture );
		}
		$this->assertSame( AIPS_VERSION, get_option( 'aips_db_version' ) );
		$this->assert_no_table_probes( $queries );
	}

	public function test_datetime_repair_map_uses_current_schema() {
		$schema = AIPS_DB_Manager::get_expected_columns();
		foreach ( AIPS_DB_Manager::get_datetime_column_map() as $table => $columns ) {
			$this->assertArrayHasKey( $table, $schema );
			foreach ( $columns as $column ) {
				$this->assertContains( $column[0], $schema[ $table ], $table );
			}
		}
	}

	public function test_schema_application_can_defer_version_acknowledgement() {
		AIPS_Config::get_instance()->set_option( 'aips_db_version', '3.7.15' );
		$this->assertTrue( AIPS_DB_Manager::install_tables( false ) );
		$this->assertSame( '3.7.15', get_option( 'aips_db_version' ) );
		$this->assertSame( '3.7.15', AIPS_Config::get_instance()->get_option( 'aips_db_version' ) );
	}

	/** @dataProvider supported_versions */
	public function test_upgrade_runs_only_later_retained_steps( $version, $repairs, $classification, $approval, $sources, $index ) {
		AIPS_Config::get_instance()->set_option( 'aips_db_version', $version );
		$queries = array();
		$capture = static function( $sql ) use ( &$queries ) {
			$queries[] = $sql;
			return $sql;
		};
		add_filter( 'query', $capture );
		try {
			$this->assertTrue( AIPS_DB_Migrations::check_and_run() );
		} finally {
			remove_filter( 'query', $capture );
		}
		$count = static function( $pattern ) use ( $queries ) {
			return count( preg_grep( $pattern, $queries ) );
		};
		$this->assertSame( $repairs, $count( '/^UPDATE .*aips_history\s+SET status/s' ) );
		$this->assertSame( $classification, $count( '/^UPDATE .*aips_history SET record_kind/s' ) > 0 );
		$this->assertSame( $approval, $count( '/^UPDATE .*aips_authors.*SET topic_auto_approval_min_relevance/s' ) > 0 );
		$this->assertSame( $sources, $count( '/^SHOW COLUMNS FROM .*aips_sources.*next_fetch_at/s' ) > 0 );
		$this->assertSame( $index, $count( '/^SHOW INDEX FROM .*aips_history_log.*history_type_ts\x27/s' ) > 0 );
		$this->assertSame( AIPS_VERSION, get_option( 'aips_db_version' ) );
		$this->assert_no_table_probes( $queries );
	}

	public function supported_versions() {
		return array(
			array( '3.7.15', 2, true, true, true, true ),
			array( '3.7.16', 1, true, true, true, true ),
			array( '3.7.17', 0, false, true, true, true ),
			array( '3.7.18', 0, false, false, true, true ),
			array( '3.7.19', 0, false, false, false, true ),
			array( '3.7.20', 0, false, false, false, false ),
		);
	}

	public function test_fresh_install_creates_schema_without_historical_backfills() {
		delete_option( 'aips_db_version' );
		$this->assertTrue( AIPS_DB_Migrations::check_and_run() );
		$this->assertSame( AIPS_VERSION, get_option( 'aips_db_version' ) );
	}

	public function test_schema_failure_preserves_version_and_releases_cache_suspension() {
		global $wpdb;
		AIPS_Config::get_instance()->set_option( 'aips_db_version', '3.7.15' );
		$fail = static function( $sql ) {
			return preg_match( '/^DESCRIBE [^ ]*aips_history[ ;]*$/', $sql ) ? 'INVALID schema inspection fixture' : $sql;
		};
		$previous = $wpdb->suppress_errors( true );
		add_filter( 'query', $fail );
		try {
			$this->assertWPError( AIPS_DB_Migrations::check_and_run() );
			$this->assertSame( '3.7.15', get_option( 'aips_db_version' ) );
			$this->assertSame( '3.7.15', AIPS_Config::get_instance()->get_option( 'aips_db_version' ) );
			// Metadata must remain silent before any successful retry in this request.
			$metadata_queries = array();
			$capture_metadata = static function( $sql ) use ( &$metadata_queries ) {
				if ( false !== strpos( $sql, 'aips_cache_index' ) ) {
					$metadata_queries[] = $sql;
				}
				return $sql;
			};
			add_filter( 'query', $capture_metadata );
			$failed_index = new AIPS_Cache_Index();
			try {
				$failed_index->record_set( 'failed-install', 'value', 60, 'contract' );
				$failed_index->record_delete( 'failed-install', 'contract' );
				$failed_index->flush_buffer();
				$this->assertSame( array(), $metadata_queries );
			} finally {
				remove_filter( 'query', $capture_metadata );
				remove_action( 'shutdown', array( $failed_index, 'flush_buffer' ) );
			}
		} finally {
			remove_filter( 'query', $fail );
			$wpdb->suppress_errors( $previous );
		}
		$this->assertTrue( AIPS_DB_Migrations::check_and_run() );
		$index = new AIPS_Cache_Index();
		try {
			$index->record_set( 'retry', 'value', 60, 'contract' );
			$index->flush_buffer();
			$this->assertSame( '1', $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aips_cache_index WHERE cache_group = 'contract' AND cache_key = 'retry'" ) );
		} finally {
			remove_action( 'shutdown', array( $index, 'flush_buffer' ) );
			$wpdb->delete( $wpdb->prefix . 'aips_cache_index', array( 'cache_group' => 'contract' ) );
		}
	}

	/** @dataProvider migration_failures */
	public function test_failed_migration_preserves_version_and_retries( $version, $pattern ) {
		AIPS_Config::get_instance()->set_option( 'aips_db_version', $version );
		$intercepted = false;
		$fail = static function( $sql ) use ( $pattern, &$intercepted ) {
			if ( preg_match( $pattern, $sql ) ) {
				$intercepted = true;
				return 'INVALID table-contract migration fixture';
			}
			return $sql;
		};
		global $wpdb;
		$previous = $wpdb->suppress_errors( true );
		add_filter( 'query', $fail );
		try {
			$this->assertWPError( AIPS_DB_Migrations::check_and_run() );
			$this->assertTrue( $intercepted, 'Fixture must fail an actual migration statement.' );
			$this->assertSame( $version, get_option( 'aips_db_version' ) );
			$this->assertSame( $version, AIPS_Config::get_instance()->get_option( 'aips_db_version' ) );
		} finally {
			remove_filter( 'query', $fail );
			$wpdb->suppress_errors( $previous );
		}
		AIPS_DB_Migrations::check_and_run();
		$this->assertSame( AIPS_VERSION, get_option( 'aips_db_version' ) );
		$this->assertSame( AIPS_VERSION, AIPS_Config::get_instance()->get_option( 'aips_db_version' ) );
	}

	public function migration_failures() {
		return array(
			'status repair' => array( '3.7.15', '/^UPDATE .*aips_history\s+SET status/s' ),
			'classification' => array( '3.7.16', '/^UPDATE .*aips_history SET record_kind/s' ),
			'classification reporting' => array( '3.7.16', '/SELECT creation_method AS method, COUNT/s' ),
			'post type lookup' => array( '3.7.16', '/SELECT h\.id\s+FROM .*aips_history h/s' ),
			'author approval' => array( '3.7.17', '/^UPDATE .*aips_authors.*SET topic_auto_approval_min_relevance/s' ),
			'source structure' => array( '3.7.18', '/^SHOW COLUMNS FROM .*aips_sources.*next_fetch_at/s' ),
			'log index' => array( '3.7.19', '/^SHOW INDEX FROM .*aips_history_log.*history_type_ts\x27/s' ),
			'version acknowledgement' => array( '3.7.19', '/^UPDATE .*options.*aips_db_version/s' ),
		);
	}

	public function test_diagnostics_inspect_columns_without_table_discovery() {
		$queries = array();
		$capture = static function( $sql ) use ( &$queries ) {
			$queries[] = $sql;
			return $sql;
		};
		add_filter( 'query', $capture );
		try {
			$provider = new AIPS_System_Diagnostics_Environment_Provider();
			$method = new ReflectionMethod( $provider, 'check_database' );
			$method->setAccessible( true );
			$results = $method->invoke( $provider );
			$this->assertCount( count( AIPS_DB_Manager::get_full_table_names() ), $results );
			foreach ( $results as $result ) {
				$this->assertSame( 'ok', $result['status'] );
			}
			$provider = new AIPS_System_Info_Provider();
			$method = new ReflectionMethod( $provider, 'get_database_info' );
			$method->setAccessible( true );
			$this->assertSame( 'ok', $method->invoke( $provider )['plugin_tables']['status'] );
		} finally {
			remove_filter( 'query', $capture );
		}
		$this->assert_no_table_probes( $queries );
	}

	public function test_repository_operations_use_declared_tables_directly() {
		global $wpdb;
		$post_id = self::factory()->post->create();
		$this->fixture_post_ids[] = $post_id;
		$queries = array();
		$capture = static function( $sql ) use ( &$queries ) {
			$queries[] = $sql;
			return $sql;
		};
		add_filter( 'query', $capture );
		try {
			$embeddings = new AIPS_Embeddings_Repository();
			$this->assertNotFalse( $embeddings->upsert( 'post', $post_id, array( 0.6, 0.8 ), 'test', 2 ) );
			$this->assertNotNull( $embeddings->get_by_post_id( $post_id ) );
			$relationships = new AIPS_Relationships_Repository();
			$this->assertNotFalse( $relationships->upsert( 'post', $post_id, 'topic', 987654, 0.8 ) );
			$index = new AIPS_Link_Index_Repository();
			$index->sync_for_source( $post_id, array() );
			$clicks = new AIPS_Link_Clicks_Repository();
			$this->assertTrue( $clicks->record( $post_id, $post_id, 1700000000 ) );
			$this->assertGreaterThanOrEqual( 1, $clicks->get_total( 0 ) );
			$redirects = new AIPS_Redirects_Repository();
			$id = $redirects->insert( array( 'source_path' => '/table-contract-' . $post_id, 'target_url' => home_url( '/target/' ) ) );
			$this->assertNotFalse( $id );
			$this->assertTrue( $redirects->delete( $id ) );
			$backup = ( new AIPS_DB_Manager() )->backup_data();
			$this->assertSame( array_keys( AIPS_DB_Manager::get_full_table_names() ), array_keys( $backup ) );
			( new AIPS_Date_Time_DB_Repair() )->run();
		} finally {
			remove_filter( 'query', $capture );
			$wpdb->delete( $wpdb->prefix . 'aips_embeddings', array( 'object_id' => $post_id ) );
			$wpdb->delete( $wpdb->prefix . 'aips_relationships', array( 'source_id' => $post_id ) );
			$wpdb->delete( $wpdb->prefix . 'aips_link_clicks', array( 'source_post_id' => $post_id ) );
		}
		$this->assert_no_table_probes( $queries );
	}

	public function test_orphan_cleanup_preserves_valid_posts_and_topic_vectors() {
		global $wpdb;
		$post_id = self::factory()->post->create();
		$this->fixture_post_ids[] = $post_id;
		$repo = new AIPS_Embeddings_Repository();
		$repo->upsert( 'post', $post_id, array( 0.6, 0.8 ), 'test', 2 );
		$repo->upsert( 'post', 987654321, array( 0.6, 0.8 ), 'test', 2 );
		$repo->upsert( 'topic', 987654321, array( 0.6, 0.8 ), 'test', 2 );
		$relationships = new AIPS_Relationships_Repository();
		$relationships->upsert( 'post', 987654321, 'post', $post_id, 0.8 );
		$relationships->upsert( 'topic', 987654321, 'post', $post_id, 0.8 );
		$queries = array();
		$capture = static function( $sql ) use ( &$queries ) {
			$queries[] = $sql;
			return $sql;
		};
		add_filter( 'query', $capture );
		try {
			$this->assertGreaterThanOrEqual( 1, ( new AIPS_DB_Prune_Repository() )->clean_orphaned_embeddings() );
		} finally {
			remove_filter( 'query', $capture );
		}
		$this->assertNull( $repo->get_by_post_id( 987654321 ) );
		$this->assertNotNull( $repo->get_by_post_id( $post_id ) );
		$this->assertNotNull( $repo->get_by_topic_id( 987654321 ) );
		$this->assertSame( '0', $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aips_relationships WHERE source_type = 'post' AND source_id = 987654321" ) );
		$this->assertSame( '1', $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aips_relationships WHERE source_type = 'topic' AND source_id = 987654321" ) );
		$this->assert_no_table_probes( $queries );
	}

	public function tearDown(): void {
		global $wpdb;
		// DDL in following tests commits rows, so do not rely on transaction rollback.
		foreach ( array_merge( $this->fixture_post_ids, array( 987654321 ) ) as $id ) {
			$wpdb->delete( $wpdb->prefix . 'aips_embeddings', array( 'object_id' => $id ) );
			$wpdb->delete( $wpdb->prefix . 'aips_relationships', array( 'source_id' => $id ) );
			$wpdb->delete( $wpdb->prefix . 'aips_relationships', array( 'target_id' => $id ) );
		}
		parent::tearDown();
	}

	/** @dataProvider failed_export_reads */
	public function test_export_reports_failed_actual_reads_without_empty_table_output( $format, $pattern ) {
		global $wpdb;
		$exporter = 'sql' === $format ? new AIPS_Data_Management_Export_MySQL() : new AIPS_Data_Management_Export_JSON();
		$fail = static function( $sql ) use ( $pattern ) {
			return preg_match( $pattern, $sql ) ? 'INVALID export read fixture' : $sql;
		};
		$previous = $wpdb->suppress_errors( true );
		add_filter( 'query', $fail );
		try {
			$result = $exporter->export();
		} finally {
			remove_filter( 'query', $fail );
			$wpdb->suppress_errors( $previous );
		}
		if ( 'sql' === $format ) {
			$this->assertStringContainsString( '-- Unable to export table ' . $wpdb->prefix . 'aips_history', $result );
			$this->assertStringNotContainsString( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . 'aips_history`;', $result );
		} else {
			$payload = json_decode( $result, true );
			$this->assertArrayNotHasKey( 'aips_history', $payload['tables'] );
			$this->assertArrayHasKey( 'aips_history', $payload['errors'] );
		}
	}

	public function failed_export_reads() {
		return array(
			'sql schema' => array( 'sql', '/^SHOW CREATE TABLE `[^`]*aips_history`$/' ),
			'sql data' => array( 'sql', '/^SELECT \* FROM `[^`]*aips_history`$/' ),
			'json data' => array( 'json', '/^SELECT \* FROM `[^`]*aips_history`$/' ),
		);
	}

	public function test_schema_failure_is_not_masked_by_a_later_successful_statement() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_history';
		$wpdb->query( "ALTER TABLE `$table` MODIFY COLUMN post_type varchar(49) DEFAULT NULL, MODIFY COLUMN status varchar(49) DEFAULT 'pending'" );
		AIPS_Config::get_instance()->set_option( 'aips_db_version', '3.7.15' );
		$fail = static function( $sql ) {
			return preg_match( '/^ALTER TABLE .*aips_history.*(?:CHANGE|MODIFY).*post_type/s', $sql ) ? 'INVALID first schema ALTER fixture' : $sql;
		};
		$previous = $wpdb->suppress_errors( true );
		add_filter( 'query', $fail );
		try {
			$this->assertWPError( AIPS_DB_Migrations::check_and_run() );
			$this->assertSame( '3.7.15', get_option( 'aips_db_version' ) );
			// dbDelta continued to the independent status change after the failed ALTER.
			$this->assertSame( 'varchar(50)', $wpdb->get_row( "SHOW COLUMNS FROM `$table` LIKE 'status'" )->Type );
		} finally {
			remove_filter( 'query', $fail );
			$wpdb->suppress_errors( $previous );
			AIPS_DB_Migrations::check_and_run();
		}
	}

	public function test_automatic_upgrade_failure_notifies_the_administrator() {
		global $wpdb;
		$previous_preferences = get_option( 'aips_notification_preferences', null );
		AIPS_Config::get_instance()->set_option( 'aips_notification_preferences', array( 'system_error' => 'db' ) );
		AIPS_Config::get_instance()->set_option( 'aips_db_version', '3.7.17' );
		$fail = static function( $sql ) {
			return preg_match( '/^UPDATE .*aips_authors.*SET topic_auto_approval_min_relevance/s', $sql ) ? 'INVALID upgrade notification fixture' : $sql;
		};
		$previous_errors = $wpdb->suppress_errors( true );
		add_filter( 'query', $fail );
		try {
			AI_Post_Scheduler::get_instance()->check_upgrades();
			$this->assertSame( '3.7.17', get_option( 'aips_db_version' ) );
			$this->assertSame( 'Database upgrade failed', $wpdb->get_var( "SELECT title FROM {$wpdb->prefix}aips_notifications WHERE dedupe_key = 'db_upgrade_failed_3.7.17' ORDER BY id DESC LIMIT 1" ) );
		} finally {
			remove_filter( 'query', $fail );
			$wpdb->suppress_errors( $previous_errors );
			$wpdb->delete( $wpdb->prefix . 'aips_notifications', array( 'dedupe_key' => 'db_upgrade_failed_3.7.17' ) );
			if ( null === $previous_preferences ) {
				delete_option( 'aips_notification_preferences' );
			} else {
				AIPS_Config::get_instance()->set_option( 'aips_notification_preferences', $previous_preferences );
			}
			AIPS_DB_Migrations::check_and_run();
		}
	}

	private function assert_no_table_probes( array $queries ): void {
		foreach ( $queries as $sql ) {
			$this->assertDoesNotMatchRegularExpression( '/^\s*SHOW\s+(?:FULL\s+)?TABLES\b/i', $sql );
			$this->assertStringNotContainsString( 'aips_post_embeddings', $sql );
		}
	}
}
