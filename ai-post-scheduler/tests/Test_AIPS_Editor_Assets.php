<?php
/**
 * Test AIPS_Editor_Assets
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Editor_Assets extends WP_UnitTestCase {

	/**
	 * @var AIPS_Editor_Assets
	 */
	private $assets;

	/**
	 * @var int
	 */
	private $admin_id;

	public function setUp(): void {
		parent::setUp();

		$this->admin_id = $this->factory->user->create(array(
			'role' => 'administrator',
		));
		wp_set_current_user($this->admin_id);

		$this->assets = new AIPS_Editor_Assets();
	}

	public function tearDown(): void {
		wp_set_current_user(0);
		parent::tearDown();
	}

	public function test_is_supported_returns_true_for_public_post_types() {
		$this->assertTrue($this->assets->is_supported('post'));
		$this->assertTrue($this->assets->is_supported('page'));
	}

	public function test_is_supported_returns_false_for_unauthorized_user() {
		$subscriber_id = $this->factory->user->create(array('role' => 'subscriber'));
		wp_set_current_user($subscriber_id);

		$this->assertFalse($this->assets->is_supported('post'));
	}

	public function test_register_classic_metabox_registers_box() {
		global $wp_meta_boxes;

		$this->assets->register_classic_metabox('post');

		$this->assertIsArray($wp_meta_boxes);
		$this->assertArrayHasKey('post', $wp_meta_boxes);
		$this->assertArrayHasKey('side', $wp_meta_boxes['post']);
		$this->assertArrayHasKey('default', $wp_meta_boxes['post']['side']);
		$this->assertArrayHasKey(AIPS_Editor_Assets::METABOX_ID, $wp_meta_boxes['post']['side']['default']);
	}

	public function test_register_tinymce_button_adds_button() {
		$initial_buttons = array('bold', 'italic', 'link');
		$filtered        = $this->assets->register_tinymce_button($initial_buttons);

		$this->assertContains('aips_editor_tinymce_btn', $filtered);
		$this->assertCount(4, $filtered);
	}

	public function test_register_tinymce_plugin_adds_script() {
		$initial_plugins = array('wpeditimage' => '/path/to/image.js');
		$filtered        = $this->assets->register_tinymce_plugin($initial_plugins);

		$this->assertArrayHasKey('aips_editor_tinymce', $filtered);
		$this->assertStringContainsString('admin-editor-tinymce.js', $filtered['aips_editor_tinymce']);
	}

	public function test_get_l10n_contains_nonce_and_presets() {
		$l10n = $this->assets->get_l10n(42);

		$this->assertIsArray($l10n);
		$this->assertSame(42, $l10n['postId']);
		$this->assertNotEmpty($l10n['nonce']);
		$this->assertArrayHasKey('presets', $l10n);
		$this->assertArrayHasKey(AIPS_Editor_Directive_Service::PRESET_PUNCHY, $l10n['presets']);
		$this->assertArrayHasKey(AIPS_Editor_Directive_Service::PRESET_EXPAND, $l10n['presets']);
		$this->assertArrayHasKey(AIPS_Editor_Directive_Service::PRESET_SIMPLIFY, $l10n['presets']);
		$this->assertArrayHasKey(AIPS_Editor_Directive_Service::PRESET_TONE_SHIFT, $l10n['presets']);
	}

	public function test_register_classic_metabox_registers_per_component_boxes() {
		global $wp_meta_boxes;

		$this->assets->register_classic_metabox('post');

		$this->assertArrayHasKey(AIPS_Editor_Assets::METABOX_ID_TITLE, $wp_meta_boxes['post']['side']['high']);
		$this->assertArrayHasKey(AIPS_Editor_Assets::METABOX_ID_EXCERPT, $wp_meta_boxes['post']['side']['default']);
		$this->assertArrayHasKey(AIPS_Editor_Assets::METABOX_ID_CONTENT, $wp_meta_boxes['post']['normal']['default']);
		$this->assertArrayHasKey(AIPS_Editor_Assets::METABOX_ID_IMAGE, $wp_meta_boxes['post']['side']['default']);
	}

	public function test_render_classic_component_metabox_outputs_html() {
		$post_id = $this->factory->post->create(array('post_title' => 'Widget Post'));
		$post    = get_post($post_id);

		ob_start();
		$this->assets->render_classic_component_metabox($post, array('args' => array('component' => 'title', 'label' => 'Title')));
		$html = ob_get_clean();

		$this->assertStringContainsString('data-component="title"', $html);
		$this->assertStringContainsString('aips-regenerate-btn', $html);
		$this->assertStringContainsString('aips-view-revisions-btn', $html);
	}
}
