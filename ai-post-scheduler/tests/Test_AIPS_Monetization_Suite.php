<?php
/**
 * Tests for Monetization & E-Commerce Suite
 *
 * Covers:
 * - AIPS_WooCommerce_Adapter
 * - AIPS_CPT_Affiliate_Adapter
 * - AIPS_Monetization_Shortcodes
 * - AIPS_Monetization_Processor
 * - AIPS_Commercial_Autolink_Service
 * - AIPS_Catalog_Enrichment_Controller
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Monetization_Suite extends WP_UnitTestCase {

	/**
	 * @var AIPS_WooCommerce_Adapter
	 */
	private $wc_adapter;

	/**
	 * @var AIPS_CPT_Affiliate_Adapter
	 */
	private $cpt_adapter;

	/**
	 * @var AIPS_Monetization_Shortcodes
	 */
	private $shortcodes;

	/**
	 * @var AIPS_Monetization_Processor
	 */
	private $processor;

	/**
	 * @var AIPS_Commercial_Autolink_Service
	 */
	private $autolinker;

	public function setUp(): void {
		parent::setUp();
		$this->wc_adapter  = new AIPS_WooCommerce_Adapter();
		$this->cpt_adapter = new AIPS_CPT_Affiliate_Adapter();
		$this->shortcodes  = new AIPS_Monetization_Shortcodes( $this->wc_adapter, $this->cpt_adapter );
		$this->processor   = new AIPS_Monetization_Processor();
		$this->autolinker  = new AIPS_Commercial_Autolink_Service( $this->wc_adapter );
	}

	public function tearDown(): void {
		delete_option( 'aips_monetization_enabled' );
		delete_option( 'aips_ad_injection_enabled' );
		delete_option( 'aips_ad_slot_code' );
		delete_option( 'aips_ftc_disclosure_enabled' );
		delete_option( AIPS_CPT_Affiliate_Adapter::OPTION_KEY );
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// 1. AIPS_WooCommerce_Adapter
	// -------------------------------------------------------------------------

	public function test_wc_adapter_is_active_returns_bool() {
		$this->assertIsBool( $this->wc_adapter->is_active() );
	}

	public function test_wc_adapter_get_product_generic_fallback() {
		$post_id = $this->factory->post->create(
			array(
				'post_title'   => 'Ergonomic Desk Chair',
				'post_content' => 'Full long description of the chair.',
				'post_excerpt' => 'Short summary for buy box.',
			)
		);
		update_post_meta( $post_id, '_price', '199.99' );

		$product = $this->wc_adapter->get_product( $post_id );
		$this->assertNotNull( $product );
		$this->assertSame( 'Ergonomic Desk Chair', $product['title'] );
		$this->assertSame( '199.99', $product['price'] );
		$this->assertSame( '$199.99', $product['formatted_price'] );
		$this->assertSame( 'Short summary for buy box.', $product['short_description'] );
		$this->assertTrue( $product['in_stock'] );
	}

	public function test_wc_adapter_update_product_enrichment() {
		$post_id = $this->factory->post->create(
			array(
				'post_title'   => 'Original Product Title',
				'post_content' => 'Old content',
				'post_excerpt' => 'Old excerpt',
			)
		);

		$result = $this->wc_adapter->update_product_enrichment(
			$post_id,
			array(
				'short_description' => 'Updated high-converting bullet points',
				'full_description'  => 'New enriched long copy with benefits',
			)
		);

		$this->assertTrue( $result );
		$updated_post = get_post( $post_id );
		$this->assertSame( 'Updated high-converting bullet points', $updated_post->post_excerpt );
		$this->assertSame( 'New enriched long copy with benefits', $updated_post->post_content );
	}

	// -------------------------------------------------------------------------
	// 2. AIPS_CPT_Affiliate_Adapter
	// -------------------------------------------------------------------------

	public function test_cpt_adapter_mapping_and_retrieval() {
		$post_id = $this->factory->post->create(
			array(
				'post_type'    => 'post',
				'post_title'   => 'Top VPN Deal',
				'post_content' => 'Full review of VPN.',
				'post_excerpt' => 'Best value VPN.',
			)
		);
		update_post_meta( $post_id, '_affiliate_url', 'https://example.com/deal?aff=123' );
		update_post_meta( $post_id, '_price', '2.99' );
		update_post_meta( $post_id, '_merchant', 'NordVPN' );

		$item = $this->cpt_adapter->get_item( $post_id );
		$this->assertNotNull( $item );
		$this->assertSame( 'Top VPN Deal', $item['title'] );
		$this->assertSame( 'https://example.com/deal?aff=123', $item['external_url'] );
		$this->assertSame( '2.99', $item['price'] );
		$this->assertSame( 'NordVPN', $item['merchant'] );
		$this->assertTrue( $item['is_external'] );
	}

	// -------------------------------------------------------------------------
	// 3. AIPS_Monetization_Shortcodes
	// -------------------------------------------------------------------------

	public function test_shortcode_product_card_rendering() {
		$post_id = $this->factory->post->create(
			array(
				'post_title'   => 'Wireless Noise Canceling Headphones',
				'post_excerpt' => 'Industry-leading noise cancellation.',
			)
		);
		update_post_meta( $post_id, '_price', '349.99' );

		$output = $this->shortcodes->render_product_card(
			array(
				'id'    => $post_id,
				'badge' => 'Top Pick',
			)
		);

		$this->assertStringContainsString( 'aips-product-card', $output );
		$this->assertStringContainsString( 'Top Pick', $output );
		$this->assertStringContainsString( 'Wireless Noise Canceling Headphones', $output );
		$this->assertStringContainsString( '$349.99', $output );
		$this->assertStringContainsString( 'data-product-id="' . $post_id . '"', $output );
	}

	public function test_shortcode_comparison_table_rendering() {
		$id1 = $this->factory->post->create( array( 'post_title' => 'Product Alpha' ) );
		$id2 = $this->factory->post->create( array( 'post_title' => 'Product Beta' ) );
		update_post_meta( $id1, '_price', '49.00' );
		update_post_meta( $id2, '_price', '89.00' );

		$output = $this->shortcodes->render_comparison_table(
			array(
				'ids'    => "{$id1},{$id2}",
				'badges' => 'Best Value,Editor Choice',
				'title'  => 'Head-to-Head Comparison',
			)
		);

		$this->assertStringContainsString( 'aips-comparison-table', $output );
		$this->assertStringContainsString( 'Head-to-Head Comparison', $output );
		$this->assertStringContainsString( 'Product Alpha', $output );
		$this->assertStringContainsString( 'Product Beta', $output );
		$this->assertStringContainsString( 'Best Value', $output );
		$this->assertStringContainsString( 'Editor Choice', $output );
	}

	public function test_shortcode_pros_cons_rendering() {
		$output = $this->shortcodes->render_pros_cons(
			array(
				'title' => 'Our Verdict',
				'pros'  => 'Super fast|Long battery life|Lightweight',
				'cons'  => 'High price|No headphone jack',
			)
		);

		$this->assertStringContainsString( 'aips-pros-cons-box', $output );
		$this->assertStringContainsString( 'Super fast', $output );
		$this->assertStringContainsString( 'Long battery life', $output );
		$this->assertStringContainsString( 'High price', $output );
		$this->assertStringContainsString( 'No headphone jack', $output );
	}

	public function test_shortcode_faq_rendering() {
		$output = $this->shortcodes->render_faq(
			array(
				'title' => 'Product FAQ',
				'items' => 'Does it have a warranty?|Yes, a 2-year warranty.||Is shipping free?|Yes, free next-day shipping.',
			)
		);

		$this->assertStringContainsString( 'aips-faq-container', $output );
		$this->assertStringContainsString( 'Does it have a warranty?', $output );
		$this->assertStringContainsString( 'Yes, a 2-year warranty.', $output );
		$this->assertStringContainsString( 'Is shipping free?', $output );
	}

	public function test_shortcode_ftc_disclosure_rendering() {
		$output = $this->shortcodes->render_ftc_disclosure( array() );
		$this->assertStringContainsString( 'aips-affiliate-disclosure', $output );
		$this->assertStringContainsString( 'This post contains affiliate links', $output );
	}

	// -------------------------------------------------------------------------
	// 4. AIPS_Monetization_Processor
	// -------------------------------------------------------------------------

	public function test_processor_transforms_ai_markers() {
		$content  = "Here is the top product:\n<!-- aips:product_card id=\"42\" badge=\"Top Pick\" -->\n\nAnd comparison:\n[COMPARISON_TABLE ids=\"1,2\"]\n";
		$rendered = $this->processor->transform_ai_markers( $content );

		$this->assertStringContainsString( '[aips_product_card id="42" badge="Top Pick"]', $rendered );
		$this->assertStringContainsString( '[aips_comparison_table ids="1,2"]', $rendered );
	}

	public function test_processor_paragraph_formatting_for_rpm() {
		$long_wall = "First sentence is here. Second sentence follows immediately. Third sentence concludes the point. Fourth sentence starts a new thought. Fifth sentence elaborates further. Sixth sentence finishes the idea.";
		$formatted = $this->processor->format_paragraphs_for_rpm( $long_wall );

		$paragraphs = explode( "\n\n", $formatted );
		$this->assertCount( 2, $paragraphs );
		$this->assertStringContainsString( 'First sentence is here', $paragraphs[0] );
		$this->assertStringContainsString( 'Fourth sentence starts a new thought', $paragraphs[1] );
	}

	public function test_processor_ad_slot_injection() {
		update_option( 'aips_ad_injection_enabled', true );
		update_option( 'aips_ad_slot_code', '<div class="ad-banner">AD_SLOT</div>' );
		update_option( 'aips_ad_paragraph_interval', 2 );

		$content = "Paragraph 1 text.\n\nParagraph 2 text.\n\nParagraph 3 text.\n\nParagraph 4 text.";
		$output  = $this->processor->inject_ad_slots( $content );

		$this->assertStringContainsString( 'aips-ad-slot-wrapper', $output );
		$this->assertStringContainsString( '<div class="ad-banner">AD_SLOT</div>', $output );
	}

	public function test_processor_prepends_ftc_disclosure_on_commercial_content() {
		$content = "Reviewing the best cameras.\n\n[aips_product_card id=\"12\"]";
		$output  = $this->processor->process_content( $content );

		$this->assertStringContainsString( '[aips_ftc_disclosure]', $output );
		$this->assertStringStartsWith( '[aips_ftc_disclosure]', $output );
	}

	// -------------------------------------------------------------------------
	// 5. AIPS_Commercial_Autolink_Service
	// -------------------------------------------------------------------------

	public function test_commercial_autolinking() {
		$post_id = $this->factory->post->create(
			array(
				'post_title' => 'SpeedMaster Running Shoe',
			)
		);
		update_post_meta( $post_id, '_price', '129.00' );

		$content = "If you want a great workout, the SpeedMaster Running Shoe is unmatched in comfort.";
		$linked  = $this->autolinker->autolink_products( $content, array( $post_id ), 1 );

		$this->assertStringContainsString( 'class="aips-commercial-autolink"', $linked );
		$this->assertStringContainsString( 'SpeedMaster Running Shoe</a>', $linked );
		$this->assertStringContainsString( 'data-product-id="' . $post_id . '"', $linked );
	}

	// -------------------------------------------------------------------------
	// 6. AIPS_Catalog_Enrichment_Controller
	// -------------------------------------------------------------------------

	public function test_catalog_enrichment_controller_actions_registered() {
		$controller = new AIPS_Catalog_Enrichment_Controller();
		$this->assertSame( 10, has_action( 'wp_ajax_aips_catalog_enrichment_list', array( $controller, 'ajax_list_products' ) ) );
		$this->assertSame( 10, has_action( 'wp_ajax_aips_catalog_enrichment_preview', array( $controller, 'ajax_preview_enrichment' ) ) );
		$this->assertSame( 10, has_action( 'wp_ajax_aips_catalog_enrichment_apply', array( $controller, 'ajax_apply_enrichment' ) ) );
	}
}
