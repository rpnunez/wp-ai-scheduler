<?php
/**
 * Tests for template clone behavior — specifically that post_type is
 * preserved on the clone rather than reverting to the 'post' default.
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Test_Templates_Clone_Stub {
	/**
	 * @var object|null
	 */
	public $source_template;

	/**
	 * @var array|null
	 */
	public $saved_data = null;

	public function get($id) {
		return $this->source_template;
	}

	public function save($data) {
		$this->saved_data = $data;
		return 456;
	}
}

class Test_AIPS_Templates_Controller_Clone extends WP_UnitTestCase {

	/** @var AIPS_Test_Templates_Clone_Stub */
	private $templates_stub;

	/** @var AIPS_Templates_Controller */
	private $controller;

	public function setUp(): void {
		parent::setUp();
		$admin_user = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($admin_user);

		$this->templates_stub = new AIPS_Test_Templates_Clone_Stub();
		$this->controller = new AIPS_Templates_Controller($this->templates_stub);
	}

	public function tearDown(): void {
		$_POST = array();
		$_REQUEST = array();
		wp_set_current_user(0);
		parent::tearDown();
	}

	public function test_clone_preserves_source_post_type() {
		$source = new stdClass();
		$source->name = 'Product Review Template';
		$source->description = '';
		$source->prompt_template = 'Write a review';
		$source->title_prompt = '';
		$source->voice_id = null;
		$source->post_quantity = 1;
		$source->image_prompt = '';
		$source->generate_featured_image = 0;
		$source->featured_image_source = 'ai_prompt';
		$source->featured_image_unsplash_keywords = '';
		$source->featured_image_media_ids = '';
		$source->post_status = 'draft';
		$source->post_type = 'product_review';
		$source->post_category = array();
		$source->post_tags = '';
		$source->post_author = 1;
		$source->include_sources = 0;
		$source->source_group_ids = '[]';
		$source->is_active = 1;

		$this->templates_stub->source_template = $source;

		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['template_id'] = '9';
		$_REQUEST = $_POST;

		ob_start();
		try {
			$this->controller->ajax_clone_template();
		} catch (WPAjaxDieStopException $e) {
			// Expected.
		} catch (WPAjaxDieContinueException $e) {
			// Expected in some environments.
		}
		$response = json_decode(ob_get_clean(), true);

		$this->assertTrue($response['success']);
		$this->assertNotNull($this->templates_stub->saved_data);
		$this->assertSame('product_review', $this->templates_stub->saved_data['post_type']);
	}
}
