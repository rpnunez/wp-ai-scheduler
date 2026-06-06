<?php
/**
 * Cache migration tests for the low-risk repository wave.
 *
 * Covers the voices, article structure, and prompt section repositories after
 * they were moved to the shared cacheable-repository trait.
 *
 * @package AI_Post_Scheduler
 */

if (!class_exists('AIPS_Voices_Repository')) {
	require_once dirname(__DIR__) . '/includes/class-aips-voices-repository.php';
}

if (!class_exists('AIPS_Article_Structure_Repository')) {
	require_once dirname(__DIR__) . '/includes/class-aips-article-structure-repository.php';
}

if (!class_exists('AIPS_Prompt_Section_Repository')) {
	require_once dirname(__DIR__) . '/includes/class-aips-prompt-section-repository.php';
}

/**
 * Minimal wpdb proxy that counts read calls for cache assertions.
 */
class Mock_WPDB_Low_Risk_Repository_Cache {

	public $prefix = 'wp_';
	public $insert_id = 0;
	public $last_error = '';
	public $get_row_calls = 0;
	public $get_results_calls = 0;
	public $get_var_calls = 0;
	public $get_row_return = null;
	public $get_results_return = array();
	public $get_var_return = 0;

	public function prepare( $query, ...$args ) {
		if (empty($args)) {
			return $query;
		}

		if (count($args) === 1 && is_array($args[0])) {
			$args = $args[0];
		}

		foreach ($args as $arg) {
			$query = preg_replace('/%[sd]/', is_numeric($arg) ? $arg : "'$arg'", $query, 1);
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
		return 1;
	}

	public function esc_like( $text ) {
		return addcslashes( $text, '_%\\' );
	}

	public function _escape( $text ) {
		return $text;
	}

	public function suppress_errors( $suppress = true ) {
		return false;
	}

	public function show_errors( $show = true ) {
		return true;
	}
}

/**
 * Shared setup/teardown for the low-risk repository cache tests.
 */
abstract class AIPS_Low_Risk_Repository_Cache_TestCase extends WP_UnitTestCase {

	/** @var Mock_WPDB_Low_Risk_Repository_Cache */
	protected $mock_wpdb;

	/** @var mixed Original global $wpdb. */
	protected $original_wpdb;

	public function setUp(): void {
		parent::setUp();
		AIPS_Cache_Factory::reset();

		global $wpdb;
		$this->original_wpdb = $wpdb;
		$this->mock_wpdb     = new Mock_WPDB_Low_Risk_Repository_Cache();
		$wpdb                = $this->mock_wpdb;
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb = $this->original_wpdb;
		AIPS_Cache_Factory::reset();
		parent::tearDown();
	}
}

class Test_Low_Risk_Voices_Repository_Cache extends AIPS_Low_Risk_Repository_Cache_TestCase {

	public function test_search_is_cached_after_first_call() {
		$this->mock_wpdb->get_results_return = array(
			(object) array( 'id' => 1, 'name' => 'Voice A' ),
		);
		$repo = new AIPS_Voices_Repository();

		$repo->search( 'Voice', 20 );
		$repo->search( 'Voice', 20 );

		$this->assertSame( 1, $this->mock_wpdb->get_results_calls );
	}

	public function test_search_cache_uses_limit_in_the_key() {
		$this->mock_wpdb->get_results_return = array(
			(object) array( 'id' => 1, 'name' => 'Voice A' ),
		);
		$repo = new AIPS_Voices_Repository();

		$repo->search( 'Voice', 10 );
		$repo->search( 'Voice', 25 );

		$this->assertSame( 2, $this->mock_wpdb->get_results_calls );
	}

	public function test_search_cache_is_invalidated_after_update() {
		$this->mock_wpdb->get_results_return = array(
			(object) array( 'id' => 1, 'name' => 'Voice A' ),
		);
		$repo = new AIPS_Voices_Repository();

		$repo->search( 'Voice', 20 );
		$repo->update( 1, array( 'name' => 'Updated Voice A' ) );
		$repo->search( 'Voice', 20 );

		$this->assertSame( 2, $this->mock_wpdb->get_results_calls );
	}
}

class Test_Low_Risk_Article_Structure_Repository_Cache extends AIPS_Low_Risk_Repository_Cache_TestCase {

	public function test_count_by_status_is_cached_after_first_call() {
		$this->mock_wpdb->get_row_return = (object) array( 'total' => 6, 'active' => 4 );
		$repo = new AIPS_Article_Structure_Repository();

		$repo->count_by_status();
		$repo->count_by_status();

		$this->assertSame( 1, $this->mock_wpdb->get_row_calls );
	}

	public function test_name_exists_is_cached_after_first_call() {
		$this->mock_wpdb->get_var_return = 1;
		$repo = new AIPS_Article_Structure_Repository();

		$repo->name_exists( 'Article Structure A' );
		$repo->name_exists( 'Article Structure A' );

		$this->assertSame( 1, $this->mock_wpdb->get_var_calls );
	}

	public function test_name_exists_cache_uses_exclude_id_in_the_key() {
		$this->mock_wpdb->get_var_return = 1;
		$repo = new AIPS_Article_Structure_Repository();

		$repo->name_exists( 'Article Structure A', 1 );
		$repo->name_exists( 'Article Structure A', 2 );

		$this->assertSame( 2, $this->mock_wpdb->get_var_calls );
	}

	public function test_count_cache_is_invalidated_after_delete() {
		$this->mock_wpdb->get_row_return = (object) array( 'total' => 6, 'active' => 4 );
		$repo = new AIPS_Article_Structure_Repository();

		$repo->count_by_status();
		$repo->delete( 1 );
		$repo->count_by_status();

		$this->assertSame( 2, $this->mock_wpdb->get_row_calls );
	}
}

class Test_Low_Risk_Prompt_Section_Repository_Cache extends AIPS_Low_Risk_Repository_Cache_TestCase {

	public function test_get_by_keys_is_cached_after_first_call() {
		$this->mock_wpdb->get_results_return = array(
			(object) array( 'id' => 1, 'section_key' => 'intro' ),
			(object) array( 'id' => 2, 'section_key' => 'body' ),
		);
		$repo = new AIPS_Prompt_Section_Repository();

		$repo->get_by_keys( array( 'intro', 'body' ) );
		$repo->get_by_keys( array( 'intro', 'body' ) );

		$this->assertSame( 1, $this->mock_wpdb->get_results_calls );
	}

	public function test_get_by_keys_uses_a_canonical_key_order() {
		$this->mock_wpdb->get_results_return = array(
			(object) array( 'id' => 1, 'section_key' => 'intro' ),
			(object) array( 'id' => 2, 'section_key' => 'body' ),
		);
		$repo = new AIPS_Prompt_Section_Repository();

		$repo->get_by_keys( array( 'body', 'intro' ) );
		$repo->get_by_keys( array( 'intro', 'body' ) );

		$this->assertSame( 1, $this->mock_wpdb->get_results_calls );
	}

	public function test_key_exists_is_cached_after_first_call() {
		$this->mock_wpdb->get_var_return = 1;
		$repo = new AIPS_Prompt_Section_Repository();

		$repo->key_exists( 'intro' );
		$repo->key_exists( 'intro' );

		$this->assertSame( 1, $this->mock_wpdb->get_var_calls );
	}

	public function test_get_by_keys_cache_is_invalidated_after_create() {
		$this->mock_wpdb->get_results_return = array(
			(object) array( 'id' => 1, 'section_key' => 'intro' ),
		);
		$repo = new AIPS_Prompt_Section_Repository();

		$repo->get_by_keys( array( 'intro' ) );
		$repo->create( array( 'name' => 'New Section', 'section_key' => 'new-section', 'content' => 'Content', 'is_active' => 1 ) );
		$repo->get_by_keys( array( 'intro' ) );

		$this->assertSame( 2, $this->mock_wpdb->get_results_calls );
	}
}