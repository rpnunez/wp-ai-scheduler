<?php
/**
 * @group authors
 */
class Test_AIPS_Author_Batch_Lookups extends WP_UnitTestCase {

	public function test_authors_get_by_ids_returns_map_keyed_by_id() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_authors';
		$wpdb->insert( $table, array( 'name' => 'Alpha' ) );
		$a = (int) $wpdb->insert_id;
		$wpdb->insert( $table, array( 'name' => 'Beta' ) );
		$b = (int) $wpdb->insert_id;

		$repo = new AIPS_Authors_Repository();
		$map  = $repo->get_by_ids( array( $a, $b, 999999 ) );

		$this->assertSame( 'Alpha', $map[ $a ]->name );
		$this->assertSame( 'Beta', $map[ $b ]->name );
		$this->assertArrayNotHasKey( 999999, $map );
	}

	public function test_topics_get_by_ids_returns_map_keyed_by_id() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_author_topics';
		$wpdb->insert( $table, array( 'author_id' => 1, 'topic_title' => 'T1', 'generated_at' => time() ) );
		$t1 = (int) $wpdb->insert_id;

		$repo = new AIPS_Author_Topics_Repository();
		$map  = $repo->get_by_ids( array( $t1 ) );

		$this->assertSame( 'T1', $map[ $t1 ]->topic_title );
	}

	public function test_get_by_ids_empty_input_returns_empty_array() {
		$repo = new AIPS_Authors_Repository();
		$this->assertSame( array(), $repo->get_by_ids( array() ) );
	}

	/**
	 * Reproduces the double-fetch bug: prime_source_caches() seeds a missing
	 * author/topic id with null, then (before this fix) used isset() to decide
	 * whether to re-fetch — isset() is false for null, so the second call in
	 * the same request (render_page() calls prime_source_caches() twice: once
	 * for the Generated Posts list, once for Partial Generations) re-issued a
	 * redundant get_by_ids() query for the same missing id.
	 */
	public function test_prime_source_caches_does_not_refetch_missing_ids_on_second_call() {
		$missing_author_id = 999999;
		$missing_topic_id  = 888888;

		$items = array(
			(object) array( 'author_id' => $missing_author_id, 'topic_id' => $missing_topic_id ),
		);

		$controller = new AIPS_Generated_Posts_Controller();
		$method     = new ReflectionMethod( AIPS_Generated_Posts_Controller::class, 'prime_source_caches' );
		$method->setAccessible( true );

		// First call: misses, seeds null, issues the batch queries.
		$method->invoke( $controller, $items );

		// Second call (mirrors render_page()'s second prime_source_caches()
		// call for the Partial Generations list): must not re-query.
		global $wpdb;
		$queries_before = $wpdb->num_queries;
		$method->invoke( $controller, $items );

		$this->assertSame( $queries_before, $wpdb->num_queries,
			'Second prime_source_caches() call must not re-fetch ids already known to be missing.' );
	}
}
