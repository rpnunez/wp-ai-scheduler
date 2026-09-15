<?php
/**
 * Test Template Language Persistence and Roundtrip
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Template_Language_Roundtrip extends WP_UnitTestCase {

	/**
	 * @var AIPS_Template_Repository
	 */
	private $repository;

	/**
	 * @var AIPS_Templates_Controller
	 */
	private $controller;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->repository = new AIPS_Template_Repository();
		$this->controller = new AIPS_Templates_Controller();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Clean up superglobals.
	 */
	public function tearDown(): void {
		$_POST    = array();
		$_GET     = array();
		$_REQUEST = array();
		parent::tearDown();
	}

	/**
	 * Test repository create and update persist and retrieve language properly.
	 */
	public function test_repository_create_and_update_preserves_language() {
		$id = $this->repository->create(
			array(
				'name'            => 'Spanish Roundtrip Template',
				'prompt_template' => 'Write about {{topic}}',
				'post_status'     => 'draft',
				'language'        => 'es',
			)
		);

		$this->assertNotFalse( $id );
		$template = $this->repository->get_by_id( $id );
		$this->assertNotNull( $template );
		$this->assertSame( 'es', $template->language );

		$updated = $this->repository->update( $id, array( 'language' => 'fr' ) );
		$this->assertTrue( $updated );

		$reloaded = $this->repository->get_by_id( $id );
		$this->assertNotNull( $reloaded );
		$this->assertSame( 'fr', $reloaded->language );
	}

	/**
	 * Test that cloning a template preserves its configured language.
	 */
	public function test_clone_template_preserves_language() {
		$id = $this->repository->create(
			array(
				'name'            => 'Original Japanese Template',
				'prompt_template' => 'Write in Japanese about {{topic}}',
				'post_status'     => 'draft',
				'language'        => 'ja',
			)
		);

		$_POST['nonce']       = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['template_id'] = $id;
		$_REQUEST             = $_POST;

		ob_start();
		try {
			$this->controller->ajax_clone_template();
		} catch ( WPAjaxDieStopException $e ) {
		} catch ( WPAjaxDieContinueException $e ) {
		}
		$output = ob_get_clean();

		$response = json_decode( $output, true );
		$this->assertTrue( $response['success'] );
		$new_id = $response['data']['template_id'];

		$cloned = $this->repository->get_by_id( $new_id );
		$this->assertNotNull( $cloned );
		$this->assertSame( 'ja', $cloned->language );
	}

	/**
	 * Test that saving and getting a template via AJAX roundtrips the language field.
	 */
	public function test_ajax_save_and_get_template_roundtrips_language() {
		$_POST['nonce']           = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['name']            = 'German AJAX Template';
		$_POST['prompt_template'] = 'Schreibe einen Artikel über {{topic}}';
		$_POST['language']        = 'de';
		$_REQUEST                 = $_POST;

		ob_start();
		try {
			$this->controller->ajax_save_template();
		} catch ( WPAjaxDieStopException $e ) {
		} catch ( WPAjaxDieContinueException $e ) {
		}
		$save_output = ob_get_clean();

		$save_response = json_decode( $save_output, true );
		$this->assertTrue( $save_response['success'] );
		$template_id = $save_response['data']['template_id'];

		$_POST                = array();
		$_POST['nonce']       = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['template_id'] = $template_id;
		$_REQUEST             = $_POST;

		ob_start();
		try {
			$this->controller->ajax_get_template();
		} catch ( WPAjaxDieStopException $e ) {
		} catch ( WPAjaxDieContinueException $e ) {
		}
		$get_output = ob_get_clean();

		$get_response = json_decode( $get_output, true );
		$this->assertTrue( $get_response['success'] );
		$this->assertSame( 'de', $get_response['data']['template']['language'] );
	}

	/**
	 * Test that invalid or unknown language code falls back to 'en'.
	 */
	public function test_ajax_save_template_invalid_language_falls_back_to_en() {
		$_POST['nonce']           = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['name']            = 'Fallback Language Template';
		$_POST['prompt_template'] = 'Write about {{topic}}';
		$_POST['language']        = 'xx-invalid-code';
		$_REQUEST                 = $_POST;

		ob_start();
		try {
			$this->controller->ajax_save_template();
		} catch ( WPAjaxDieStopException $e ) {
		} catch ( WPAjaxDieContinueException $e ) {
		}
		$save_output = ob_get_clean();

		$save_response = json_decode( $save_output, true );
		$this->assertTrue( $save_response['success'] );
		$template_id = $save_response['data']['template_id'];

		$saved = $this->repository->get_by_id( $template_id );
		$this->assertSame( 'en', $saved->language );
	}
}
