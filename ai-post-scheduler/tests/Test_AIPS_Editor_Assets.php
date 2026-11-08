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
		$this->assertArrayHasKey('models', $l10n);
		$this->assertArrayHasKey('componentOverrides', $l10n);
		$this->assertArrayHasKey('defaultAiConfig', $l10n);
		$this->assertArrayHasKey('slugVariants', $l10n['labels']);
	}

	public function test_register_classic_metabox_registers_per_component_boxes() {
		global $wp_meta_boxes;

		$this->assets->register_classic_metabox('post');

		$this->assertArrayHasKey(AIPS_Editor_Assets::METABOX_ID_TITLE, $wp_meta_boxes['post']['side']['high']);
		$this->assertArrayHasKey(AIPS_Editor_Assets::METABOX_ID_SLUG, $wp_meta_boxes['post']['side']['high']);
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

	public function test_render_classic_slug_metabox_outputs_permalink_field() {
		$post_id = $this->factory->post->create(array(
			'post_title' => 'Slug Widget Post',
			'post_name'  => 'slug-widget-post',
		));
		$post = get_post($post_id);

		ob_start();
		$this->assets->render_classic_component_metabox($post, array('args' => array('component' => 'slug', 'label' => 'Slug')));
		$html = ob_get_clean();

		$this->assertStringContainsString('data-component="slug"', $html);
		$this->assertStringContainsString('id="aips-editor-input-slug"', $html);
		$this->assertStringContainsString('slug-widget-post', $html);
		$this->assertStringContainsString('aips-accept-diff-btn', $html);
		$this->assertStringContainsString('aips-view-revisions-btn', $html);
		$this->assertStringContainsString('aips-gen-variants-btn', $html);
	}

	public function test_editor_scripts_sync_slug_with_wordpress_fields() {
		$classic = file_get_contents(AIPS_PLUGIN_DIR . 'assets/js/admin-editor-classic.js');
		$gutenberg = file_get_contents(AIPS_PLUGIN_DIR . 'assets/js/admin-editor-gutenberg.js');

		$this->assertStringContainsString("editPost({ slug: slugVal })", $gutenberg);
		$this->assertStringContainsString("getEditedPostAttribute('slug')", $gutenberg);
		$this->assertStringContainsString("component: 'slug'", $gutenberg);
		$this->assertStringContainsString('aips-editor-slug-panel', $gutenberg);
		$this->assertStringContainsString("payloadComponents.slug", $gutenberg);

		$this->assertStringContainsString("'slug' === component", $classic);
		$this->assertStringContainsString("payloadComponents.slug", $classic);
		$this->assertStringContainsString("#post_name", $classic);
		$this->assertStringContainsString('#aips-editor-input-slug', $classic);
		$this->assertStringContainsString('#editable-post-name', $classic);
	}

	public function test_post_images_metabox_lists_content_images_in_placeholder_order() {
		global $wp_meta_boxes;

		$this->assets->register_classic_metabox('post');
		$this->assertArrayHasKey(AIPS_Editor_Assets::METABOX_ID_POST_IMAGES, $wp_meta_boxes['post']['normal']['default']);

		$post_id = $this->factory->post->create();
		$repo = new AIPS_Post_Images_Generated_Repository();
		$second_id = $repo->create(array(
			'post_id'     => $post_id,
			'image_index' => 2,
			'status'      => 'completed',
			'image_url'   => 'http://example.com/second.jpg',
		));
		$first_id = $repo->create(array(
			'post_id'     => $post_id,
			'image_index' => 1,
			'status'      => 'failed',
			'image_url'   => 'http://example.com/first.jpg',
		));

		$l10n = $this->assets->get_l10n($post_id);

		$this->assertCount(2, $l10n['postImages']);
		$this->assertSame(1, $l10n['postImages'][0]['imageIndex']);
		$this->assertSame((int) $first_id, $l10n['postImages'][0]['id']);
		$this->assertSame('failed', $l10n['postImages'][0]['status']);
		$this->assertSame(2, $l10n['postImages'][1]['imageIndex']);
		$this->assertSame((int) $second_id, $l10n['postImages'][1]['id']);

		$post = get_post($post_id);
		ob_start();
		$this->assets->render_classic_post_images_metabox($post);
		$html = ob_get_clean();

		$this->assertStringContainsString('id="aips-editor-post-images"', $html);
		$this->assertStringContainsString('id="aips-editor-post-images-regenerate-all"', $html);
	}

	public function test_editor_scripts_replace_only_the_targeted_post_image() {
		$classic = file_get_contents(AIPS_PLUGIN_DIR . 'assets/js/admin-editor-classic.js');
		$gutenberg = file_get_contents(AIPS_PLUGIN_DIR . 'assets/js/admin-editor-gutenberg.js');

		$this->assertStringContainsString('aips-editor-post-images-panel', $gutenberg);
		$this->assertStringContainsString("regenerate('post_image_' + image.id)", $gutenberg);
		$this->assertStringContainsString("regenerate('post_images')", $gutenberg);
		$this->assertStringContainsString('post_images_regenerated', $gutenberg);
		$this->assertStringContainsString("'core/block-editor'", $gutenberg);
		$this->assertStringContainsString('data-aips-image-id', $gutenberg);
		$this->assertStringContainsString('Accept & Replace', $gutenberg);

		$this->assertStringContainsString('data-aips-image-id', $classic);
		$this->assertStringContainsString('post_images_regenerated', $classic);
		$this->assertStringContainsString("tinymce.get('content')", $classic);
		$this->assertStringContainsString('#content', $classic);
		$this->assertStringContainsString("regenerate('post_image_' + imageId)", $classic);
		$this->assertStringContainsString("regenerate('post_images')", $classic);
	}
}
