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
		$this->assertSame($image->featured_image_id, $manager->featured_image_id);
		$this->assertGreaterThan(0, $manager->featured_image_id);
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
		$this->assertSame('completed', $row->status);
		$this->assertSame('', (string) $row->error_message);
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

	public function test_regeneration_leaves_the_row_unchanged_until_the_post_is_saved() {
		$new_attachment_id = $this->create_attachment();
		$fixture = $this->make_regeneration_fixture(
			array('image_source' => 'media_library'),
			$new_attachment_id,
			array('source' => 'media_library', 'media_ids' => (string) $new_attachment_id)
		);

		$result = $fixture['service']->regenerate_post_images($fixture['context'], 'all');

		$repo = new AIPS_Post_Images_Generated_Repository();
		$row  = $repo->get_by_id($fixture['row_id']);
		$this->assertSame($new_attachment_id, $result['post_images'][0]['attachment_id']);
		$this->assertSame($fixture['attachment_id'], (int) $row->attachment_id);

		(new AIPS_Post_Images_Service())->sync_generated_rows_from_content($fixture['post_id'], $result['content']);

		$row = $repo->get_by_id($fixture['row_id']);
		$this->assertSame($new_attachment_id, (int) $row->attachment_id);
		$this->assertSame(wp_get_attachment_url($new_attachment_id), $row->image_url);
		$this->assertSame('completed', $row->status);
	}

	public function test_failed_regeneration_does_not_report_an_image_to_the_modal() {
		$fixture = $this->make_regeneration_fixture(array('image_source' => 'ai_prompt'), new WP_Error('quota', 'Quota exceeded.'), array('source' => 'ai_prompt'));

		$result = $fixture['service']->regenerate_post_images($fixture['context'], 'all');

		$this->assertArrayNotHasKey('attachment_id', $result['post_images'][0]);
		$this->assertArrayNotHasKey('url', $result['post_images'][0]);
	}

	public function test_content_sync_marks_a_failed_row_completed_when_its_figure_is_saved() {
		$attachment_id = $this->create_attachment();
		$repo          = new AIPS_Post_Images_Generated_Repository();
		$post_id       = self::factory()->post->create();
		$row_id        = $repo->create(array('post_id' => $post_id, 'status' => 'failed', 'error_message' => 'Quota exceeded.'));

		$content = '<figure data-aips-image-id="' . $row_id . '"><img src="new.jpg" class="wp-image-' . $attachment_id . '"/></figure>';
		(new AIPS_Post_Images_Service())->sync_generated_rows_from_content($post_id, $content);

		$row = $repo->get_by_id($row_id);
		$this->assertSame($attachment_id, (int) $row->attachment_id);
		$this->assertSame('completed', $row->status);
		$this->assertSame('', (string) $row->error_message);
	}

	public function test_content_sync_ignores_rows_without_a_usable_figure() {
		$attachment_id = $this->create_attachment();
		$repo          = new AIPS_Post_Images_Generated_Repository();
		$post_id       = self::factory()->post->create();
		$missing_id    = $repo->create(array('post_id' => $post_id, 'attachment_id' => $attachment_id));
		$deleted_id    = $repo->create(array('post_id' => $post_id, 'attachment_id' => $attachment_id));

		$content = '<figure data-aips-image-id="' . $deleted_id . '"><img src="gone.jpg" class="wp-image-999999"/></figure>';
		(new AIPS_Post_Images_Service())->sync_generated_rows_from_content($post_id, $content);

		$this->assertSame($attachment_id, (int) $repo->get_by_id($missing_id)->attachment_id);
		$this->assertSame($attachment_id, (int) $repo->get_by_id($deleted_id)->attachment_id);
	}

	public function test_figure_pattern_does_not_match_a_longer_row_id() {
		$content = '<figure data-aips-image-id="123"><img src="a.jpg"/></figure>';

		$this->assertSame(0, preg_match(AIPS_Post_Images_Service::get_figure_pattern(12), $content));
		$this->assertSame(1, preg_match(AIPS_Post_Images_Service::get_figure_pattern(123), $content));
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
			'history_id'      => $history_id,
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

	public function test_zero_based_orders_are_numbered_as_the_nth_image() {
		$template = (object) array(
			'id'              => 0,
			'prompt_template' => 'Write an article.',
			'post_images'     => array(
				(object) array('image_order' => 0, 'placement' => 'auto'),
				(object) array('image_order' => 1, 'placement' => 'auto'),
				(object) array('image_order' => 2, 'placement' => 'after_section_2'),
			),
		);

		$prompt = $this->prompt_builder->build($template, 'Indexing');

		$this->assertSame(1, substr_count($prompt, '[post_image index="1"]'));
		$this->assertSame(1, substr_count($prompt, '[post_image index="2"]'));
		$this->assertSame(1, substr_count($prompt, '[post_image index="3"]'));
		$this->assertStringContainsString('after Section 2', $prompt);
	}

	public function test_generation_stores_the_nth_image_not_a_shifted_order() {
		$attachment_id = $this->create_attachment();
		$post_id       = self::factory()->post->create();
		$manager       = new AIPS_Test_Post_Images_Post_Manager($post_id);
		$generator     = $this->make_generator(
			new AIPS_Test_Post_Image_Service(array('media' => $attachment_id)),
			$manager,
			array(
				'<p>[post_image index="1"]</p><p>[post_image index="2"]</p><p>[post_image index="3"]</p><p>[post_image index="9"]</p>',
				'Title',
				'Excerpt',
			)
		);

		$generator->generate_post($this->make_context(array(
			'post_images' => array(
				(object) array('id' => 10, 'image_order' => 0, 'source' => 'media_library', 'media_ids' => (string) $attachment_id),
				(object) array('id' => 11, 'image_order' => 1, 'source' => 'media_library', 'media_ids' => (string) $attachment_id),
				(object) array('id' => 12, 'image_order' => 2, 'source' => 'media_library', 'media_ids' => (string) $attachment_id),
			),
		)));

		$rows = (new AIPS_Post_Images_Generated_Repository())->get_by_post_id($post_id);
		$this->assertSame(array(1, 2, 3), array_map(static function ($row) {
			return (int) $row->image_index;
		}, $rows));

		$content = get_post_field('post_content', $post_id);
		$this->assertStringNotContainsString('[post_image', $content);
		$this->assertLessThan(
			strpos($content, 'data-aips-image-id="' . $rows[2]->id . '"'),
			strpos($content, 'data-aips-image-id="' . $rows[1]->id . '"')
		);
		$this->assertSame(0, (int) get_post_thumbnail_id($post_id));
	}

	public function test_after_section_two_is_the_second_heading() {
		$content = "<h2>H1</h2>\n<p>A</p>\n<h2>H2</h2>\n<p>B</p>\n<h2>H3</h2>\n<p>C</p>";
		$result  = $this->generator->inject_single_post_image($content, 2, 'after_section_2', '<figure id="s2"></figure>', 3);

		$this->assertGreaterThan(strpos($result, 'H2'), strpos($result, 'id="s2"'));
		$this->assertLessThan(strpos($result, 'H3'), strpos($result, 'id="s2"'));
		$this->assertGreaterThan(strpos($result, '>B<'), strpos($result, 'id="s2"'));
	}

	public function test_even_distribution_keeps_image_two_ahead_of_image_three() {
		$content = '';
		for ($i = 1; $i <= 9; $i++) {
			$content .= "<p>P{$i}</p>\n";
		}

		foreach (array(1, 2, 3) as $order) {
			$content = $this->generator->inject_single_post_image($content, $order, 'auto', "<figure id=\"img-{$order}\"></figure>", 3);
		}

		$this->assertLessThan(strpos($content, 'id="img-2"'), strpos($content, 'id="img-1"'));
		$this->assertLessThan(strpos($content, 'id="img-3"'), strpos($content, 'id="img-2"'));
		$this->assertGreaterThan(strpos($content, 'P4'), strpos($content, 'id="img-2"'));
		$this->assertLessThan(strpos($content, 'P6'), strpos($content, 'id="img-2"'));
		$this->assertGreaterThan(strpos($content, 'P7'), strpos($content, 'id="img-3"'));
	}

	public function test_regenerating_the_second_image_uses_the_second_config_and_figure() {
		update_option('aips_unsplash_access_key', 'test-key');
		AIPS_Config::get_instance()->flush_option_cache();

		$template_id = (new AIPS_Template_Repository())->create(array(
			'name'            => 'Index Mapping',
			'prompt_template' => 'Prompt',
			'post_status'     => 'draft',
			'post_category'   => 0,
			'is_active'       => 1,
		));
		$repo = new AIPS_Post_Images_Repository();
		$keywords = array('alpha dawn', 'beta harbor', 'gamma peak');
		foreach ($keywords as $index => $keyword) {
			$repo->create(array(
				'entity_type'       => 'template',
				'entity_id'         => $template_id,
				'image_order'       => $index,
				'source'            => 'unsplash',
				'unsplash_keywords' => $keyword,
			));
		}

		$post_id    = self::factory()->post->create(array('post_title' => 'Indexed Post'));
		$history_id = (new AIPS_History_Repository())->create(array(
			'template_id' => $template_id,
			'post_id'     => $post_id,
			'status'      => 'completed',
		));
		$generated = new AIPS_Post_Images_Generated_Repository();
		$other_history = (new AIPS_History_Repository())->create(array(
			'template_id' => $template_id,
			'post_id'     => $post_id,
			'status'      => 'completed',
		));
		$row_ids = array();
		foreach (array(1, 2, 3) as $image_index) {
			$row_ids[] = $generated->create(array(
				'post_id'       => $post_id,
				'history_id'    => $history_id,
				'image_index'   => $image_index,
				'attachment_id' => $this->create_attachment(),
				'image_source'  => 'unsplash',
				'image_url'     => 'http://example.org/old-' . $image_index . '.jpg',
				'status'        => 'completed',
			));
		}
		$foreign_id = $generated->create(array(
			'post_id'       => $post_id,
			'history_id'    => $other_history,
			'image_index'   => 2,
			'attachment_id' => $this->create_attachment(),
			'image_source'  => 'unsplash',
			'status'        => 'completed',
		));

		$new_attachment = $this->create_attachment();
		$image_service  = new AIPS_Test_Post_Image_Service(array(
			'unsplash' => $new_attachment,
		));
		$service  = new AIPS_Component_Regeneration_Service();
		$property = new ReflectionProperty(AIPS_Component_Regeneration_Service::class, 'generator');
		$property->setAccessible(true);
		$property->setValue($service, new AIPS_Generator(null, null, null, $image_service));

		$context = $service->get_generation_context($history_id);
		$context['post_id']         = $post_id;
		$context['history_id']      = $history_id;
		$context['current_title']   = 'Indexed Post';
		$context['current_content'] = '<p>Unsaved</p>'
			. '<figure data-aips-image-id="' . $row_ids[0] . '"><img src="first.jpg"/></figure>'
			. '<figure data-aips-image-id="' . $row_ids[1] . '"><img src="second.jpg"/></figure>'
			. '<figure data-aips-image-id="' . $row_ids[2] . '"><img src="third.jpg"/></figure>';

		$result = $service->regenerate_post_images($context, $row_ids[1]);

		delete_option('aips_unsplash_access_key');
		AIPS_Config::get_instance()->flush_option_cache();

		$this->assertSame('beta harbor', $image_service->last_keywords);
		$this->assertStringContainsString('first.jpg', $result['content']);
		$this->assertStringContainsString('third.jpg', $result['content']);
		$this->assertStringContainsString('data-aips-image-id="' . $row_ids[1] . '"', $result['content']);
		$this->assertStringContainsString('wp-image-' . $new_attachment, $result['post_images'][0]['content']);
		$this->assertStringContainsString('first.jpg', $result['post_images'][0]['content']);
		$this->assertStringContainsString('third.jpg', $result['post_images'][0]['content']);
		$this->assertStringNotContainsString('second.jpg', $result['post_images'][0]['content']);
		$this->assertSame($foreign_id, (int) $generated->get_by_id($foreign_id)->id);
		$this->assertNotSame($new_attachment, (int) $generated->get_by_id($row_ids[1])->attachment_id);

		$foreign = $service->regenerate_post_images($context, $foreign_id);
		$this->assertWPError($foreign);
	}

	public function test_bulk_regeneration_isolates_each_accepted_figure() {
		update_option('aips_unsplash_access_key', 'test-key');
		AIPS_Config::get_instance()->flush_option_cache();

		$first = $this->make_regeneration_fixture(
			array('image_source' => 'unsplash', 'image_index' => 1),
			$this->create_attachment(),
			array('source' => 'unsplash', 'unsplash_keywords' => 'first sunrise', 'image_order' => 1)
		);
		$second_config = (new AIPS_Post_Images_Repository())->create(array(
			'entity_type'       => 'template',
			'entity_id'         => (int) $first['context']['generation_context']->get_id(),
			'image_order'       => 2,
			'source'            => 'unsplash',
			'unsplash_keywords' => 'second harbor',
		));
		$second_attachment = $first['attachment_id'];
		$second_id = (new AIPS_Post_Images_Generated_Repository())->create(array(
			'post_id'         => $first['post_id'],
			'history_id'      => $first['context']['history_id'],
			'config_image_id' => $second_config,
			'image_index'     => 2,
			'attachment_id'   => $second_attachment,
			'image_source'    => 'unsplash',
			'image_url'       => 'http://example.org/second.jpg',
			'status'          => 'completed',
		));
		$first['context']['current_content'] = '<figure data-aips-image-id="' . $first['row_id'] . '"><img src="first.jpg"/></figure>'
			. '<figure data-aips-image-id="' . $second_id . '"><img src="second.jpg"/></figure>';

		$result = $first['service']->regenerate_post_images($first['context'], 'all');

		delete_option('aips_unsplash_access_key');
		AIPS_Config::get_instance()->flush_option_cache();

		$by_id = array();
		foreach ($result['post_images'] as $image) {
			$by_id[$image['id']] = $image;
		}

		$this->assertStringContainsString('second.jpg', $by_id[$first['row_id']]['content']);
		$this->assertStringNotContainsString('first.jpg', $by_id[$first['row_id']]['content']);
		$this->assertStringContainsString('first.jpg', $by_id[$second_id]['content']);
		$this->assertStringNotContainsString('second.jpg', $by_id[$second_id]['content']);
		$this->assertStringNotContainsString('first.jpg', $result['content']);
		$this->assertStringNotContainsString('second.jpg', $result['content']);
	}

	public function test_sync_does_not_adopt_another_posts_figure_or_a_non_image() {
		$repo    = new AIPS_Post_Images_Generated_Repository();
		$post_a  = self::factory()->post->create();
		$post_b  = self::factory()->post->create();
		$image   = $this->create_attachment();
		$pdf     = self::factory()->attachment->create_object('file.pdf', 0, array('post_mime_type' => 'application/pdf'));
		$row_a   = $repo->create(array('post_id' => $post_a, 'attachment_id' => $image, 'status' => 'completed'));
		$row_b   = $repo->create(array('post_id' => $post_b, 'attachment_id' => $image, 'status' => 'completed'));
		$service = new AIPS_Post_Images_Service();

		$service->sync_generated_rows_from_content(
			$post_a,
			'<figure data-aips-image-id="' . $row_b . '"><img class="wp-image-' . $this->create_attachment() . '" src="stolen.jpg"/></figure>'
			. '<figure data-aips-image-id="' . $row_a . '"><img class="wp-image-' . $pdf . '" src="file.pdf"/></figure>'
		);

		$this->assertSame($image, (int) $repo->get_by_id($row_b)->attachment_id);
		$this->assertSame($image, (int) $repo->get_by_id($row_a)->attachment_id);
	}

	public function test_kses_keeps_the_generated_image_marker() {
		$this->assertNotFalse(has_filter('wp_kses_allowed_html', array('AIPS_Post_Images_Service', 'allow_figure_attributes')));

		$html = wp_kses_post('<figure class="wp-block-image" data-aips-image-id="5"><img src="http://example.com/a.jpg" alt="A" class="wp-image-5" /></figure>');

		$this->assertStringContainsString('data-aips-image-id="5"', $html);
	}

	public function test_topic_context_uses_author_images_and_template_context_uses_template_images() {
		$author = (object) array(
			'id'                              => 0,
			'name'                            => 'Ada',
			'featured_image_source'           => 'unsplash',
			'featured_image_unsplash_keywords' => 'author keywords',
			'image_prompt'                    => 'author featured prompt',
			'post_images'                     => array(
				(object) array('image_order' => 2, 'image_prompt' => 'author content', 'source' => 'media_library'),
			),
		);
		$topic = new AIPS_Topic_Context($author, (object) array('id' => 7, 'topic_title' => 'Topic'));

		$this->assertSame('unsplash', $topic->get_featured_image_source());
		$this->assertSame('author keywords', $topic->get_unsplash_keywords());
		$this->assertSame('author featured prompt', $topic->get_image_prompt());
		$this->assertSame('author content', $topic->get_content_images()[0]->image_prompt);
		$this->assertSame(1, (int) $topic->get_content_images()[0]->placeholder_index);

		$template = new AIPS_Template_Context((object) array(
			'id'                     => 0,
			'name'                   => 'Template',
			'featured_image_source'  => 'media_library',
			'featured_image_media_ids' => '4,5',
			'image_prompt'           => 'template featured',
			'post_images'            => array(
				(object) array('image_order' => 1, 'image_prompt' => 'template content'),
			),
		));

		$this->assertSame('media_library', $template->get_featured_image_source());
		$this->assertSame('4,5', $template->get_media_library_ids());
		$this->assertSame('template featured', $template->get_image_prompt());
		$this->assertSame('template content', $template->get_content_images()[0]->image_prompt);
	}

	public function test_disallowed_source_is_rejected() {
		update_option('aips_allowed_image_sources', array('unsplash'));
		AIPS_Config::get_instance()->flush_option_cache();

		$resolved = $this->generator->resolve_post_image(
			(object) array('source' => 'ai_prompt', 'image_prompt' => 'A photo'),
			'Title',
			'Topic'
		);

		delete_option('aips_allowed_image_sources');
		AIPS_Config::get_instance()->flush_option_cache();

		$this->assertSame(0, $resolved['attachment_id']);
		$this->assertNotSame('', $resolved['error']);
	}

	public function test_restore_distributes_a_missing_figure_among_all_images() {
		$repo       = new AIPS_Post_Images_Generated_Repository();
		$post_id    = self::factory()->post->create(array('post_title' => 'Restore'));
		$history_id = 8801;
		$attachment = $this->create_attachment();
		$row_ids    = array();
		foreach (array(1, 2, 3) as $image_index) {
			$row_ids[] = $repo->create(array(
				'post_id'       => $post_id,
				'history_id'    => $history_id,
				'image_index'   => $image_index,
				'attachment_id' => $attachment,
				'status'        => 'completed',
			));
		}

		$content = '';
		for ($i = 1; $i <= 9; $i++) {
			$content .= "<p>P{$i}</p>\n";
		}

		$service = new AIPS_Component_Regeneration_Service();
		$applied = $service->apply_restored_post_image(
			$post_id,
			$row_ids[2],
			array('attachment_id' => $attachment),
			$content,
			$history_id
		);

		$this->assertGreaterThan(strpos($applied['content'], 'P7'), strpos($applied['content'], 'data-aips-image-id="' . $row_ids[2] . '"'));
	}

	public function test_post_image_component_keys_are_distinct_from_featured_image() {
		$this->assertTrue(AIPS_Component_Regeneration_Service::is_ai_edit_component('featured_image'));
		$this->assertTrue(AIPS_Component_Regeneration_Service::is_ai_edit_component('post_images'));
		$this->assertTrue(AIPS_Component_Regeneration_Service::is_ai_edit_component('post_image_12'));
		$this->assertSame(12, AIPS_Component_Regeneration_Service::post_image_id_from_component('post_image_12'));
		$this->assertSame(0, AIPS_Component_Regeneration_Service::post_image_id_from_component('post_image_'));
		$this->assertSame(0, AIPS_Component_Regeneration_Service::post_image_id_from_component('featured_image'));
		$this->assertFalse(AIPS_Component_Regeneration_Service::is_ai_edit_component('post_image_0'));
		$this->assertFalse(AIPS_Component_Regeneration_Service::is_ai_edit_component('post_image_12abc'));
	}
}

class AIPS_Test_Post_Image_Service extends AIPS_Image_Service {
	public $last_keywords = null;
	public $last_media_ids = null;
	public $featured_image_id = 0;
	private $results;

	public function __construct($results) {
		$this->results = $results;
	}

	public function has_unsplash_access_key(): bool {
		return true;
	}

	public function generate_and_upload_featured_image($image_prompt, $post_title) {
		$this->featured_image_id = is_wp_error($this->results['ai']) ? 0 : (int) $this->results['ai'];
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
	public $featured_image_id = 0;
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

	public function set_featured_image($post_id, $attachment_id) {
		$this->featured_image_id = (int) $attachment_id;
		return true;
	}
}
