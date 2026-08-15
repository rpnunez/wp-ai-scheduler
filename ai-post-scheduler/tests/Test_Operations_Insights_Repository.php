<?php
class Test_Operations_Insights_Repository extends WP_UnitTestCase {
    private $repository;

    public function set_up() {
        parent::set_up();
        $this->repository = new AIPS_Operations_Insights_Repository();
    }

    public function test_average_duration_by_flow_skips_incomplete_rows_without_underflow() {
        global $wpdb;

        $table = $wpdb->prefix . 'aips_history';

        $wpdb->insert(
            $table,
            array(
                'status'          => 'processing',
                'created_at'      => time(),
                'completed_at'    => 0,
                'creation_method' => 'manual',
            )
        );

        $wpdb->last_error = '';

        $rows = $this->repository->get_average_duration_by_flow(30);

        $this->assertSame('', $wpdb->last_error, 'Incomplete rows must not trigger an unsigned-underflow error.');
        $this->assertIsArray($rows);
    }
}
