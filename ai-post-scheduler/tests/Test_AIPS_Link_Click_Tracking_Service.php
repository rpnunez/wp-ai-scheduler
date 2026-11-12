<?php
/**
 * Tests for AIPS_Link_Click_Tracking_Service.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Link_Click_Tracking_Service extends WP_UnitTestCase {

	public function test_get_top_links_primes_caches_and_returns_data() {
		$source = self::factory()->post->create( array( 'post_title' => 'Source Post' ) );
		$target = self::factory()->post->create( array( 'post_title' => 'Target Post' ) );

		$mock_repo = $this->createMock( AIPS_Link_Clicks_Repository::class );
		$mock_repo->expects( $this->once() )
			->method( 'get_top_links' )
			->willReturn(
				array(
					(object) array(
						'source_post_id' => $source,
						'target_post_id' => $target,
						'clicks'         => 5,
					),
				)
			);

		$service   = new AIPS_Link_Click_Tracking_Service( null, $mock_repo );
		$top_links = $service->get_top_links( 10 );

		$this->assertCount( 1, $top_links );
		$this->assertEquals( $source, $top_links[0]['source_id'] );
		$this->assertEquals( 'Source Post', $top_links[0]['source_title'] );
		$this->assertEquals( $target, $top_links[0]['target_id'] );
		$this->assertEquals( 'Target Post', $top_links[0]['target_title'] );
		$this->assertEquals( 5, $top_links[0]['clicks'] );
	}
}
