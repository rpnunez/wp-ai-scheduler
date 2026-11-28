<?php
/**
 * Tests template settings persistence through the template service and repository.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Template_Repository_Persistence extends WP_UnitTestCase {
	/** @var int[] Template IDs created by these tests. */
	private $template_ids = array();

	public function tearDown(): void {
		$repository = new AIPS_Template_Repository();
		foreach ( $this->template_ids as $template_id ) {
			$repository->delete( $template_id );
		}
		$this->template_ids = array();
		parent::tearDown();
	}

	/**
	 * Create and update retain settings consumed by scheduled generation.
	 */
	public function test_template_create_and_update_persist_affiliate_flag_and_description() {
		$templates = new AIPS_Templates();
		$template_id = $templates->save( array(
			'name'                    => 'Affiliate setting test',
			'description'             => 'Initial template description',
			'prompt_template'         => 'Write about {{topic}}',
			'post_status'             => 'draft',
			'generate_slug'           => 1,
			'slug_prompt'             => 'Use a concise URL slug',
			'affiliate_links_enabled' => 1,
		) );

		$this->assertNotFalse( $template_id );
		$this->template_ids[] = (int) $template_id;

		$repository = new AIPS_Template_Repository();
		$template = $repository->get_by_id( $template_id );
		$this->assertSame( 'Initial template description', $template->description );
		$this->assertSame( 1, (int) $template->affiliate_links_enabled );
		$this->assertSame( 1, (int) $template->generate_slug );
		$this->assertSame( 'Use a concise URL slug', $template->slug_prompt );
		$this->assertTrue( ( new AIPS_Template_Context( $template ) )->get_affiliate_links_enabled() );

		$templates->save( array(
			'id'                      => $template_id,
			'name'                    => 'Affiliate setting test',
			'description'             => 'Updated template description',
			'prompt_template'         => 'Write about {{topic}}',
			'post_status'             => 'draft',
			'generate_slug'           => 0,
			'slug_prompt'             => '',
			'affiliate_links_enabled' => 0,
		) );

		$template = $repository->get_by_id( $template_id );
		$this->assertSame( 'Updated template description', $template->description );
		$this->assertSame( 0, (int) $template->affiliate_links_enabled );
		$this->assertSame( 0, (int) $template->generate_slug );
		$this->assertSame( '', $template->slug_prompt );
		$this->assertFalse( ( new AIPS_Template_Context( $template ) )->get_affiliate_links_enabled() );
	}
}
