<?php
/**
 * @group schedule
 */
class Test_AIPS_Schedule_Repository_Batch extends WP_UnitTestCase {

	public function test_get_by_templates_groups_by_template_id() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_schedule';
		$wpdb->insert( $table, array( 'template_id' => 11, 'next_run' => 2000 ) );
		$wpdb->insert( $table, array( 'template_id' => 11, 'next_run' => 1000 ) );
		$wpdb->insert( $table, array( 'template_id' => 12, 'next_run' => 3000 ) );

		$repo   = new AIPS_Schedule_Repository();
		$result = $repo->get_by_templates( array( 11, 12, 99 ) );

		$this->assertCount( 2, $result[11] );
		$this->assertEquals( 1000, $result[11][0]->next_run, 'Lists must be ordered next_run ASC.' );
		$this->assertCount( 1, $result[12] );
		$this->assertArrayNotHasKey( 99, $result );
	}

	public function test_get_by_templates_empty_input_returns_empty_array() {
		$repo = new AIPS_Schedule_Repository();
		$this->assertSame( array(), $repo->get_by_templates( array() ) );
	}
}
