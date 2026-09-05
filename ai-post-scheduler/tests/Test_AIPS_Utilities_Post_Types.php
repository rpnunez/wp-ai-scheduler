<?php
/**
 * Tests for AIPS_Utilities::get_selectable_post_types() / is_selectable_post_type().
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Utilities_Post_Types extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		register_post_type('aips_util_cpt_with_tax', array(
			'public'     => true,
			'label'      => 'AIPS CPT With Taxonomies',
			'taxonomies' => array('category', 'post_tag'),
		));

		register_post_type('aips_util_cpt_no_tax', array(
			'public'     => true,
			'label'      => 'AIPS CPT Without Taxonomies',
			'taxonomies' => array(),
		));
	}

	public function tearDown(): void {
		unregister_post_type('aips_util_cpt_with_tax');
		unregister_post_type('aips_util_cpt_no_tax');
		parent::tearDown();
	}

	public function test_excludes_attachment() {
		$types = AIPS_Utilities::get_selectable_post_types();
		$this->assertArrayNotHasKey('attachment', $types);
	}

	public function test_includes_native_post_type() {
		$types = AIPS_Utilities::get_selectable_post_types();
		$this->assertArrayHasKey('post', $types);
		$this->assertTrue($types['post']['supports_category']);
		$this->assertTrue($types['post']['supports_post_tag']);
	}

	public function test_includes_registered_custom_post_type_with_taxonomy_support() {
		$types = AIPS_Utilities::get_selectable_post_types();
		$this->assertArrayHasKey('aips_util_cpt_with_tax', $types);
		$this->assertTrue($types['aips_util_cpt_with_tax']['supports_category']);
		$this->assertTrue($types['aips_util_cpt_with_tax']['supports_post_tag']);
	}

	public function test_reports_no_taxonomy_support_for_cpt_without_it() {
		$types = AIPS_Utilities::get_selectable_post_types();
		$this->assertArrayHasKey('aips_util_cpt_no_tax', $types);
		$this->assertFalse($types['aips_util_cpt_no_tax']['supports_category']);
		$this->assertFalse($types['aips_util_cpt_no_tax']['supports_post_tag']);
	}

	public function test_is_selectable_post_type() {
		$this->assertTrue(AIPS_Utilities::is_selectable_post_type('post'));
		$this->assertTrue(AIPS_Utilities::is_selectable_post_type('aips_util_cpt_with_tax'));
		$this->assertFalse(AIPS_Utilities::is_selectable_post_type('attachment'));
		$this->assertFalse(AIPS_Utilities::is_selectable_post_type('does_not_exist'));
	}
}
