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
	 * check_and_run() with a pre-2.3.1 version must add the composite indexes
	 * to aips_notifications via the versioned migrate_to_2_3_1() migration.
	 */
	public function test_migrate_to_2_3_1_adds_composite_indexes() {
		// Simulate an existing install at a pre-2.3.1 version that has the
		// notifications table but lacks the composite indexes.
		AIPS_DB_Manager::install_tables();

		global $wpdb;
		$table = $wpdb->prefix . 'aips_notifications';

		// Drop the composite indexes to simulate the pre-2.3.1 state.
		$wpdb->query( "ALTER TABLE `{$table}` DROP INDEX IF EXISTS is_read_created_at" );
		$wpdb->query( "ALTER TABLE `{$table}` DROP INDEX IF EXISTS dedupe_key_created_at" );

		// Confirm the indexes are gone before the migration runs.
		$index_names_before = $wpdb->get_col( "SHOW INDEX FROM `{$table}`", 2 );
		$this->assertNotContains( 'is_read_created_at', $index_names_before, 'Pre-condition: index should be absent before migration' );
		$this->assertNotContains( 'dedupe_key_created_at', $index_names_before, 'Pre-condition: index should be absent before migration' );

		// Trigger upgrade from a version older than 2.3.1.
		AIPS_Config::get_instance()->set_option( 'aips_db_version', '2.3.0' );
		AIPS_DB_Migrations::check_and_run();

		$index_names_after = $wpdb->get_col( "SHOW INDEX FROM `{$table}`", 2 );
		$this->assertContains(
			'is_read_created_at',
			$index_names_after,
			'migrate_to_2_3_1 must add is_read_created_at index'
		);
		$this->assertContains(
			'dedupe_key_created_at',
			$index_names_after,
			'migrate_to_2_3_1 must add dedupe_key_created_at index'
		);
	}

	/**
	 * check_and_run() must trigger an upgrade and save the new version when the
	 * stored version is older than the current plugin version.
	 */
	public function test_check_and_run_saves_version_after_upgrade() {
		AIPS_Config::get_instance()->set_option( 'aips_db_version', '0.0.1' );

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

	/**
	 * Legacy partial-generation keys should be renamed and the generated-post
	 * marker should be backfilled without duplicating rows on re-run.
	 */
	public function test_migrate_to_3_0_1_normalizes_partial_generation_meta() {
		AIPS_DB_Manager::install_tables();

		$post_id = self::factory()->post->create( array(
			'post_title' => 'Legacy Partial Generation Post',
		) );

		add_post_meta( $post_id, 'aips_post_generation_component_statuses', wp_json_encode( array( 'post_title' => true ) ) );
		add_post_meta( $post_id, 'aips_post_generation_incomplete', 'true' );
		add_post_meta( $post_id, AIPS_Post_Manager::META_GENERATION_COMPONENT_STATUSES, wp_json_encode( array( 'post_title' => false ) ) );

		AIPS_Config::get_instance()->set_option( 'aips_db_version', '3.0.0' );
		AIPS_DB_Migrations::check_and_run();
		AIPS_Config::get_instance()->set_option( 'aips_db_version', '3.0.0' );
		AIPS_DB_Migrations::check_and_run();

		$this->assertFalse( metadata_exists( 'post', $post_id, 'aips_post_generation_component_statuses' ) );
		$this->assertFalse( metadata_exists( 'post', $post_id, 'aips_post_generation_incomplete' ) );
		$this->assertSame(
			wp_json_encode( array( 'post_title' => false ) ),
			get_post_meta( $post_id, AIPS_Post_Manager::META_GENERATION_COMPONENT_STATUSES, true )
		);
		$this->assertSame( 'true', get_post_meta( $post_id, AIPS_Post_Manager::META_GENERATION_INCOMPLETE, true ) );
		$this->assertSame( '1', get_post_meta( $post_id, AIPS_Post_Manager::META_GENERATED_POST, true ) );

		global $wpdb;
		$generated_marker_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$wpdb->postmeta}
				WHERE post_id = %d
				AND meta_key = %s",
				$post_id,
				AIPS_Post_Manager::META_GENERATED_POST
			)
		);
		$this->assertSame( 1, $generated_marker_count );
	}

	/**
	 * The generated-post marker should also backfill for posts referenced only
	 * by generation history.
	 */
	public function test_migrate_to_3_0_1_backfills_generated_post_marker_from_history() {
		AIPS_DB_Manager::install_tables();

		$post_id = self::factory()->post->create( array(
			'post_title' => 'History Only Generated Post',
		) );

		$history_repository = new AIPS_History_Repository();
		$history_id         = $history_repository->create( array(
			'post_id' => $post_id,
			'status'  => 'completed',
		) );

		$this->assertNotFalse( $history_id );
		$this->assertFalse( metadata_exists( 'post', $post_id, AIPS_Post_Manager::META_GENERATED_POST ) );

		AIPS_Config::get_instance()->set_option( 'aips_db_version', '3.0.0' );
		AIPS_DB_Migrations::check_and_run();
		clean_post_cache( $post_id );

		$this->assertSame( '1', get_post_meta( $post_id, AIPS_Post_Manager::META_GENERATED_POST, true ) );
	}

	/**
	 * migrate_to_3_4_0() must backfill aips_history.post_type from the linked
	 * post's actual post type for rows created before the column existed.
	 */
	public function test_migrate_to_3_4_0_backfills_post_type_from_linked_post() {
		AIPS_DB_Manager::install_tables();

		$post_id = self::factory()->post->create( array(
			'post_title' => 'Legacy History Row Post',
			'post_type'  => 'page',
		) );

		$history_repository = new AIPS_History_Repository();
		$history_id         = $history_repository->create( array(
			'post_id' => $post_id,
			'status'  => 'completed',
			// post_type intentionally omitted, simulating a pre-3.4.0 row.
		) );

		$before = $history_repository->get_by_id( $history_id );
		$this->assertNull( $before->post_type );

		AIPS_Config::get_instance()->set_option( 'aips_db_version', '3.3.0' );
		AIPS_DB_Migrations::check_and_run();

		$after = $history_repository->get_by_id( $history_id );
		$this->assertSame( 'page', $after->post_type );
	}

	/**
	 * migrate_to_3_4_0() must not overwrite a post_type that's already set
	 * (e.g. by the write path on a more recent row), and must be a safe
	 * no-op to run twice.
	 */
	public function test_migrate_to_3_4_0_does_not_overwrite_existing_post_type() {
		AIPS_DB_Manager::install_tables();

		$post_id = self::factory()->post->create( array(
			'post_title' => 'Already Backfilled Post',
			'post_type'  => 'page',
		) );

		$history_repository = new AIPS_History_Repository();
		$history_id         = $history_repository->create( array(
			'post_id'   => $post_id,
			'status'    => 'completed',
			'post_type' => 'post', // Deliberately mismatched from the post's real type.
		) );

		AIPS_Config::get_instance()->set_option( 'aips_db_version', '3.3.0' );
		AIPS_DB_Migrations::check_and_run();
		AIPS_Config::get_instance()->set_option( 'aips_db_version', '3.3.0' );
		AIPS_DB_Migrations::check_and_run();

		$after = $history_repository->get_by_id( $history_id );
		$this->assertSame( 'post', $after->post_type, 'Migration must only backfill NULL post_type values, never overwrite an existing one.' );
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
	}


	/**
	 * migrate_to_3_7_14() must ensure post_generation_lock exists on aips_authors
	 * and claimed_at exists on aips_author_topics.
	 */
	public function test_migrate_to_3_7_14_adds_lock_and_claim_columns() {
		AIPS_DB_Manager::install_tables();

		AIPS_Config::get_instance()->set_option( 'aips_db_version', '3.7.13' );
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
	 * migrate_to_3_7_15() must fill a NULL post_type on generation-run rows from the
	 * post, leave event rows alone and never overwrite an existing value.
	 */
	public function test_migrate_to_3_7_15_backfills_post_type_on_run_rows_only() {
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

		AIPS_Config::get_instance()->set_option( 'aips_db_version', '3.7.14' );
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

	/**
	 * Invoke the private migrate_to_3_7_21() on a fresh instance.
	 *
	 * @return bool
	 */
	private function run_migrate_3_7_21() {
		$migrations = new AIPS_DB_Migrations();
		$method     = new ReflectionMethod( $migrations, 'migrate_to_3_7_21' );
		$method->setAccessible( true );
		return (bool) $method->invoke( $migrations );
	}

	/**
	 * migrate_to_3_7_21() must drop the redundant single-column indexes that are
	 * left-prefixes of a composite and add the author_id_status_score covering
	 * index, so the per-author topic claim no longer filesorts.
	 */
	public function test_migrate_to_3_7_21_consolidates_indexes() {
		AIPS_DB_Manager::install_tables();

		global $wpdb;
		$history = $wpdb->prefix . 'aips_history';
		$topics  = $wpdb->prefix . 'aips_author_topics';

		// Recreate the pre-3.7.21 index layout.
		$wpdb->query( "ALTER TABLE `{$history}` ADD KEY status (status)" );
		$wpdb->query( "ALTER TABLE `{$history}` ADD KEY template_id (template_id)" );
		$wpdb->query( "ALTER TABLE `{$history}` ADD KEY post_id (post_id)" );
		$wpdb->query( "ALTER TABLE `{$topics}` DROP INDEX IF EXISTS author_id_status_score" );
		$wpdb->query( "ALTER TABLE `{$topics}` ADD KEY author_id (author_id)" );
		$wpdb->query( "ALTER TABLE `{$topics}` ADD KEY status (status)" );
		$wpdb->query( "ALTER TABLE `{$topics}` ADD KEY author_id_status (author_id, status)" );

		$this->assertTrue( $this->run_migrate_3_7_21() );

		$history_indexes = $wpdb->get_col( "SHOW INDEX FROM `{$history}`", 2 );
		$this->assertNotContains( 'status', $history_indexes, 'Redundant aips_history.status index should be dropped' );
		$this->assertNotContains( 'template_id', $history_indexes, 'Redundant aips_history.template_id index should be dropped' );
		$this->assertNotContains( 'post_id', $history_indexes, 'Redundant aips_history.post_id index should be dropped' );
		$this->assertContains( 'status_created', $history_indexes, 'Covering composite status_created must remain' );
		$this->assertContains( 'post_status', $history_indexes, 'Covering composite post_status must remain' );

		$topic_indexes = $wpdb->get_col( "SHOW INDEX FROM `{$topics}`", 2 );
		$this->assertContains( 'author_id_status_score', $topic_indexes, 'author_id_status_score covering index must be added' );
		$this->assertNotContains( 'author_id', $topic_indexes, 'Redundant author_id index should be dropped' );
		$this->assertNotContains( 'author_id_status', $topic_indexes, 'Redundant author_id_status index should be dropped' );
		$this->assertNotContains( 'status', $topic_indexes, 'Redundant status index should be dropped' );
		$this->assertContains( 'status_score_reviewed', $topic_indexes, 'status_score_reviewed must remain' );
	}

	/**
	 * migrate_to_3_7_21() must delete history_log rows whose parent history row
	 * no longer exists, and leave rows with a live parent untouched.
	 */
	public function test_migrate_to_3_7_21_deletes_orphaned_history_log() {
		AIPS_DB_Manager::install_tables();

		global $wpdb;
		$history = $wpdb->prefix . 'aips_history';
		$log     = $wpdb->prefix . 'aips_history_log';

		$wpdb->insert(
			$history,
			array( 'status' => 'completed', 'record_kind' => 'event', 'created_at' => 1700000000 ),
			array( '%s', '%s', '%d' )
		);
		$parent_id = (int) $wpdb->insert_id;

		$wpdb->insert(
			$log,
			array( 'history_id' => $parent_id, 'timestamp' => 1700000000 ),
			array( '%d', '%d' )
		);
		$live_log_id = (int) $wpdb->insert_id;

		$wpdb->insert(
			$log,
			array( 'history_id' => 999999999, 'timestamp' => 1700000000 ),
			array( '%d', '%d' )
		);
		$orphan_log_id = (int) $wpdb->insert_id;

		$this->assertTrue( $this->run_migrate_3_7_21() );

		$this->assertNull(
			$wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$log}` WHERE id = %d", $orphan_log_id ) ),
			'Orphaned history_log row should be deleted'
		);
		$this->assertSame(
			(string) $live_log_id,
			$wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$log}` WHERE id = %d", $live_log_id ) ),
			'history_log row with a live parent must be kept'
		);

		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$log}` WHERE id = %d", $live_log_id ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$history}` WHERE id = %d", $parent_id ) );
	}

	/**
	 * migrate_to_3_7_21() must strip the cached embedding vector from existing
	 * author_topics metadata while preserving every other metadata field.
	 */
	public function test_migrate_to_3_7_21_strips_metadata_embeddings() {
		AIPS_DB_Manager::install_tables();

		global $wpdb;
		$table = $wpdb->prefix . 'aips_author_topics';

		$wpdb->insert(
			$table,
			array(
				'author_id'    => 1,
				'topic_title'  => 'Bloated topic',
				'status'       => 'approved',
				'score'        => 90,
				'metadata'     => wp_json_encode( array(
					'embedding'           => array_fill( 0, 1536, 0.1234 ),
					'potential_duplicate' => false,
					'auto_approval_rule'  => 'similarity',
				) ),
				'generated_at' => 1700000000,
			),
			array( '%d', '%s', '%s', '%d', '%s', '%d' )
		);
		$topic_id = (int) $wpdb->insert_id;

		$this->assertTrue( $this->run_migrate_3_7_21() );

		$meta = json_decode(
			(string) $wpdb->get_var( $wpdb->prepare( "SELECT metadata FROM `{$table}` WHERE id = %d", $topic_id ) ),
			true
		);
		$this->assertArrayNotHasKey( 'embedding', $meta, 'embedding must be stripped from metadata' );
		$this->assertArrayHasKey( 'potential_duplicate', $meta, 'other metadata fields must be preserved' );
		$this->assertSame( 'similarity', $meta['auto_approval_rule'], 'other metadata values must be preserved' );

		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE id = %d", $topic_id ) );
	}

	/**
	 * migrate_to_3_7_21() must clear the aips_embeddings cache group from
	 * aips_cache while leaving other cache groups intact.
	 */
	public function test_migrate_to_3_7_21_clears_embeddings_cache() {
		AIPS_DB_Manager::install_tables();

		global $wpdb;
		$cache = $wpdb->prefix . 'aips_cache';

		$wpdb->insert(
			$cache,
			array( 'cache_key' => 'vec_post_1', 'cache_group' => 'aips_embeddings', 'value' => '[0.1,0.2]', 'expires_at' => 0, 'updated_at' => 1700000000 ),
			array( '%s', '%s', '%s', '%d', '%d' )
		);
		$wpdb->insert(
			$cache,
			array( 'cache_key' => 'keepme', 'cache_group' => 'aips_schedules', 'value' => 'x', 'expires_at' => 0, 'updated_at' => 1700000000 ),
			array( '%s', '%s', '%s', '%d', '%d' )
		);

		$this->assertTrue( $this->run_migrate_3_7_21() );

		$this->assertSame(
			'0',
			$wpdb->get_var( "SELECT COUNT(*) FROM `{$cache}` WHERE cache_group = 'aips_embeddings'" ),
			'embeddings cache group should be cleared'
		);
		$this->assertSame(
			'1',
			$wpdb->get_var( "SELECT COUNT(*) FROM `{$cache}` WHERE cache_group = 'aips_schedules'" ),
			'other cache groups must be left intact'
		);

		$wpdb->query( "DELETE FROM `{$cache}` WHERE cache_key IN ('vec_post_1','keepme')" );
	}

	/**
	 * migrate_to_3_7_21() must return true and be a safe, idempotent no-op on a
	 * second run (indexes already consolidated, no embeddings left to strip).
	 */
	public function test_migrate_to_3_7_21_is_idempotent() {
		AIPS_DB_Manager::install_tables();

		$this->assertTrue( $this->run_migrate_3_7_21() );
		$this->assertTrue( $this->run_migrate_3_7_21() );

		global $wpdb;
		$topics        = $wpdb->prefix . 'aips_author_topics';
		$topic_indexes = $wpdb->get_col( "SHOW INDEX FROM `{$topics}`", 2 );
		$this->assertContains( 'author_id_status_score', $topic_indexes );
	}
}
