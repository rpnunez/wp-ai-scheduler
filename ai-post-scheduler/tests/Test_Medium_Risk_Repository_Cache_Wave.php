<?php
/**
 * Cache migration tests for the medium-risk repository wave.
 *
 * Covers sources, sources data, internal links, and taxonomy repositories after
 * migration to the shared cacheable-repository trait.
 *
 * @package AI_Post_Scheduler
 */

if (!class_exists('AIPS_Sources_Repository')) {
	require_once dirname(__DIR__) . '/includes/class-aips-sources-repository.php';
}

if (!class_exists('AIPS_Sources_Data_Repository')) {
	require_once dirname(__DIR__) . '/includes/class-aips-sources-data-repository.php';
}

if (!class_exists('AIPS_Internal_Links_Repository')) {
	require_once dirname(__DIR__) . '/includes/class-aips-internal-links-repository.php';
}

if (!class_exists('AIPS_Taxonomy_Repository')) {
	require_once dirname(__DIR__) . '/includes/class-aips-taxonomy-repository.php';
}

/**
 * Minimal wpdb proxy that counts read calls for cache assertions.
 */
class Mock_WPDB_Medium_Risk_Repository_Cache {

	public $prefix = 'wp_';
	public $posts = 'wp_posts';
	public $insert_id = 100;
	public $last_error = '';
	public $get_row_calls = 0;
	public $get_results_calls = 0;
	public $get_var_calls = 0;
	public $query_calls = 0;
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
 * Shared setup/teardown for medium-risk repository cache tests.
 */
abstract class AIPS_Medium_Risk_Repository_Cache_TestCase extends WP_UnitTestCase {

	/** @var Mock_WPDB_Medium_Risk_Repository_Cache */
	protected $mock_wpdb;

	/** @var mixed Original global $wpdb. */
	protected $original_wpdb;

	public function setUp(): void {
		parent::setUp();
		AIPS_Cache_Factory::reset();

		global $wpdb;
		$this->original_wpdb = $wpdb;
		$this->mock_wpdb     = new Mock_WPDB_Medium_Risk_Repository_Cache();
		$wpdb                = $this->mock_wpdb;
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb = $this->original_wpdb;
		AIPS_Cache_Factory::reset();
		parent::tearDown();
	}
}

class Test_Medium_Risk_Sources_Repository_Cache extends AIPS_Medium_Risk_Repository_Cache_TestCase {

	public function test_get_all_is_cached_after_first_call() {
		$this->mock_wpdb->get_results_return = array(
			(object) array( 'id' => 1, 'url' => 'https://example.com' ),
		);
		$repo = new AIPS_Sources_Repository();

		$repo->get_all( true );
		$repo->get_all( true );

		$this->assertSame( 1, $this->mock_wpdb->get_results_calls );
	}

	public function test_get_active_urls_cache_is_invalidated_after_create() {
		$this->mock_wpdb->get_results_return = array(
			(object) array( 'url' => 'https://example.com' ),
		);
		$repo = new AIPS_Sources_Repository();

		$repo->get_active_urls();
		$repo->create(
			array(
				'url'       => 'https://example.org',
				'is_active' => 1,
			)
		);
		$repo->get_active_urls();

		$this->assertSame( 2, $this->mock_wpdb->get_results_calls );
	}
}

class Test_Medium_Risk_Sources_Data_Repository_Cache extends AIPS_Medium_Risk_Repository_Cache_TestCase {

	public function test_get_counts_by_source_ids_uses_canonical_argument_keying() {
		$this->mock_wpdb->get_results_return = array(
			(object) array( 'source_id' => 3, 'content_count' => 4 ),
			(object) array( 'source_id' => 7, 'content_count' => 2 ),
		);
		$repo = new AIPS_Sources_Data_Repository();

		$repo->get_counts_by_source_ids( array( 7, 3 ) );
		$repo->get_counts_by_source_ids( array( 3, 7 ) );

		$this->assertSame( 1, $this->mock_wpdb->get_results_calls );
	}

	public function test_get_count_by_source_id_cache_is_invalidated_after_mark_fetch_failed() {
		$this->mock_wpdb->get_var_return = 5;
		$this->mock_wpdb->get_row_return = (object) array( 'id' => 22 );
		$repo = new AIPS_Sources_Data_Repository();

		$repo->get_count_by_source_id( 9 );
		$repo->mark_fetch_failed( 9, 'Network error', 500 );
		$repo->get_count_by_source_id( 9 );

		$this->assertSame( 2, $this->mock_wpdb->get_var_calls );
	}
}

class Test_Medium_Risk_Internal_Links_Repository_Cache extends AIPS_Medium_Risk_Repository_Cache_TestCase {

	public function test_get_paginated_count_is_cached_after_first_call() {
		$this->mock_wpdb->get_var_return = 12;
		$repo = new AIPS_Internal_Links_Repository();

		$repo->get_paginated_count( 'pending', 'hello' );
		$repo->get_paginated_count( 'pending', 'hello' );

		$this->assertSame( 1, $this->mock_wpdb->get_var_calls );
	}

	public function test_get_paginated_count_cache_is_invalidated_after_insert() {
		$this->mock_wpdb->get_var_return = 12;
		$repo = new AIPS_Internal_Links_Repository();

		$repo->get_paginated_count( 'pending', '' );
		$repo->insert( 11, 13, 0.95, 'Read more' );
		$repo->get_paginated_count( 'pending', '' );

		$this->assertSame( 2, $this->mock_wpdb->get_var_calls );
	}
}

class Test_Medium_Risk_Taxonomy_Repository_Cache extends AIPS_Medium_Risk_Repository_Cache_TestCase {

	public function test_search_is_cached_after_first_call() {
		$this->mock_wpdb->get_results_return = array(
			(object) array( 'id' => 1, 'name' => 'SEO', 'taxonomy_type' => 'post_tag' ),
		);
		$repo = new AIPS_Taxonomy_Repository();

		$repo->search( 'seo', 'post_tag' );
		$repo->search( 'seo', 'post_tag' );

		$this->assertSame( 1, $this->mock_wpdb->get_results_calls );
	}

	public function test_search_cache_is_invalidated_after_insert() {
		$this->mock_wpdb->get_results_return = array(
			(object) array( 'id' => 1, 'name' => 'SEO', 'taxonomy_type' => 'post_tag' ),
		);
		$repo = new AIPS_Taxonomy_Repository();

		$repo->search( 'seo', 'post_tag' );
		$repo->insert(
			array(
				'name'          => 'New Tag',
				'taxonomy_type' => 'post_tag',
				'status'        => 'pending',
			)
		);
		$repo->search( 'seo', 'post_tag' );

		$this->assertSame( 2, $this->mock_wpdb->get_results_calls );
	}
}