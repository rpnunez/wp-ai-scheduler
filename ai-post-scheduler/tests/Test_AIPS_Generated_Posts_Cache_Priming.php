<?php
/**
 * @group generated-posts
 */
class Test_AIPS_Generated_Posts_Cache_Priming extends WP_UnitTestCase {

	public function test_prime_history_post_caches_batches_post_loading() {
		$post_ids = self::factory()->post->create_many( 3 );
		wp_cache_flush();

		$items = array_map( static function ( $id ) {
			return (object) array( 'post_id' => $id );
		}, $post_ids );

		$controller = new AIPS_Generated_Posts_Controller();
		$method     = new ReflectionMethod( AIPS_Generated_Posts_Controller::class, 'prime_history_post_caches' );
		$method->setAccessible( true );
		$method->invoke( $controller, $items );

		global $wpdb;
		$queries_before = $wpdb->num_queries;
		foreach ( $post_ids as $id ) {
			get_post( $id );
		}
		$this->assertSame( $queries_before, $wpdb->num_queries );
	}
}
