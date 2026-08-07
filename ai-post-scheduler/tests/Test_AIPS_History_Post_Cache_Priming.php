<?php
/**
 * @group history
 */
class Test_AIPS_History_Post_Cache_Priming extends WP_UnitTestCase {

	public function test_prepare_items_primes_post_cache() {
		$post_ids = self::factory()->post->create_many( 3 );
		wp_cache_flush();

		$items = array_map( static function ( $id ) {
			return (object) array(
				'post_id'    => $id,
				'created_at' => time(),
			);
		}, $post_ids );

		$history = new AIPS_History();
		$method  = new ReflectionMethod( AIPS_History::class, 'prepare_items_for_display' );
		$method->setAccessible( true );
		$method->invokeArgs( $history, array( &$items ) );

		global $wpdb;
		$queries_before = $wpdb->num_queries;
		foreach ( $post_ids as $id ) {
			get_post( $id );
		}
		$this->assertSame( $queries_before, $wpdb->num_queries,
			'get_post() after prepare_items_for_display() must be served from cache.' );
	}
}
