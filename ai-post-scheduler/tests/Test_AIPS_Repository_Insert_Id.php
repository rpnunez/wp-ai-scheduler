<?php
/**
 * Regression tests: repository create() must return the new row's own ID.
 *
 * create() invalidates the repository cache after inserting. Invalidation
 * writes to other tables (cache, cache index), which overwrites
 * $wpdb->insert_id, so create() used to return another table's row ID.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Repository_Insert_Id extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		// The cache index is one of the writers that clobbered insert_id.
		update_option( 'aips_cache_monitor_index_enabled', '1' );
	}

	/**
	 * Assert that $id is the primary key of the row in $table matching $where.
	 *
	 * @param int    $id    Returned ID.
	 * @param string $table Unprefixed table name.
	 * @param string $col   Column to match.
	 * @param string $value Value to match.
	 */
	private function assert_id_matches_row( $id, $table, $col, $value ) {
		global $wpdb;
		$actual = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$wpdb->prefix}{$table} WHERE {$col} = %s ORDER BY id DESC LIMIT 1", $value )
		);

		$this->assertGreaterThan( 0, $actual );
		$this->assertSame( $actual, (int) $id );
	}

	public function test_template_create_returns_its_own_id() {
		$name = 'Insert Id Template ' . uniqid();
		$id   = ( new AIPS_Template_Repository() )->create(
			array(
				'name'            => $name,
				'prompt_template' => 'Write about {{topic}}',
				'post_status'     => 'draft',
				'post_category'   => 1,
				'is_active'       => 1,
			)
		);

		$this->assert_id_matches_row( $id, 'aips_templates', 'name', $name );
	}

	public function test_schedule_create_returns_its_own_id() {
		$topic = 'Insert Id Topic ' . uniqid();
		$id    = ( new AIPS_Schedule_Repository() )->create(
			array(
				'template_id' => 1,
				'frequency'   => 'daily',
				'next_run'    => AIPS_DateTime::now()->timestamp() + HOUR_IN_SECONDS,
				'is_active'   => 1,
				'topic'       => $topic,
			)
		);

		$this->assert_id_matches_row( $id, 'aips_schedule', 'topic', $topic );
	}

	public function test_author_create_returns_its_own_id() {
		$name = 'Insert Id Author ' . uniqid();
		$id   = ( new AIPS_Authors_Repository() )->create(
			array(
				'name'        => $name,
				'field_niche' => 'Testing',
				'is_active'   => 1,
			)
		);

		$this->assert_id_matches_row( $id, 'aips_authors', 'name', $name );
	}
}
