<?php
/**
 * Tests for AIPS_Link_Index_Repository.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Link_Index_Repository extends WP_UnitTestCase {

	/** @var AIPS_Link_Index_Repository */
	private $repo;

	public function setUp(): void {
		parent::setUp();
		$this->repo = new AIPS_Link_Index_Repository();
		$this->repo->delete_all();
	}

	public function tearDown(): void {
		$this->repo->delete_all();
		parent::tearDown();
	}

	private function internal_link( int $target_id, string $anchor = 'anchor' ): array {
		return array(
			'target_url'     => get_permalink( $target_id ),
			'target_post_id' => $target_id,
			'anchor_text'    => $anchor,
			'link_type'      => 'internal',
		);
	}

	private function external_link( string $url = 'https://example.org/page' ): array {
		return array(
			'target_url'  => $url,
			'anchor_text' => 'external',
			'link_type'   => 'external',
			'rel'         => 'nofollow',
			'is_nofollow' => true,
		);
	}

	public function test_table_exists() {
		$this->assertTrue( $this->repo->table_exists() );
	}

	public function test_sync_for_source_replaces_existing_rows() {
		list( $source, $a, $b ) = self::factory()->post->create_many( 3 );

		$this->assertSame( 2, $this->repo->sync_for_source( $source, array( $this->internal_link( $a ), $this->external_link() ) ) );
		$this->assertCount( 2, $this->repo->get_outbound( $source ) );

		$this->assertSame( 1, $this->repo->sync_for_source( $source, array( $this->internal_link( $b ) ) ) );
		$outbound = $this->repo->get_outbound( $source );

		$this->assertCount( 1, $outbound );
		$this->assertSame( $b, (int) $outbound[0]->target_post_id );
		$this->assertSame( md5( get_permalink( $b ) ), $outbound[0]->url_hash );
	}

	public function test_sync_for_source_skips_invalid_links_and_zeroes_external_targets() {
		$source = self::factory()->post->create();

		$written = $this->repo->sync_for_source(
			$source,
			array(
				array( 'anchor_text' => 'no url' ),
				'not-an-array',
				array(
					'target_url'     => 'https://example.org/',
					'target_post_id' => 99,
					'link_type'      => 'external',
				),
			)
		);

		$this->assertSame( 1, $written );
		$rows = $this->repo->get_outbound( $source );
		$this->assertSame( 0, (int) $rows[0]->target_post_id );
		$this->assertSame( 'external', $rows[0]->link_type );
	}

	public function test_sync_for_source_handles_more_rows_than_one_insert_chunk() {
		list( $source, $target ) = self::factory()->post->create_many( 2 );

		$links = array();
		for ( $i = 0; $i < AIPS_Link_Index_Repository::INSERT_CHUNK_SIZE + 5; $i++ ) {
			$links[] = $this->external_link( 'https://example.org/page-' . $i );
		}
		$links[] = $this->internal_link( $target );

		$this->assertSame( count( $links ), $this->repo->sync_for_source( $source, $links ) );
		$this->assertCount( 1, $this->repo->get_outbound( $source, 'internal' ) );
	}

	public function test_get_outbound_filters_by_type() {
		list( $source, $target ) = self::factory()->post->create_many( 2 );
		$this->repo->sync_for_source( $source, array( $this->internal_link( $target ), $this->external_link() ) );

		$this->assertCount( 1, $this->repo->get_outbound( $source, 'internal' ) );
		$this->assertCount( 1, $this->repo->get_outbound( $source, 'external' ) );
		$this->assertCount( 2, $this->repo->get_outbound( $source, 'bogus' ) );
	}

	public function test_inbound_queries_exclude_self_links() {
		list( $a, $b, $target ) = self::factory()->post->create_many( 3 );

		$this->repo->sync_for_source( $a, array( $this->internal_link( $target ), $this->internal_link( $target, 'again' ) ) );
		$this->repo->sync_for_source( $b, array( $this->internal_link( $target ) ) );
		$this->repo->sync_for_source( $target, array( $this->internal_link( $target, 'self' ) ) );

		$this->assertCount( 3, $this->repo->get_inbound( $target ) );
		$this->assertEqualsCanonicalizing( array( $a, $b ), $this->repo->get_source_ids_linking_to( $target ) );
		$this->assertTrue( $this->repo->source_links_to( $a, $target ) );
		$this->assertFalse( $this->repo->source_links_to( $target, $a ) );
	}

	public function test_get_counts_for_posts() {
		list( $a, $b, $target, $unlinked ) = self::factory()->post->create_many( 4 );

		$this->repo->sync_for_source( $a, array( $this->internal_link( $target ), $this->internal_link( $target ), $this->external_link() ) );
		$this->repo->sync_for_source(
			$b,
			array(
				$this->internal_link( $target ),
				array(
					'target_url' => home_url( '/missing-page/' ),
					'link_type'  => 'internal',
				),
			)
		);

		$counts = $this->repo->get_counts_for_posts( array( $a, $b, $target, $unlinked ) );

		$this->assertSame( array( 'inbound' => 0, 'outbound' => 2, 'external' => 1, 'broken' => 0 ), $counts[ $a ] );
		$this->assertSame( array( 'inbound' => 0, 'outbound' => 1, 'external' => 0, 'broken' => 1 ), $counts[ $b ] );
		$this->assertSame( 2, $counts[ $target ]['inbound'] );
		$this->assertSame( array( 'inbound' => 0, 'outbound' => 0, 'external' => 0, 'broken' => 0 ), $counts[ $unlinked ] );
	}

	public function test_clear_target_turns_links_into_broken_internal_links() {
		list( $source, $target ) = self::factory()->post->create_many( 2 );
		$this->repo->sync_for_source( $source, array( $this->internal_link( $target ) ) );

		$this->repo->clear_target( $target );

		$counts = $this->repo->get_counts_for_posts( array( $source, $target ) );
		$this->assertSame( 0, $counts[ $target ]['inbound'] );
		$this->assertSame( 1, $counts[ $source ]['broken'] );
		$this->assertSame( 1, $this->repo->get_summary()['broken'] );
	}

	public function test_delete_for_source() {
		list( $source, $target ) = self::factory()->post->create_many( 2 );
		$this->repo->sync_for_source( $source, array( $this->internal_link( $target ) ) );

		$this->repo->delete_for_source( $source );

		$this->assertSame( array(), $this->repo->get_outbound( $source ) );
	}

	public function test_report_orphans_filter_and_ordering() {
		$hub    = self::factory()->post->create( array( 'post_title' => 'Hub' ) );
		$linked = self::factory()->post->create( array( 'post_title' => 'Linked' ) );
		$orphan = self::factory()->post->create( array( 'post_title' => 'Orphan' ) );
		self::factory()->post->create( array( 'post_title' => 'Draft', 'post_status' => 'draft' ) );

		$this->repo->sync_for_source( $hub, array( $this->internal_link( $linked ), $this->external_link() ) );

		$args = array( 'post_types' => array( 'post' ), 'search' => '' );

		$this->assertSame( 3, $this->repo->get_report_count( $args ) );

		$orphans = $this->repo->get_report_page( $args + array( 'orphans_only' => true ) );
		$this->assertEqualsCanonicalizing( array( $hub, $orphan ), wp_list_pluck( $orphans, 'ID' ) );
		$this->assertSame( 2, $this->repo->get_report_count( $args + array( 'orphans_only' => true ) ) );

		$by_outbound = $this->repo->get_report_page( $args + array( 'orderby' => 'outbound', 'order' => 'DESC' ) );
		$this->assertSame( $hub, $by_outbound[0]->ID );
		$this->assertSame( 1, $by_outbound[0]->outbound );
		$this->assertSame( 1, $by_outbound[0]->external );

		$by_inbound = $this->repo->get_report_page( $args + array( 'orderby' => 'inbound', 'order' => 'DESC' ) );
		$this->assertSame( $linked, $by_inbound[0]->ID );
		$this->assertSame( 1, $by_inbound[0]->inbound );
	}

	public function test_report_rejects_unknown_orderby_and_paginates() {
		self::factory()->post->create_many( 3 );

		$page = $this->repo->get_report_page(
			array(
				'orderby'  => 'ID; DROP TABLE wp_posts',
				'per_page' => 2,
				'page'     => 2,
			)
		);

		$this->assertCount( 1, $page );
	}

	public function test_report_search_filters_titles() {
		self::factory()->post->create( array( 'post_title' => 'Alpha internal linking guide' ) );
		self::factory()->post->create( array( 'post_title' => 'Beta' ) );

		$rows = $this->repo->get_report_page( array( 'search' => 'linking' ) );

		$this->assertCount( 1, $rows );
		$this->assertSame( 'Alpha internal linking guide', $rows[0]->post_title );
	}

	public function test_get_summary() {
		list( $source, $target ) = self::factory()->post->create_many( 2 );
		$this->repo->sync_for_source( $source, array( $this->internal_link( $target ), $this->external_link() ) );

		$this->assertSame(
			array( 'total' => 2, 'internal' => 1, 'external' => 1, 'broken' => 0, 'sources' => 1 ),
			$this->repo->get_summary()
		);
	}
}
