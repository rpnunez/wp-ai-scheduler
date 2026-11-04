<?php
/**
 * Tests for Affiliate Marketing Links Generation, Matching, Attributes and Queue
 *
 * @package AI_Post_Scheduler
 * @since 3.7.20
 */

class Test_AIPS_Affiliate_Marketing_Links extends WP_UnitTestCase {

	/**
	 * @var AIPS_Affiliate_Links_Repository
	 */
	private $links_repo;

	/**
	 * @var AIPS_Affiliate_Link_Suggestions_Repository
	 */
	private $sug_repo;

	/**
	 * @var AIPS_Affiliate_Links_Service
	 */
	private $links_service;

	/**
	 * @var AIPS_Affiliate_Link_Inserter_Service
	 */
	private $inserter_service;

	public function setUp(): void {
		parent::setUp();

		// Run DB delta / migration to ensure tables and columns exist in test DB
		AIPS_DB_Manager::install_tables();

		$this->links_repo = AIPS_Container::get_instance()->make( AIPS_Affiliate_Links_Repository::class );
		$this->sug_repo = AIPS_Container::get_instance()->make( AIPS_Affiliate_Link_Suggestions_Repository::class );
		$this->links_service = AIPS_Container::get_instance()->make( AIPS_Affiliate_Links_Service::class );
		$this->inserter_service = AIPS_Container::get_instance()->make( AIPS_Affiliate_Link_Inserter_Service::class );
	}

	public function test_config_affiliate_defaults() {
		$config = AIPS_Config::get_instance();
		$this->assertFalse( $config->get_option( 'aips_affiliate_disclosure_enabled' ) );
		$this->assertEquals( 'top', $config->get_option( 'aips_affiliate_disclosure_position' ) );
		$this->assertEquals( 1, $config->get_option( 'aips_affiliate_max_links_per_post' ) );
		$this->assertTrue( $config->get_option( 'aips_affiliate_enforce_sponsored_rel' ) );
		$this->assertStringContainsString( 'affiliate links', $config->get_option( 'aips_affiliate_disclosure_text' ) );
	}

	public function test_affiliate_links_crud_with_extended_fields() {
		$id = $this->links_repo->insert( array(
			'tag'                => 'vps-hosting',
			'label'              => 'Namecheap VPS',
			'affiliate_url'      => 'https://namecheap.pxf.io/c/123/456',
			'keywords'           => 'vps, cloud hosting, dedicated server',
			'category_ids'       => array( 10, 20 ),
			'prompt_guidance'    => 'Emphasize 99.9% uptime and round-the-clock support.',
			'is_global_fallback' => 1,
			'enabled'            => 1,
		) );

		$this->assertGreaterThan( 0, $id );

		$item = $this->links_repo->get_by_id( $id );
		$this->assertEquals( 'vps-hosting', $item->tag );
		$this->assertEquals( 'Namecheap VPS', $item->label );
		$this->assertEquals( 'https://namecheap.pxf.io/c/123/456', $item->affiliate_url );
		$this->assertEquals( 'vps, cloud hosting, dedicated server', $item->keywords );
		$this->assertEquals( 1, (int) $item->is_global_fallback );
		$this->assertStringContainsString( '10', $item->category_ids );

		// Update
		$updated = $this->links_repo->update( $id, array(
			'label'              => 'Namecheap Managed VPS',
			'is_global_fallback' => 0,
		) );
		$this->assertNotFalse( $updated );

		$item_updated = $this->links_repo->get_by_id( $id );
		$this->assertEquals( 'Namecheap Managed VPS', $item_updated->label );
		$this->assertEquals( 0, (int) $item_updated->is_global_fallback );

		// Clean up
		$this->links_repo->delete( $id );
	}

	public function test_offer_resolution_matching_tags_and_keywords() {
		$id1 = $this->links_repo->insert( array(
			'tag'           => 'docker',
			'label'         => 'Docker Pro',
			'affiliate_url' => 'https://docker.com/aff',
			'keywords'      => 'containers, kubernetes, microservices',
			'enabled'       => 1,
		) );

		$id2 = $this->links_repo->insert( array(
			'tag'                => 'general-tech',
			'label'              => 'General Tech Fallback',
			'affiliate_url'      => 'https://example.com/fallback',
			'is_global_fallback' => 1,
			'enabled'            => 1,
		) );

		// 1. Direct tag match
		$matches = $this->links_repo->find_matching_offers( '', null, array( 'docker' ) );
		$this->assertNotEmpty( $matches );
		$this->assertEquals( $id1, $matches[0]->id );

		// 2. Keyword match
		$matches_kw = $this->links_repo->find_matching_offers( 'A complete guide to containers', null, array() );
		$this->assertNotEmpty( $matches_kw );
		$this->assertEquals( $id1, $matches_kw[0]->id );

		// 3. Fallback resolution when no tag/keyword matches
		$fallbacks = $this->links_repo->get_enabled_fallbacks();
		$this->assertNotEmpty( $fallbacks );
		$this->assertEquals( $id2, $fallbacks[0]->id );

		// Clean up
		$this->links_repo->delete( $id1 );
		$this->links_repo->delete( $id2 );
	}

	public function test_normalize_affiliate_attributes() {
		// When content contains an affiliate link, ensure rel="nofollow sponsored noopener" and target="_blank" are added
		$target_url = 'https://namecheap.pxf.io/c/123/456';
		$raw_content = '<p>Check out <a href="' . $target_url . '">Namecheap Hosting</a> for fast servers.</p>';

		$normalized = $this->inserter_service->normalize_affiliate_attributes( $raw_content, $target_url );

		$this->assertStringContainsString( 'rel="nofollow sponsored noopener"', $normalized );
		$this->assertStringContainsString( 'target="_blank"', $normalized );
		$this->assertStringContainsString( 'noopener', $normalized );
	}

	public function test_apply_disclosure() {
		update_option( 'aips_affiliate_disclosure_enabled', 1 );
		update_option( 'aips_affiliate_disclosure_text', 'Disclosure: Contains affiliate links.' );
		update_option( 'aips_affiliate_disclosure_position', 'top' );

		$content = '<p>Article body here.</p>';
		$result = $this->inserter_service->apply_disclosure( $content );

		$this->assertStringContainsString( 'aips-affiliate-disclosure', $result );
		$this->assertStringContainsString( 'Disclosure: Contains affiliate links.', $result );
		$this->assertStringStartsWith( '<div class="aips-affiliate-disclosure', trim( $result ) );

		// Bottom position
		update_option( 'aips_affiliate_disclosure_position', 'bottom' );
		$result_bottom = $this->inserter_service->apply_disclosure( $content );
		$this->assertStringEndsWith( '</div>', trim( $result_bottom ) );
	}

	public function test_suggestions_queue_repository() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'Affiliate Test Post' ) );

		$sug_id = $this->sug_repo->insert( array(
			'post_id'           => $post_id,
			'affiliate_link_id' => 999,
			'anchor_text'       => 'best cloud hosting',
			'match_type'        => 'in_content',
			'before_snippet'    => 'We recommend the best cloud hosting for your app.',
			'status'            => 'pending',
		) );

		$this->assertGreaterThan( 0, $sug_id );
		$this->assertTrue( $this->sug_repo->exists_for_post_and_link( $post_id, 999 ) );

		$items = $this->sug_repo->get_paginated( 10, 1, 'pending' );
		$this->assertNotEmpty( $items );

		$updated = $this->sug_repo->update_status( $sug_id, 'applied' );
		$this->assertNotFalse( $updated );

		$applied = $this->sug_repo->get_by_id( $sug_id );
		$this->assertEquals( 'applied', $applied->status );

		$this->sug_repo->delete( $sug_id );
		$this->assertFalse( $this->sug_repo->exists_for_post_and_link( $post_id, 999 ) );
	}
}
