<?php
/**
 * Tests for date/time DB repair.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Date_Time_DB_Repair extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();
	}

	public function test_repair_backfills_missing_next_run_for_active_template_schedule() {
		global $wpdb;

		$template_table = $wpdb->prefix . 'aips_templates';
		$schedule_table = $wpdb->prefix . 'aips_schedule';

		$wpdb->insert(
			$template_table,
			array(
				'name'            => 'Date Repair Template',
				'prompt_template' => 'Write about repaired schedules',
				'is_active'       => 1,
				'created_at'      => AIPS_DateTime::now()->timestamp(),
				'updated_at'      => AIPS_DateTime::now()->timestamp(),
			),
			array( '%s', '%s', '%d', '%d', '%d' )
		);

		$template_id = (int) $wpdb->insert_id;
		$created_at  = AIPS_DateTime::now()->addSeconds( -3600 )->timestamp();

		$wpdb->insert(
			$schedule_table,
			array(
				'template_id' => $template_id,
				'frequency'   => 'daily',
				'next_run'    => 0,
				'last_run'    => 0,
				'is_active'   => 1,
				'created_at'  => $created_at,
			),
			array( '%d', '%s', '%d', '%d', '%d', '%d' )
		);

		$schedule_id = (int) $wpdb->insert_id;

		$summary = ( new AIPS_Date_Time_DB_Repair() )->run();
		$row     = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT next_run FROM {$schedule_table} WHERE id = %d",
				$schedule_id
			)
		);

		$this->assertNotNull( $row );
		$this->assertGreaterThan( 0, (int) $row->next_run );
		$this->assertGreaterThanOrEqual( 1, $summary['fixed_schedule_next_runs'] );
	}

	public function test_repair_backfills_missing_next_run_for_active_author_schedules() {
		global $wpdb;

		$authors_table = $wpdb->prefix . 'aips_authors';
		$created_at    = AIPS_DateTime::now()->addSeconds( -7200 )->timestamp();

		$wpdb->insert(
			$authors_table,
			array(
				'name'                       => 'Date Repair Author',
				'field_niche'                => 'Testing',
				'is_active'                  => 1,
				'topic_generation_frequency' => 'daily',
				'topic_generation_is_active' => 1,
				'topic_generation_next_run'  => 0,
				'topic_generation_last_run'  => 0,
				'post_generation_frequency'  => 'weekly',
				'post_generation_is_active'  => 1,
				'post_generation_next_run'   => 0,
				'post_generation_last_run'   => 0,
				'created_at'                 => $created_at,
				'updated_at'                 => $created_at,
			),
			array( '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%s', '%d', '%d', '%d', '%d', '%d' )
		);

		$author_id = (int) $wpdb->insert_id;

		$summary = ( new AIPS_Date_Time_DB_Repair() )->run();
		$row     = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT topic_generation_next_run, post_generation_next_run FROM {$authors_table} WHERE id = %d",
				$author_id
			)
		);

		$this->assertNotNull( $row );
		$this->assertGreaterThan( 0, (int) $row->topic_generation_next_run );
		$this->assertGreaterThan( 0, (int) $row->post_generation_next_run );
		$this->assertGreaterThanOrEqual( 2, $summary['fixed_author_next_runs'] );
	}
}
