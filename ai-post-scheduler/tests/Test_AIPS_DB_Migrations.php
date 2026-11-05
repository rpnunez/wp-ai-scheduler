<?php
/**
 * Tests for database migration version tracking.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_DB_Migrations extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		if (!defined('ABSPATH') || !file_exists(ABSPATH . 'wp-admin/includes/upgrade.php')) {
			$this->markTestSkipped('Database migration tests require the full WordPress test library.');
		}

		AIPS_Config::get_instance()->set_option('aips_db_version', null);
		delete_option('aips_db_version');
	}

	public function tearDown(): void {
		AIPS_Config::get_instance()->set_option('aips_db_version', null);
		delete_option('aips_db_version');
		parent::tearDown();
	}

	/**
	 * install_tables() must persist aips_db_version so the schema level is
	 * always recorded, regardless of the call site.
	 */
	public function test_install_tables_saves_db_version() {
		AIPS_DB_Manager::install_tables();

		$saved = get_option('aips_db_version');
		$this->assertSame(AIPS_VERSION, $saved, 'aips_db_version should equal AIPS_VERSION after install_tables()');
	}

	/**
	 * install_tables() must create the composite indexes on aips_notifications
	 * so that get_unread() and was_recently_sent() can use covering scans.
	 */
	public function test_install_tables_creates_notifications_composite_indexes() {
		AIPS_DB_Manager::install_tables();

		global $wpdb;
		$table = $wpdb->prefix . 'aips_notifications';

		$index_names = $wpdb->get_col( "SHOW INDEX FROM `{$table}`", 2 );

		$this->assertContains(
			'is_read_created_at',
			$index_names,
			'Composite index is_read_created_at must exist on aips_notifications'
		);
		$this->assertContains(
			'dedupe_key_created_at',
			$index_names,
			'Composite index dedupe_key_created_at must exist on aips_notifications'
		);
	}

	/**
	 * check_and_run() must trigger an upgrade and save the new version when the
	 * stored version is older than the current plugin version.
	 */
	public function test_check_and_run_saves_version_after_upgrade() {
		AIPS_Config::get_instance()->set_option( 'aips_db_version', '3.7.15' );

		AIPS_DB_Migrations::check_and_run();

		$saved = get_option('aips_db_version');
		$this->assertSame(AIPS_VERSION, $saved, 'aips_db_version should equal AIPS_VERSION after check_and_run() upgrade');
	}

	/**
	 * check_and_run() must not change the stored version when it already matches
	 * the current plugin version.
	 */
	public function test_check_and_run_skips_when_version_matches() {
		AIPS_Config::get_instance()->set_option( 'aips_db_version', AIPS_VERSION );

		AIPS_DB_Migrations::check_and_run();

		$saved = get_option('aips_db_version');
		$this->assertSame(AIPS_VERSION, $saved, 'aips_db_version should remain unchanged when versions match');
	}

	public function test_sources_nullable_migration_failure_preserves_version_and_retries() {
		global $wpdb;
		AIPS_DB_Manager::install_tables();
		$table = $wpdb->prefix . 'aips_sources';
		$wpdb->query( "UPDATE `$table` SET next_fetch_at = 0 WHERE next_fetch_at IS NULL" );
		$wpdb->query( "ALTER TABLE `$table` MODIFY COLUMN next_fetch_at bigint(20) unsigned NOT NULL DEFAULT 0" );
		AIPS_Config::get_instance()->set_option( 'aips_db_version', '3.7.18' );
		$fail_alter = static function( $query ) use ( $table ) {
			return str_starts_with( $query, "ALTER TABLE `$table` MODIFY COLUMN `next_fetch_at`" ) ? 'INVALID nullable migration fixture' : $query;
		};
		$previous = $wpdb->suppress_errors( true );
		add_filter( 'query', $fail_alter );
		try {
			AIPS_DB_Migrations::check_and_run();
			$this->assertSame( '3.7.18', get_option( 'aips_db_version' ) );
			$this->assertSame( '3.7.18', AIPS_Config::get_instance()->get_option( 'aips_db_version' ) );
		} finally {
			remove_filter( 'query', $fail_alter );
			$wpdb->suppress_errors( $previous );
			// Recover schema even if the assertions above fail.
			AIPS_Config::get_instance()->set_option( 'aips_db_version', '3.7.18' );
			AIPS_DB_Migrations::check_and_run();
		}
		$this->assertSame( AIPS_VERSION, get_option( 'aips_db_version' ) );
		$this->assertSame( 'YES', $wpdb->get_row( "SHOW COLUMNS FROM `$table` LIKE 'next_fetch_at'" )->Null );
	}

	public function test_sources_attempt_migration_is_idempotent_and_preserves_legacy_due_time() {
		global $wpdb;
		AIPS_DB_Manager::install_tables();
		$table = $wpdb->prefix . 'aips_sources';
		$wpdb->query( "UPDATE `$table` SET next_fetch_at = 0 WHERE next_fetch_at IS NULL" );
		$wpdb->query( "ALTER TABLE `$table` MODIFY COLUMN next_fetch_at bigint(20) unsigned NOT NULL DEFAULT 0" );
		foreach ( array( 'last_fetch_attempt_at', 'last_fetch_status', 'last_fetch_error' ) as $column ) {
			$wpdb->query( "ALTER TABLE `$table` DROP COLUMN `$column`" );
		}
		$wpdb->insert( $table, array( 'url' => 'https://example.com/migration', 'fetch_interval' => 'hourly', 'next_fetch_at' => 123456 ) );
		$id = (int) $wpdb->insert_id;
		try {
			$migration = new ReflectionMethod( AIPS_DB_Migrations::class, 'migrate_to_3_7_19' );
			$migration->setAccessible( true );
			$instance = new AIPS_DB_Migrations();
			$this->assertTrue( $migration->invoke( $instance ) );
			$this->assertTrue( $migration->invoke( $instance ) );
			$this->assertSame( '', $wpdb->last_error );
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$table` WHERE id = %d", $id ) );
			$this->assertSame( 123456, (int) $row->next_fetch_at );
			$this->assertNull( $row->last_fetch_attempt_at );
			$this->assertNull( $row->last_fetch_status );
			$this->assertNull( $row->last_fetch_error );
			$this->assertNotFalse( $wpdb->update( $table, array( 'next_fetch_at' => null ), array( 'id' => $id ) ) );
			$this->assertNull( $wpdb->get_var( $wpdb->prepare( "SELECT next_fetch_at FROM `$table` WHERE id = %d", $id ) ) );
		} finally {
			$wpdb->delete( $table, array( 'id' => $id ) );
		}
	}


	/**
	 * Current schema must ensure post_generation_lock exists on aips_authors
	 * and claimed_at exists on aips_author_topics.
	 */
	public function test_current_schema_has_lock_and_claim_columns() {
		AIPS_DB_Manager::install_tables();

		AIPS_Config::get_instance()->set_option( 'aips_db_version', '3.7.15' );
		AIPS_DB_Migrations::check_and_run();

		global $wpdb;
		$authors_table = $wpdb->prefix . 'aips_authors';
		$topics_table  = $wpdb->prefix . 'aips_author_topics';

		$authors_cols = $wpdb->get_col( "SHOW COLUMNS FROM `{$authors_table}`" );
		$topics_cols  = $wpdb->get_col( "SHOW COLUMNS FROM `{$topics_table}`" );

		$this->assertContains( 'post_generation_lock', $authors_cols );
		$this->assertContains( 'claimed_at', $topics_cols );
	}

	/**
	 * The retained History backfill must fill a NULL post_type on generation-run rows from the
	 * post, leave event rows alone and never overwrite an existing value.
	 */
	public function test_retained_history_backfill_preserves_event_rows_and_existing_types() {
		AIPS_DB_Manager::install_tables();

		$post_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$history_repository = new AIPS_History_Repository();
		$run_id             = $history_repository->create( array(
			'post_id'         => $post_id,
			'status'          => 'completed',
			'creation_method' => 'scheduled',
		) );
		$event_id           = $history_repository->create( array(
			'post_id'         => $post_id,
			'status'          => 'completed',
			'creation_method' => 'topic_post_generation',
		) );
		$set_id             = $history_repository->create( array(
			'post_id'         => $post_id,
			'status'          => 'completed',
			'creation_method' => 'manual',
			'post_type'       => 'post',
		) );

		AIPS_Config::get_instance()->set_option( 'aips_db_version', '3.7.15' );
		AIPS_DB_Migrations::check_and_run();

		$this->assertSame( 'page', $history_repository->get_by_id( $run_id )->post_type );
		$this->assertNull( $history_repository->get_by_id( $event_id )->post_type );
		$this->assertSame( 'post', $history_repository->get_by_id( $set_id )->post_type );
	}

	/**
	 * migrate_to_3_7_16() must move a status the old sync wrote to a post's
	 * content_indexing row back onto its generation run.
	 */
	public function test_migrate_to_3_7_16_repairs_statuses_written_to_the_wrong_row() {
		AIPS_DB_Manager::install_tables();

		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, AIPS_Post_Manager::META_GENERATION_INCOMPLETE, 'true' );

		$history_repository = new AIPS_History_Repository();
		$run_id             = $history_repository->create( array(
			'post_id'         => $post_id,
			'status'          => 'completed',
			'creation_method' => 'scheduled',
		) );
		$indexing_id        = $history_repository->create( array(
			'post_id'         => $post_id,
			'status'          => 'partial',
			'creation_method' => 'content_indexing',
		) );

		try {
			AIPS_Config::get_instance()->set_option( 'aips_db_version', '3.7.15' );
			AIPS_DB_Migrations::check_and_run();

			$this->assertSame( 'partial', $history_repository->get_by_id( $run_id )->status );
			$this->assertSame( 'completed', $history_repository->get_by_id( $indexing_id )->status );
		} finally {
			// install_tables() runs DDL, which commits, so the test case's rollback will not
			// remove these rows.
			global $wpdb;
			$wpdb->delete( $wpdb->prefix . 'aips_history', array( 'id' => $run_id ), array( '%d' ) );
			$wpdb->delete( $wpdb->prefix . 'aips_history', array( 'id' => $indexing_id ), array( '%d' ) );
			wp_delete_post( $post_id, true );
		}
	}

	/**
	 * migrate_to_3_7_17() must classify existing rows, then re-run the post_type backfill
	 * and the status repair, which both select rows by record_kind.
	 */
	public function test_migrate_to_3_7_17_classifies_rows_and_reruns_the_kind_based_repairs() {
		global $wpdb;

		AIPS_DB_Manager::install_tables();

		$post_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_post_meta( $post_id, AIPS_Post_Manager::META_GENERATION_INCOMPLETE, 'true' );

		$table = $wpdb->prefix . 'aips_history';
		$ids   = array();
		foreach ( array(
			'run'      => array( 'scheduled', 'completed' ),
			'indexing' => array( 'content_indexing', 'partial' ),
			'wrapper'  => array( 'topic_post_generation', 'completed' ),
		) as $key => $row ) {
			// Rows as an older install has them: record_kind left at the column default.
			$wpdb->insert( $table, array(
				'post_id'         => $post_id,
				'creation_method' => $row[0],
				'status'          => $row[1],
				'created_at'      => time(),
				'completed_at'    => time(),
			) );
			$ids[ $key ] = (int) $wpdb->insert_id;
		}

		try {
			AIPS_Config::get_instance()->set_option( 'aips_db_version', '3.7.16' );
			AIPS_DB_Migrations::check_and_run();

			$history = new AIPS_History_Repository();
			$this->assertSame( 'generation', $history->get_by_id( $ids['run'] )->record_kind );
			$this->assertSame( 'job', $history->get_by_id( $ids['indexing'] )->record_kind );
			$this->assertSame( 'event', $history->get_by_id( $ids['wrapper'] )->record_kind );

			// The repairs that select rows by kind ran after the classification.
			$this->assertSame( 'page', $history->get_by_id( $ids['run'] )->post_type );
			$this->assertSame( 'partial', $history->get_by_id( $ids['run'] )->status );
			$this->assertSame( 'completed', $history->get_by_id( $ids['indexing'] )->status );
		} finally {
			// install_tables() runs DDL, which commits, so the test case's rollback will not
			// remove these rows.
			foreach ( $ids as $id ) {
				$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
			}
			wp_delete_post( $post_id, true );
		}
	}

	/**
	 * Migration 3.7.18 backfills min_relevance for similarity authors, switches
	 * manual authors to inherit, leaves explicit modes alone, renames the stored
	 * `reject` fallback and deletes the hidden option family.
	 */
	public function test_migration_3_7_18_consolidates_author_auto_approval() {
		global $wpdb;
		AIPS_DB_Manager::install_tables();
		$table = $wpdb->prefix . 'aips_authors';

		$base = array('name' => 'M', 'field_niche' => 'n', 'topic_generation_frequency' => 'weekly', 'post_generation_frequency' => 'daily');
		$ids  = array();
		foreach (array(
			'manual'     => array('topic_auto_approval_mode' => 'manual', 'topic_auto_approval_min_score' => 70),
			'similarity' => array('topic_auto_approval_mode' => 'similarity', 'topic_auto_approval_min_score' => 55),
			'score'      => array('topic_auto_approval_mode' => 'score', 'topic_auto_approval_min_score' => 88),
			'all'        => array('topic_auto_approval_mode' => 'all'),
		) as $key => $cols) {
			$wpdb->insert($table, array_merge($base, $cols, array('created_at' => 1, 'updated_at' => 1)));
			$ids[$key] = (int) $wpdb->insert_id;
		}

		update_option('aips_author_topic_auto_approval_fallback', 'reject');
		update_option('aips_author_topic_auto_approval_enabled', true);
		update_option('aips_author_topic_approval_mode', 'embeddings');

		$method = new ReflectionMethod('AIPS_DB_Migrations', 'migrate_to_3_7_18');
		$method->setAccessible(true);
		$method->invoke(new AIPS_DB_Migrations());

		$row = function ($key) use ($wpdb, $table, $ids) {
			return $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE id = %d", $ids[$key]));
		};

		$this->assertSame('inherit', $row('manual')->topic_auto_approval_mode);
		$this->assertSame('similarity', $row('similarity')->topic_auto_approval_mode);
		$this->assertSame(55, (int) $row('similarity')->topic_auto_approval_min_relevance);
		$this->assertSame('score', $row('score')->topic_auto_approval_mode);
		$this->assertSame(88, (int) $row('score')->topic_auto_approval_min_score);
		$this->assertSame(65, (int) $row('score')->topic_auto_approval_min_relevance);
		$this->assertSame('all', $row('all')->topic_auto_approval_mode);

		$this->assertSame('rejected', get_option('aips_author_topic_auto_approval_fallback'));
		$this->assertFalse(get_option('aips_author_topic_auto_approval_enabled', false));
		$this->assertFalse(get_option('aips_author_topic_approval_mode', false));

		$wpdb->query("DELETE FROM `{$table}` WHERE id IN (" . implode(',', $ids) . ')');
		delete_option('aips_author_topic_auto_approval_fallback');
	}
}
