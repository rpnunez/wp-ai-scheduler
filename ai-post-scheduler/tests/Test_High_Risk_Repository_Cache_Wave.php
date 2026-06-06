<?php
/**
 * Cache migration tests for the high-risk repository wave.
 *
 * Covers selected safe cache reads for history and telemetry repositories.
 *
 * @package AI_Post_Scheduler
 */

if (!class_exists('AIPS_History_Repository')) {
	require_once dirname(__DIR__) . '/includes/class-aips-history-repository.php';
}

if (!class_exists('AIPS_Telemetry_Repository')) {
	require_once dirname(__DIR__) . '/includes/class-aips-telemetry-repository.php';
}

if (!class_exists('AIPS_Post_Slices_Repository')) {
	require_once dirname(__DIR__) . '/includes/class-aips-post-slices-repository.php';
}

if (!class_exists('AIPS_Bulk_Batch_Job_Store')) {
	require_once dirname(__DIR__) . '/includes/class-aips-bulk-batch-job-store.php';
}

class Mock_WPDB_High_Risk_Repository_Cache {
	public $prefix = 'wp_';
	public $posts = 'wp_posts';
	public $postmeta = 'wp_postmeta';
	public $insert_id = 100;
	public $last_error = '';
	public $get_row_calls = 0;
	public $get_results_calls = 0;
	public $get_var_calls = 0;
	public $query_calls = 0;
	public $get_row_return = null;
	public $get_results_return = array();
	public $get_var_return = 0;
	public $get_col_return = array();

	public function prepare( $query, ...$args ) {
		if (empty($args)) {
			return $query;
		}

		if (count($args) === 1 && is_array($args[0])) {
			$args = $args[0];
		}

		foreach ($args as $arg) {
			$query = preg_replace('/%[sdf]/', is_numeric($arg) ? (string) $arg : "'" . $arg . "'", $query, 1);
		}

		return $query;
	}

	public function get_row( $query, $output = null, $y = 0 ) {
		$this->get_row_calls++;
		return $this->get_row_return;
	}

	public function get_results( $query, $output = null ) {
		$this->get_results_calls++;
		return $this->get_results_return;
	}

	public function get_var( $query = null, $x = 0, $y = 0 ) {
		$this->get_var_calls++;
		return $this->get_var_return;
	}

	public function get_col( $query = null, $x = 0 ) {
		return $this->get_col_return;
	}

	public function insert( $table, $data, $format = null ) {
		$this->insert_id++;
		return true;
	}

	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		return 1;
	}

	public function delete( $table, $where, $where_format = null ) {
		return 1;
	}

	public function query( $sql ) {
		$this->query_calls++;
		return 1;
	}

	public function esc_like( $text ) {
		return addcslashes( $text, '_%\\' );
	}
}

abstract class AIPS_High_Risk_Repository_Cache_TestCase extends WP_UnitTestCase {
	protected $mock_wpdb;
	protected $original_wpdb;

	public function setUp(): void {
		parent::setUp();
		AIPS_Cache_Factory::reset();

		global $wpdb;
		$this->original_wpdb = $wpdb;
		$this->mock_wpdb     = new Mock_WPDB_High_Risk_Repository_Cache();
		$wpdb                = $this->mock_wpdb;
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb = $this->original_wpdb;
		AIPS_Cache_Factory::reset();
		parent::tearDown();
	}
}

class Test_High_Risk_History_Repository_Cache extends AIPS_High_Risk_Repository_Cache_TestCase {
	public function test_get_stats_is_cached_after_first_call() {
		$this->mock_wpdb->get_row_return = (object) array(
			'total'      => 10,
			'completed'  => 8,
			'failed'     => 1,
			'processing' => 1,
			'partial'    => 0,
		);
		$repo = new AIPS_History_Repository();

		$repo->get_stats();
		$repo->get_stats();

		$this->assertSame( 1, $this->mock_wpdb->get_row_calls );
	}

	public function test_get_stats_cache_is_invalidated_after_create() {
		$this->mock_wpdb->get_row_return = (object) array(
			'total'      => 10,
			'completed'  => 8,
			'failed'     => 1,
			'processing' => 1,
			'partial'    => 0,
		);
		$repo = new AIPS_History_Repository();

		$repo->get_stats();
		$repo->create(
			array(
				'status'          => 'completed',
				'generated_title' => 'Example',
			)
		);
		$repo->get_stats();

		$this->assertSame( 2, $this->mock_wpdb->get_row_calls );
	}

	public function test_get_template_stats_cache_is_invalidated_after_create() {
		$this->mock_wpdb->get_var_return = 4;
		$repo = new AIPS_History_Repository();

		$repo->get_template_stats( 7 );
		$repo->create(
			array(
				'template_id'     => 7,
				'status'          => 'completed',
				'generated_title' => 'Template Example',
			)
		);
		$repo->get_template_stats( 7 );

		$this->assertSame( 2, $this->mock_wpdb->get_var_calls );
	}
}

class Test_High_Risk_Telemetry_Repository_Cache extends AIPS_High_Risk_Repository_Cache_TestCase {
	public function test_count_is_cached_after_first_call() {
		$this->mock_wpdb->get_var_return = 33;
		$repo = new AIPS_Telemetry_Repository();

		$repo->count();
		$repo->count();

		$this->assertSame( 1, $this->mock_wpdb->get_var_calls );
	}

	public function test_count_cache_is_invalidated_after_insert() {
		$this->mock_wpdb->get_var_return = 33;
		$repo = new AIPS_Telemetry_Repository();

		$repo->count();
		$repo->insert(
			array(
				'type'        => 'request',
				'inserted_at' => 123,
			)
		);
		$repo->count();

		$this->assertSame( 2, $this->mock_wpdb->get_var_calls );
	}
}

class Test_High_Risk_Post_Slices_Repository_Cache extends AIPS_High_Risk_Repository_Cache_TestCase {
	public function test_get_counts_is_cached_after_first_call() {
		$this->mock_wpdb->get_results_return = array(
			array( 'is_active' => 1, 'count' => 3 ),
			array( 'is_active' => 0, 'count' => 2 ),
		);
		$repo = new AIPS_Post_Slices_Repository();

		$repo->get_counts();
		$repo->get_counts();

		$this->assertSame( 1, $this->mock_wpdb->get_results_calls );
	}

	public function test_get_counts_cache_is_invalidated_after_update() {
		$this->mock_wpdb->get_results_return = array(
			array( 'is_active' => 1, 'count' => 3 ),
			array( 'is_active' => 0, 'count' => 2 ),
		);
		$repo = new AIPS_Post_Slices_Repository();

		$repo->get_counts();
		$repo->update( 5, array( 'name' => 'Updated' ) );
		$repo->get_counts();

		$this->assertSame( 2, $this->mock_wpdb->get_results_calls );
	}
}

class Test_High_Risk_Bulk_Batch_Job_Store_Cache extends AIPS_High_Risk_Repository_Cache_TestCase {
	public function test_get_status_counts_is_cached_for_same_status_set() {
		$this->mock_wpdb->get_results_return = array(
			array( 'status' => 'pending', 'count' => 3 ),
			array( 'status' => 'failed', 'count' => 1 ),
		);
		$store = new AIPS_Bulk_Batch_Job_Store();

		$store->get_status_counts( array( 'failed', 'pending' ) );
		$store->get_status_counts( array( 'pending', 'failed' ) );

		$this->assertSame( 1, $this->mock_wpdb->get_results_calls );
	}

	public function test_get_status_counts_cache_is_invalidated_after_update_status() {
		$this->mock_wpdb->get_results_return = array(
			array( 'status' => 'processing', 'count' => 2 ),
		);
		$store = new AIPS_Bulk_Batch_Job_Store();

		$store->get_status_counts( array( 'processing' ) );
		$store->update_status( 'job-123', 'processing', 4 );
		$store->get_status_counts( array( 'processing' ) );

		$this->assertSame( 2, $this->mock_wpdb->get_results_calls );
	}
}