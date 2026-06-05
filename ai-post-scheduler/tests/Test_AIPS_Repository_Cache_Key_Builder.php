<?php
/**
 * Tests for stable repository cache key generation.
 *
 * @package AI_Post_Scheduler
 */


if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}

if (!defined('AIPS_VERSION')) {
	define('AIPS_VERSION', 'test');
}

if (!class_exists('AIPS_Repository_Cache_Key_Builder')) {
	require_once dirname(__DIR__) . '/includes/class-aips-repository-cache-key-builder.php';
}

class Test_AIPS_Repository_Cache_Key_Builder extends PHPUnit\Framework\TestCase {

	public function test_equivalent_associative_argument_order_generates_identical_keys() {
		$first = array(
			'filters'     => array(
				'status'    => 'approved',
				'search'    => 'wordpress',
				'date_from' => '2026-01-01',
				'date_to'   => '2026-01-31',
			),
			'pagination' => array(
				'page'     => 2,
				'per_page' => 20,
			),
			'sort'       => array(
				'orderby' => 'created_at',
				'order'   => 'DESC',
			),
			'author_id'  => '42',
			'active'     => true,
		);

		$second = array(
			'active'     => 1,
			'author_id'  => 42,
			'sort'       => array(
				'order'   => 'DESC',
				'orderby' => 'created_at',
			),
			'pagination' => array(
				'per_page' => 20,
				'page'     => 2,
			),
			'filters'     => array(
				'date_to'   => '2026-01-31',
				'date_from' => '2026-01-01',
				'search'    => 'wordpress',
				'status'    => 'approved',
			),
		);

		$this->assertSame(
			AIPS_Repository_Cache_Key_Builder::build_key( 'author_topics:list', $first ),
			AIPS_Repository_Cache_Key_Builder::build_key( 'author_topics:list', $second )
		);
	}

	public function test_indexed_array_order_is_preserved() {
		$first = AIPS_Repository_Cache_Key_Builder::build_key(
			'templates:by-status',
			array(
				'statuses' => array( 'draft', 'publish' ),
			)
		);

		$second = AIPS_Repository_Cache_Key_Builder::build_key(
			'templates:by-status',
			array(
				'statuses' => array( 'publish', 'draft' ),
			)
		);

		$this->assertNotSame( $first, $second );
	}

	public function test_different_filters_produce_different_keys() {
		$approved = AIPS_Repository_Cache_Key_Builder::build_key(
			'author_topics:list',
			array(
				'filters' => array(
					'status' => 'approved',
					'search' => 'wordpress',
				),
			)
		);

		$rejected = AIPS_Repository_Cache_Key_Builder::build_key(
			'author_topics:list',
			array(
				'filters' => array(
					'status' => 'rejected',
					'search' => 'wordpress',
				),
			)
		);

		$this->assertNotSame( $approved, $rejected );
	}

	public function test_typed_numeric_ids_and_booleans_are_normalized() {
		$first = AIPS_Repository_Cache_Key_Builder::normalize_args(
			array(
				'author_id'   => '7',
				'topic_id'    => '8',
				'template_id' => 9.0,
				'include_ids' => array( '1', 2.0, '3' ),
				'active'      => false,
			)
		);

		$this->assertSame( 7, $first['author_id'] );
		$this->assertSame( 8, $first['topic_id'] );
		$this->assertSame( 9, $first['template_id'] );
		$this->assertSame( array( 1, 2, 3 ), $first['include_ids'] );
		$this->assertSame( 0, $first['active'] );
	}

	public function test_empty_filters_are_normalized_to_stable_value() {
		$normalized = AIPS_Repository_Cache_Key_Builder::normalize_args(
			array(
				'filters'      => array(),
				'date_filters' => array(
					'from' => '',
					'to'   => null,
				),
			)
		);

		$this->assertSame( AIPS_Repository_Cache_Key_Builder::EMPTY_FILTER_VALUE, $normalized['filters'] );
		$this->assertSame( AIPS_Repository_Cache_Key_Builder::EMPTY_FILTER_VALUE, $normalized['date_filters']['from'] );
		$this->assertSame( AIPS_Repository_Cache_Key_Builder::EMPTY_FILTER_VALUE, $normalized['date_filters']['to'] );
	}

	public function test_tag_versions_and_context_participate_in_key() {
		$base = AIPS_Repository_Cache_Key_Builder::build_key(
			'history:stats',
			array( 'template_id' => 10 ),
			array( 'history' => 1 ),
			array( 'locale' => 'en_US' )
		);

		$changed_tag = AIPS_Repository_Cache_Key_Builder::build_key(
			'history:stats',
			array( 'template_id' => 10 ),
			array( 'history' => 2 ),
			array( 'locale' => 'en_US' )
		);

		$changed_context = AIPS_Repository_Cache_Key_Builder::build_key(
			'history:stats',
			array( 'template_id' => 10 ),
			array( 'history' => 1 ),
			array( 'locale' => 'es_ES' )
		);

		$this->assertStringStartsWith( 'aips_repo:history_stats:', $base );
		$this->assertStringContainsString( ':ctx:', $base );
		$this->assertNotSame( $base, $changed_tag );
		$this->assertNotSame( $base, $changed_context );
	}

	public function test_is_associative_array_treats_empty_array_as_non_associative() {
		$method = new ReflectionMethod( 'AIPS_Repository_Cache_Key_Builder', 'is_associative_array' );
		$method->setAccessible( true );

		$this->assertFalse( $method->invoke( null, array() ) );
	}
}
