<?php
/**
 * Tests for Post Images Generation and Injection
 *
 * @package AI_Post_Scheduler
 */

require_once __DIR__ . '/Test_AIPS_Generator_Required_Content.php';

class Test_AIPS_Post_Images_Generation extends WP_UnitTestCase {

	private $generator;
	private $prompt_builder;

	public function setUp(): void {
		parent::setUp();
		$this->generator = new AIPS_Generator();
		$this->prompt_builder = new AIPS_Prompt_Builder_Post_Content();
	}

	public function test_prompt_builder_injects_image_placeholders_block() {
		$template = (object) array(
			'id'              => 0,
			'prompt_template' => 'Write an article about Artificial Intelligence.',
			'post_images'     => array(
				(object) array(
					'image_order' => 1,
					'placement'   => 'after_section_1',
				),
				(object) array(
					'image_order' => 2,
					'placement'   => 'auto',
				),
			),
		);

		$prompt = $this->prompt_builder->build($template, 'AI Trends');

		$this->assertStringContainsString('Image Placement Instructions:', $prompt);
		$this->assertStringContainsString('[post_image index="1"]', $prompt);
		$this->assertStringContainsString('after Section 1', $prompt);
		$this->assertStringContainsString('[post_image index="2"]', $prompt);
		$this->assertStringContainsString('place naturally', $prompt);
	}

	public function test_generator_inject_replaces_explicit_placeholder() {
		$content = "<p>Intro paragraph.</p>\n<p>[post_image index=\"1\"]</p>\n<p>Second paragraph.</p>";
		$markup  = '<figure class="wp-block-image size-large" data-aips-image-id="10"><img src="http://example.com/img.jpg" class="wp-image-1"/></figure>';

		$result = $this->generator->inject_single_post_image($content, 1, 'auto', $markup);

		$this->assertStringNotContainsString('[post_image index="1"]', $result);
		$this->assertStringContainsString('data-aips-image-id="10"', $result);
		$this->assertStringContainsString('Intro paragraph.', $result);
		$this->assertStringContainsString('Second paragraph.', $result);
	}

	public function test_generator_inject_fallback_after_section() {
		$content = "<h2>Section One</h2>\n<p>Paragraph under section one.</p>\n<h2>Section Two</h2>\n<p>Paragraph under section two.</p>";
		$markup  = '<figure class="wp-block-image" data-aips-image-id="20"><img src="http://example.com/img2.jpg"/></figure>';

		$result = $this->generator->inject_single_post_image($content, 1, 'after_section_1', $markup);

		$this->assertStringContainsString('data-aips-image-id="20"', $result);
		$pos_sec1 = strpos($result, 'Section One');
		$pos_markup = strpos($result, 'data-aips-image-id="20"');
		$pos_sec2 = strpos($result, 'Section Two');

		$this->assertGreaterThan($pos_sec1, $pos_markup);
		$this->assertLessThan($pos_sec2, $pos_markup);
	}

	public function test_generator_inject_fallback_auto_paragraph_distribution() {
		$content = "<p>First paragraph.</p>\n<p>Second paragraph.</p>\n<p>Third paragraph.</p>";
		$markup  = '<figure class="wp-block-image" data-aips-image-id="30"><img src="http://example.com/img3.jpg"/></figure>';

		$result = $this->generator->inject_single_post_image($content, 1, 'auto', $markup);

		$this->assertStringContainsString('data-aips-image-id="30"', $result);
		$this->assertStringContainsString('First paragraph.', $result);
	}

	public function test_generator_failed_image_placeholder_comment() {
		$content = "<p>Paragraph before.</p>\n[post_image index=\"1\"]\n<p>Paragraph after.</p>";
		$markup  = '<!-- aips-image-placeholder id="42" -->';

		$result = $this->generator->inject_single_post_image($content, 1, 'auto', $markup);

		$this->assertStringNotContainsString('[post_image index="1"]', $result);
		$this->assertStringContainsString('<!-- aips-image-placeholder id="42" -->', $result);
	}

	public function test_auto_fallback_spreads_images_across_paragraphs() {
		$content = str_repeat("<p>Paragraph.</p>\n", 10);

		for ($order = 1; $order <= 5; $order++) {
			$content = $this->generator->inject_single_post_image($content, $order, 'auto', "<figure id=\"f{$order}\"></figure>", 5);
		}

		$this->assertDoesNotMatchRegularExpression('/<\/figure>\s*<figure/', $content);
	}

	public function test_inject_keeps_dollar_amounts_in_markup_literal() {
		$content = '<p>[post_image index="1"]</p>';
		$markup  = '<figure><img alt="Best Laptops Under $1000"/></figure>';

		$result = $this->generator->inject_single_post_image($content, 1, 'auto', $markup);

		$this->assertStringContainsString('Best Laptops Under $1000', $result);
	}

	public function test_submitted_rows_are_unslashed_validated_and_limited() {
		update_option('aips_max_post_images_limit', 1);
		update_option('aips_allowed_image_sources', array('unsplash'));
		AIPS_Config::get_instance()->flush_option_cache();

		$rows = (new AIPS_Post_Images_Service())->normalize_submitted_rows(array(
			array('source' => 'ai_prompt', 'placement' => 'after_section_9', 'image_prompt' => 'a \"retro\" kitchen'),
			array('source' => 'unsplash'),
		));

		delete_option('aips_max_post_images_limit');
		delete_option('aips_allowed_image_sources');
		AIPS_Config::get_instance()->flush_option_cache();

		$this->assertCount(1, $rows);
		$this->assertSame('a "retro" kitchen', $rows[0]['image_prompt']);
		$this->assertSame('unsplash', $rows[0]['source']);
		$this->assertSame('auto', $rows[0]['placement']);
		$this->assertSame(1, $rows[0]['image_order']);
	}

	public function test_template_modal_keeps_the_image_prompt_field_id() {
		$image_field_name_prefix = 'featured_image';
		$image_field_id_prefix   = 'featured_image';
		$image_prompt_field_id   = 'image_prompt';
		$image_values            = array();
		$is_post_image           = false;

		ob_start();
		include AIPS_PLUGIN_DIR . 'templates/admin/partials/image-settings-field.php';
		$html = ob_get_clean();

		$this->assertStringContainsString('id="image_prompt"', $html);
		$this->assertStringContainsString('name="image_prompt"', $html);
	}

	public function test_post_is_held_as_draft_then_released_when_all_images_succeed() {
		$result = $this->generate_with_content_image(new AIPS_Test_Post_Image_Service(array('media' => $this->create_attachment())));

		$this->assertSame('draft', $result['manager']->created_post_data['post_status']);
		$this->assertSame(array('publish'), $result['manager']->forced_statuses);
		$this->assertTrue($result['manager']->component_statuses['post_images']);
		$this->assertFalse($result['manager']->generation_incomplete);
	}

	public function test_post_stays_draft_when_an_image_fails() {
		$result = $this->generate_with_content_image(new AIPS_Test_Post_Image_Service(array(
			'media' => new WP_Error('no_image', 'Image failed.'),
		)));

		$this->assertSame(array(), $result['manager']->forced_statuses);
		$this->assertFalse($result['manager']->component_statuses['post_images']);
		$this->assertTrue($result['manager']->generation_incomplete);
	}

	public function test_featured_image_request_alone_does_not_force_draft() {
		$manager = new AIPS_Test_Post_Images_Post_Manager(self::factory()->post->create());
		$image   = new AIPS_Test_Post_Image_Service(array('ai' => $this->create_attachment()));

		$generator = $this->make_generator($image, $manager, array('Body.', 'Title', 'Excerpt', 'An image prompt'));
		$generator->generate_post($this->make_context(array(
			'generate_featured_image' => true,
			'image_prompt'            => 'A photo',
		)));

		$this->assertSame('publish', $manager->created_post_data['post_status']);
	}

	public function test_saved_content_keeps_backslashes() {
		$post_id = self::factory()->post->create();
		$manager = new AIPS_Test_Post_Images_Post_Manager($post_id);
		$image   = new AIPS_Test_Post_Image_Service(array('media' => $this->create_attachment()));

		$generator = $this->make_generator($image, $manager, array('<p>Path C:\\Users\\admin</p><p>[post_image index="1"]</p>', 'Title', 'Excerpt'));
		$generator->generate_post($this->make_context(array('post_images' => array($this->media_image_config()))));

		$this->assertStringContainsString('C:\\Users\\admin', get_post_field('post_content', $post_id));
	}

	public function test_status_meta_tracks_post_images_and_reconciles_from_rows() {
		$post_id = self::factory()->post->create(array('post_excerpt' => 'Excerpt'));
		$manager = new AIPS_Post_Manager();
		$repo    = new AIPS_Post_Images_Generated_Repository();
		$row_id  = $repo->create(array('post_id' => $post_id, 'status' => 'failed'));

		$manager->update_generation_status_meta($post_id, array('post_title' => true, 'post_images' => false), true);

		$stored = json_decode(get_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_COMPONENT_STATUSES, true), true);
		$this->assertFalse($stored['post_images']);

		$this->assertFalse($manager->reconcile_generation_status_meta_from_post($post_id)['post_images']);

		$repo->update($row_id, array('status' => 'completed'));
		$this->assertTrue($manager->reconcile_generation_status_meta_from_post($post_id)['post_images']);
	}

	public function test_context_applies_the_configured_image_limit() {
		update_option('aips_max_post_images_limit', 1);
		AIPS_Config::get_instance()->flush_option_cache();

		$context = $this->make_context(array('post_images' => array($this->media_image_config(), $this->media_image_config())));
		$images  = $context->get_content_images();

		delete_option('aips_max_post_images_limit');
		AIPS_Config::get_instance()->flush_option_cache();

		$this->assertCount(1, $images);
	}

	public function test_regeneration_failure_keeps_the_existing_image() {
		$fixture = $this->make_regeneration_fixture(array('image_source' => 'ai_prompt'), new WP_Error('quota', 'Quota exceeded.'), array('source' => 'ai_prompt'));

		$result = $fixture['service']->regenerate_post_images($fixture['context'], 'all');

		$this->assertSame($fixture['context']['current_content'], $result['content']);
		$this->assertSame('Quota exceeded.', $result['post_images'][0]['error_message']);
		$this->assertSame('completed', $result['post_images'][0]['status']);

		$row = (new AIPS_Post_Images_Generated_Repository())->get_by_id($fixture['row_id']);
		$this->assertSame($fixture['attachment_id'], (int) $row->attachment_id);
	}

	public function test_regeneration_uses_config_keywords_and_does_not_save_the_post() {
		update_option('aips_unsplash_access_key', 'test-key');
		AIPS_Config::get_instance()->flush_option_cache();

		$attachment_id = $this->create_attachment();
		$fixture = $this->make_regeneration_fixture(
			array('image_source' => 'unsplash'),
			$attachment_id,
			array('source' => 'unsplash', 'unsplash_keywords' => 'mountain sunrise')
		);
		$stored_content = get_post_field('post_content', $fixture['post_id']);

		$result = $fixture['service']->regenerate_post_images($fixture['context'], $fixture['row_id']);

		delete_option('aips_unsplash_access_key');
		AIPS_Config::get_instance()->flush_option_cache();

		$this->assertSame('mountain sunrise', $fixture['image_service']->last_keywords);
		$this->assertStringContainsString('wp-image-' . $attachment_id, $result['content']);
		$this->assertStringContainsString('Unsaved edit', $result['content']);
		$this->assertSame($stored_content, get_post_field('post_content', $fixture['post_id']));
	}

	public function test_regeneration_selects_from_config_media_ids() {
		$attachment_id = $this->create_attachment();
		$fixture = $this->make_regeneration_fixture(
			array('image_source' => 'media_library'),
			$attachment_id,
			array('source' => 'media_library', 'media_ids' => (string) $attachment_id)
		);

		$result = $fixture['service']->regenerate_post_images($fixture['context'], 'all');

		$this->assertSame((string) $attachment_id, $fixture['image_service']->last_media_ids);
		$this->assertSame($attachment_id, $result['post_images'][0]['attachment_id']);
	}

	/**
	 * @param array $overrides Template overrides.
	 * @return AIPS_Template_Context
	 */
	private function make_context($overrides = array()) {
		$template = (object) array_merge(array(
			'id'                      => 0,
			'name'                    => 'Post Images Test',
			'prompt_template'         => 'Write the post body.',
			'title_prompt'            => 'Write the post title.',
			'image_prompt'            => '',
			'generate_featured_image' => false,
			'post_status'             => 'publish',
			'post_type'               => 'post',
			'post_category'           => '',
			'post_tags'               => '',
			'post_author'             => 1,
			'article_structure_id'    => null,
		), $overrides);

		return new AIPS_Template_Context($template, null, 'Test Topic', 'scheduled');
	}

	private function media_image_config() {
		return (object) array('image_order' => 1, 'source' => 'media_library', 'media_ids' => '1', 'placement' => 'auto');
	}

	private function create_attachment() {
		return self::factory()->attachment->create_object('photo.jpg', 0, array('post_mime_type' => 'image/jpeg'));
	}

	private function make_generator($image_service, $manager, $responses) {
		return new AIPS_Generator(
			null,
			new AIPS_Test_Generator_Required_Content_AI_Service($responses),
			null,
			$image_service,
			null,
			$manager,
			new AIPS_Test_Generator_Required_Content_History_Service()
		);
	}

	private function generate_with_content_image($image_service) {
		$manager   = new AIPS_Test_Post_Images_Post_Manager(self::factory()->post->create());
		$generator = $this->make_generator($image_service, $manager, array('<p>Body.</p><p>[post_image index="1"]</p>', 'Title', 'Excerpt'));

		$generator->generate_post($this->make_context(array('post_images' => array($this->media_image_config()))));

		return array('manager' => $manager);
	}

	/**
	 * Build a post with one generated image row whose figure is already in the content.
	 */
	private function make_regeneration_fixture($row_data, $service_result, $config_data) {
		$template_id = (new AIPS_Template_Repository())->create(array(
			'name'            => 'Regeneration Test',
			'prompt_template' => 'Prompt',
			'post_status'     => 'draft',
			'post_category'   => 0,
			'is_active'       => 1,
		));

		$config_id = (new AIPS_Post_Images_Repository())->create(array_merge(array(
			'entity_type' => 'template',
			'entity_id'   => $template_id,
			'image_order' => 1,
		), $config_data));

		$attachment_id = $this->create_attachment();
		$post_id       = self::factory()->post->create(array('post_title' => 'Regenerated Post'));
		$history_id    = (new AIPS_History_Repository())->create(array(
			'template_id' => $template_id,
			'post_id'     => $post_id,
			'status'      => 'completed',
		));

		$row_id = (new AIPS_Post_Images_Generated_Repository())->create(array_merge(array(
			'post_id'         => $post_id,
			'config_image_id' => $config_id,
			'image_index'     => 1,
			'attachment_id'   => $attachment_id,
			'image_url'       => 'http://example.org/photo.jpg',
		), $row_data));

		$image_service = new AIPS_Test_Post_Image_Service(array(
			'ai'       => $service_result,
			'unsplash' => $service_result,
			'media'    => $service_result,
		));

		$service  = new AIPS_Component_Regeneration_Service();
		$property = new ReflectionProperty(AIPS_Component_Regeneration_Service::class, 'generator');
		$property->setAccessible(true);
		$property->setValue($service, new AIPS_Generator(null, null, null, $image_service));

		$context = $service->get_generation_context($history_id);
		$context['post_id']         = $post_id;
		$context['history_id']      = $history_id;
		$context['current_title']   = 'Regenerated Post';
		$context['current_content'] = '<p>Unsaved edit</p><figure data-aips-image-id="' . $row_id . '"><img src="old.jpg"/></figure>';

		return compact('service', 'context', 'post_id', 'row_id', 'attachment_id', 'image_service');
	}
}

class AIPS_Test_Post_Image_Service extends AIPS_Image_Service {
	public $last_keywords = null;
	public $last_media_ids = null;
	private $results;

	public function __construct($results) {
		$this->results = $results;
	}

	public function has_unsplash_access_key(): bool {
		return true;
	}

	public function generate_and_upload_featured_image($image_prompt, $post_title) {
		return $this->results['ai'];
	}

	public function fetch_and_upload_unsplash_image($keywords, $post_title) {
		$this->last_keywords = $keywords;
		return $this->results['unsplash'];
	}

	public function select_media_library_image($media_ids) {
		$this->last_media_ids = $media_ids;
		return $this->results['media'];
	}
}

class AIPS_Test_Post_Images_Post_Manager {
	public $created_post_data = array();
	public $forced_statuses = array();
	public $component_statuses = array();
	public $generation_incomplete = null;
	private $post_id;

	public function __construct($post_id) {
		$this->post_id = $post_id;
	}

	public function create_post($data) {
		$this->created_post_data = $data;
		return $this->post_id;
	}

	public function update_generation_status_meta($post_id, $component_statuses = null, $generation_incomplete = null) {
		$this->component_statuses    = $component_statuses;
		$this->generation_incomplete = $generation_incomplete;
	}

	public function force_post_status($post_id, $post_status) {
		$this->forced_statuses[] = $post_status;
		return $post_id;
	}
}
