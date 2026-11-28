<?php
/**
 * Deep Lifecycle, Security Boundary & AI Edit Tests for Generation Components
 *
 * Covers:
 * - Post & Media components (Featured_Image, Content_Images, Content, Title, Excerpt, Slug,
 *   Taxonomy, Custom_Meta, Internal_Links, Affiliate_Links, Integration_Fields, Abstract_Post_Component)
 * - Security boundaries: non-image attachment rejection & Gutenberg block comment breakout XSS protection
 * - Author Topic single-component AI Edit & Topic_Embedding_Component (composite vectors, caching, cooldowns, indexing)
 * - Domain enrichment and suggestion components (Content_Audit_Pass, Taxonomy_Suggestion, Planner_Topics, Field_Suggestion)
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

require_once __DIR__ . '/support/class-aips-generation-test-harness.php';

class Test_AIPS_Generation_Components_Deep extends WP_UnitTestCase {

	/** @var AIPS_Generation_Test_AI_Service */
	private $ai_service;

	public function setUp(): void {
		parent::setUp();

		if (!taxonomy_exists('aips_source_group')) {
			register_taxonomy('aips_source_group', array(), array('public' => false));
		}

		$this->ai_service = new AIPS_Generation_Test_AI_Service();
		$ai               = $this->ai_service;
		AIPS_Container::get_instance()->singleton(AIPS_AI_Service_Interface::class, static function () use ($ai) {
			return $ai;
		});
	}

	// -------------------------------------------------------------------------
	// Post, Media & Security Boundary Components
	// -------------------------------------------------------------------------

	/**
	 * Featured_Image_Post_Component::apply_to_post() only accepts real image/*
	 * attachment posts and rejects regular posts, missing posts, zero IDs, and
	 * non-image attachments.
	 */
	public function test_featured_image_apply_to_post_enforces_image_attachment_security_boundary() {
		$post_id      = AIPS_Generation_Test_Fixtures::create_draft_post();
		$other_post   = AIPS_Generation_Test_Fixtures::create_draft_post(array('post_title' => 'Not An Attachment'));
		$txt_att_id   = AIPS_Generation_Test_Fixtures::create_attachment($post_id, 'text/plain');
		$image_att_id = AIPS_Generation_Test_Fixtures::create_attachment($post_id, 'image/jpeg');

		$component = new AIPS_Featured_Image_Post_Component();

		$this->assertFalse($component->apply_to_post(0, $image_att_id));
		$this->assertFalse($component->apply_to_post($post_id, 0));
		$this->assertFalse($component->apply_to_post($post_id, 9999999));
		$this->assertFalse($component->apply_to_post($post_id, $other_post), 'Regular post ID must not be accepted as thumbnail.');
		$this->assertFalse($component->apply_to_post($post_id, $txt_att_id), 'Non-image attachment must not be accepted as thumbnail.');
		$this->assertFalse($component->reconcile_status($post_id));

		$this->assertTrue($component->apply_to_post($post_id, array('attachment_id' => $image_att_id)));
		$this->assertSame($image_att_id, (int) get_post_thumbnail_id($post_id));
		$this->assertTrue($component->reconcile_status($post_id));
		$this->assertSame($image_att_id, $component->sanitize_value(array('attachment_id' => (string) $image_att_id)));
	}

	/**
	 * Featured_Image_Post_Component::generate() and regenerate() cover disabled,
	 * happy path with directive + History Container telemetry, missing post ID,
	 * empty prompt, and upload WP_Error branches.
	 */
	public function test_featured_image_generate_and_regenerate_lifecycle_and_errors() {
		$post_id      = AIPS_Generation_Test_Fixtures::create_draft_post(array('post_title' => 'Hero Architecture Post'));
		$image_att_id = AIPS_Generation_Test_Fixtures::create_attachment($post_id, 'image/png');

		$disabled_ctx = AIPS_Generation_Test_Fixtures::make_template_context(array('generate_featured_image' => 0));
		$enabled_ctx  = AIPS_Generation_Test_Fixtures::make_template_context(array(
			'generate_featured_image' => 1,
			'featured_image_source'   => 'media_library',
			'media_library_ids'       => (string) $image_att_id,
			'image_prompt'            => 'Blueprint diagram of {{topic}}',
		));

		$captured_prompt = '';
		$image_service   = new class($image_att_id, $captured_prompt) {
			public $next_return;
			public $captured_prompt;

			public function __construct($att_id, &$captured_prompt) {
				$this->next_return     = $att_id;
				$this->captured_prompt =& $captured_prompt;
			}

			public function generate_and_upload_featured_image($prompt, $title) {
				$this->captured_prompt = $prompt;
				return $this->next_return;
			}
		};

		$mock_fi_gen = $this->getMockBuilder(AIPS_Generator::class)
			->disableOriginalConstructor()
			->onlyMethods(array('resolve_and_attach_featured_image', 'process_featured_image_prompt'))
			->getMock();
		$mock_fi_gen->method('resolve_and_attach_featured_image')->willReturnCallback(
			static function ($ctx, $pid, $t, &$success) use ($image_att_id) {
				$success = true;
				return $image_att_id;
			}
		);
		$mock_fi_gen->method('process_featured_image_prompt')->willReturn('Blueprint diagram of WordPress Query Optimization');

		$component = new AIPS_Featured_Image_Post_Component($mock_fi_gen, $image_service);

		// 1. Disabled returns null; enabled resolves and returns attachment_id + success.
		$this->assertNull($component->generate($disabled_ctx, null, array('post_id' => $post_id)));
		$gen_res = $component->generate($enabled_ctx, null, array(
			'post_id' => $post_id,
			'title'   => 'Hero Architecture Post',
			'content' => '<p>Body</p>',
		));
		$this->assertSame($image_att_id, $gen_res['attachment_id']);
		$this->assertTrue($gen_res['success']);

		// 2. regenerate() missing post ID.
		$missing = $component->regenerate(0, $enabled_ctx);
		$this->assertWPError($missing);
		$this->assertSame('missing_post_id', $missing->get_error_code());

		// 3. regenerate() happy path with directive and real AIPS_History_Container.
		$history_service   = new AIPS_History_Service(new AIPS_History_Repository());
		$history_container = $history_service->create('ai_edit', array('post_id' => $post_id));

		$regen_ok = $component->regenerate($post_id, $enabled_ctx, null, 'isometric dark mode', array(
			'history_container' => $history_container,
		));
		$this->assertIsArray($regen_ok);
		$this->assertSame($image_att_id, $regen_ok['attachment_id']);
		$this->assertStringContainsString('isometric dark mode', $captured_prompt);

		// 4. regenerate() upload WP_Error records error + failed metric.
		$image_service->next_return = new WP_Error('upload_failed', 'Storage quota exceeded');
		$regen_err = $component->regenerate($post_id, $enabled_ctx, null, '', array(
			'history_container' => $history_container,
		));
		$this->assertWPError($regen_err);
		$this->assertSame('upload_failed', $regen_err->get_error_code());

		// 5. regenerate() no_image_prompt when prompt resolves to empty.
		$empty_prompt_gen = $this->getMockBuilder(AIPS_Generator::class)
			->disableOriginalConstructor()
			->onlyMethods(array('process_featured_image_prompt'))
			->getMock();
		$empty_prompt_gen->method('process_featured_image_prompt')->willReturn('');
		$empty_comp = new AIPS_Featured_Image_Post_Component($empty_prompt_gen, $image_service);
		$no_prompt  = $empty_comp->regenerate($post_id, $enabled_ctx, null, '', array(
			'history_container' => $history_container,
		));
		$this->assertWPError($no_prompt);
		$this->assertSame('no_image_prompt', $no_prompt->get_error_code());
	}

	/**
	 * Content_Images_Post_Component covers passthrough when disabled, regenerate
	 * guards, row creation + media resolution, apply_to_post, and reconcile_status.
	 */
	public function test_content_images_post_component_lifecycle_and_reconcile() {
		$post_id      = AIPS_Generation_Test_Fixtures::create_draft_post(array(
			'post_content' => "<p>Section one.</p>\n\n[post_image index=\"1\"]\n\n<p>Section two.</p>",
		));
		$image_att_id = AIPS_Generation_Test_Fixtures::create_attachment($post_id, 'image/jpeg');

		$disabled_ctx = AIPS_Generation_Test_Fixtures::make_template_context();
		$component    = new AIPS_Content_Images_Post_Component();

		$passthrough = $component->generate($disabled_ctx, null, array('content' => '<p>Unchanged</p>'));
		$this->assertSame(array('content' => '<p>Unchanged</p>', 'success' => true), $passthrough);

		$this->assertWPError($component->regenerate(0, $disabled_ctx));
		$no_images = $component->regenerate($post_id, $disabled_ctx);
		$this->assertWPError($no_images);
		$this->assertSame('no_images', $no_images->get_error_code());
		$this->assertTrue($component->reconcile_status($post_id), 'No generated rows should reconcile as true.');

		// Context with 1 configured content image (media_library).
		$images_ctx = new class($image_att_id) extends AIPS_Test_Abstract_Generation_Context_Stub {
			private $att_id;
			public function __construct($att_id) { $this->att_id = $att_id; }
			public function has_content_images() { return true; }
			public function get_content_images() {
				return array(
					(object) array(
						'id'                => 10,
						'placeholder_index' => 1,
						'source'            => 'media_library',
						'media_library_ids' => (string) $this->att_id,
						'image_prompt'      => 'Diagram of cache layers',
						'placement'         => 'auto',
						'caption'           => 'Cache Architecture',
					),
				);
			}
		};

		$mock_gen = $this->getMockBuilder(AIPS_Generator::class)
			->disableOriginalConstructor()
			->onlyMethods(array('resolve_post_image', 'build_post_image_markup'))
			->getMock();
		$mock_gen->method('resolve_post_image')->willReturnOnConsecutiveCalls(
			array(
				'attachment_id' => $image_att_id,
				'image_url'     => 'https://example.com/cache.jpg',
				'error'         => '',
			),
			array(
				'attachment_id' => $image_att_id,
				'image_url'     => 'https://example.com/cache.jpg',
				'error'         => '',
			),
			array(
				'attachment_id' => 0,
				'image_url'     => '',
				'error'         => 'Image provider timeout',
			)
		);
		$mock_gen->method('build_post_image_markup')->willReturn('<figure class="aips-post-image"><img src="https://example.com/cache.jpg" alt="Cache"/></figure>');

		$comp_with_gen = new AIPS_Content_Images_Post_Component($mock_gen);
		$regen         = $comp_with_gen->regenerate($post_id, $images_ctx, null, 'high contrast');
		$this->assertIsArray($regen);
		$this->assertCount(1, $regen['post_images']);
		$this->assertSame('completed', $regen['post_images'][0]['status']);
		$this->assertStringContainsString('<figure class="aips-post-image">', $regen['content']);

		$this->assertTrue($comp_with_gen->apply_to_post($post_id, $regen));
		$this->assertFalse($comp_with_gen->apply_to_post($post_id, array('content' => '')));

		// Failed image status causes reconcile_status() to return false.
		$gen_repo = new AIPS_Post_Images_Generated_Repository();
		$gen_repo->create(array(
			'post_id'       => $post_id,
			'history_id'    => 0,
			'image_index'   => 2,
			'attachment_id' => 0,
			'image_source'  => 'ai_prompt',
			'status'        => 'failed',
		));
		$this->assertFalse($comp_with_gen->reconcile_status($post_id));

		// Trigger failed resolve_post_image branch on newly created failed row.
		$regen_fail = $comp_with_gen->regenerate($post_id, $images_ctx, null, '', array('image_id' => 'all'));
		$this->assertIsArray($regen_fail);
	}

	/**
	 * Content_Post_Component::sanitize_value() preserves genuine Gutenberg block
	 * comments while stripping `--!>` comment breakout XSS and unsafe event attributes.
	 */
	public function test_content_post_component_sanitize_value_blocks_comment_breakout_xss() {
		$component = new AIPS_Content_Post_Component();

		$this->assertSame('', $component->sanitize_value(array('not-a-string')));

		$valid_blocks = "<!-- wp:paragraph {\"align\":\"center\"} -->\n<p>Hello <strong>Blocks</strong>.</p>\n<!-- /wp:paragraph -->";
		$sanitized_ok = $component->sanitize_value($valid_blocks);
		$this->assertStringContainsString('<!-- wp:paragraph {"align":"center"} -->', $sanitized_ok);
		$this->assertStringContainsString('<!-- /wp:paragraph -->', $sanitized_ok);

		$smuggled_xss = '<!-- wp:html --!><script>alert("xss")</script><img src="x" onerror="alert(2)"><!-- /wp:html -->';
		$sanitized_xss = $component->sanitize_value($smuggled_xss);
		$this->assertStringNotContainsString('<script>', $sanitized_xss);
		$this->assertStringNotContainsString('</script>', $sanitized_xss);
		$this->assertStringNotContainsString('--!>', $sanitized_xss);
		$this->assertStringNotContainsString('onerror', $sanitized_xss);
		$this->assertStringContainsString('<!-- /wp:html -->', $sanitized_xss);
	}

	/**
	 * Content_Post_Component covers generate (directive, leading h1 strip, WP_Error),
	 * regenerate (placeholder stripping + image restoration), and rewrite_with_directive.
	 */
	public function test_content_post_component_generate_regenerate_and_rewrite_with_directive() {
		$post_id = AIPS_Generation_Test_Fixtures::create_draft_post(array('post_content' => ''));
		$context = AIPS_Generation_Test_Fixtures::make_template_context();

		$this->ai_service
			->queue_text("<h1>Repeated Title</h1>\n\nParagraph one content.\n\nParagraph two content.")
			->queue_text(new WP_Error('content_err', 'Provider unavailable'))
			->queue_text("<p>Regenerated intro.</p>\n\n[post_image index=\"9\"]");

		$component = new AIPS_Content_Post_Component(new AIPS_Generator());
		$this->assertFalse($component->reconcile_status($post_id));

		$generated = $component->generate($context, null, array(
			'custom_directive' => 'Include concrete SQL examples',
			'temperature'      => 0.5,
		));
		$this->assertIsString($generated);
		$this->assertStringNotContainsString('<h1>Repeated Title</h1>', $generated);
		$this->assertStringContainsString('Paragraph one content.', $generated);
		$text_calls = array_values(array_filter($this->ai_service->get_call_log(), static function ($entry) {
			return isset($entry['method']) && $entry['method'] === 'generate_text';
		}));
		$this->assertNotEmpty($text_calls);
		$this->assertStringContainsString('Include concrete SQL examples', $text_calls[0]['prompt']);

		$this->assertTrue($component->apply_to_post($post_id, $generated));
		$this->assertTrue($component->reconcile_status($post_id));

		// WP_Error propagation.
		$err = $component->generate($context);
		$this->assertWPError($err);
		$this->assertSame('content_err', $err->get_error_code());

		// regenerate() strips unresolved [post_image] placeholders.
		$regenerated = $component->regenerate($post_id, $context, null, 'Tighten intro');
		$this->assertIsString($regenerated);
		$this->assertStringNotContainsString('[post_image', $regenerated);

		// rewrite_with_directive(): empty, unavailable, single, 3-JSON, and 3-line fallback.
		$local_ai = new AIPS_Generation_Test_AI_Service();
		$local_ai->set_available(false);
		$unavail = $component->rewrite_with_directive('Body text', 'Shorten', 1, array('ai_service' => $local_ai));
		$this->assertWPError($unavail);
		$this->assertSame('ai_service_unavailable', $unavail->get_error_code());

		$local_ai->set_available(true);
		$empty = $component->rewrite_with_directive('   ', 'Shorten', 1, array('ai_service' => $local_ai));
		$this->assertWPError($empty);
		$this->assertSame('empty_content', $empty->get_error_code());

		$local_ai
			->queue_text('"Polished single rewrite."')
			->queue_text('["Option A", "Option B", "Option C"]')
			->queue_text("Fallback line 1\nFallback line 2\nFallback line 3");

		$single = $component->rewrite_with_directive('Draft sentence.', 'Make active', 1, array(
			'ai_service' => $local_ai,
			'post_title' => 'Performance Guide',
		));
		$this->assertSame(array('Polished single rewrite.'), $single);

		$three_json = $component->rewrite_with_directive('Draft sentence.', 'Vary tone', 3, array('ai_service' => $local_ai));
		$this->assertSame(array('Option A', 'Option B', 'Option C'), $three_json);

		$three_lines = $component->rewrite_with_directive('Draft sentence.', 'Vary tone', 3, array('ai_service' => $local_ai));
		$this->assertSame(array('Fallback line 1', 'Fallback line 2', 'Fallback line 3'), $three_lines);
	}

	/**
	 * Title, Excerpt, Slug, and Abstract_Post_Component cover generate, regenerate,
	 * generate_variants, empty-result WP_Errors, and backslash preservation via wp_slash().
	 */
	public function test_title_excerpt_slug_and_abstract_post_components_lifecycle_and_variants() {
		$post_id = AIPS_Generation_Test_Fixtures::create_draft_post();
		$context = AIPS_Generation_Test_Fixtures::make_template_context();

		$mock_gen = $this->getMockBuilder(AIPS_Generator::class)
			->disableOriginalConstructor()
			->onlyMethods(array(
				'set_conversation',
				'generate_title_for_context',
				'generate_excerpt_text',
				'generate_slug_from_context',
			))
			->getMock();

		$mock_gen->method('generate_title_for_context')->willReturnOnConsecutiveCalls(
			'  "Generated Post Title"  ',
			'Regenerated Post Title'
		);
		$mock_gen->method('generate_excerpt_text')->willReturnOnConsecutiveCalls(
			'  Generated summary excerpt.  ',
			'   '
		);
		$mock_gen->method('generate_slug_from_context')->willReturnOnConsecutiveCalls(
			'"Custom SEO Slug"',
			'   '
		);

		$variant_ai = new AIPS_Generation_Test_AI_Service();
		$variant_ai
			->queue_text('["Title V1", "Title V2", "Title V3"]')
			->queue_text('["Excerpt V1", "Excerpt V2"]')
			->queue_text('["Clean Slug One", "Clean Slug Two"]')
			->queue_text('["!!!", "???"]');

		// Title component + backslash preservation.
		$title_comp = new AIPS_Title_Post_Component($mock_gen);
		$this->assertSame('"Generated Post Title"', $title_comp->generate($context, null, array('content' => '<p>Body</p>')));
		$this->assertSame('Regenerated Post Title', $title_comp->regenerate($post_id, $context, null, 'Shorter'));
		$this->assertSame(
			array('Title V1', 'Title V2', 'Title V3'),
			$title_comp->generate_variants($post_id, $context, 3, array('ai_service' => $variant_ai))
		);
		$this->assertWPError($title_comp->generate_variants(9999999, $context, 3, array('ai_service' => $variant_ai)));
		$this->assertTrue($title_comp->apply(get_post($post_id), 'C:\Windows\System32 Debugging'));
		$this->assertSame('C:\Windows\System32 Debugging', get_post_field('post_title', $post_id));

		// Excerpt component + empty regenerate WP_Error + backslash preservation.
		$excerpt_comp = new AIPS_Excerpt_Post_Component($mock_gen);
		$this->assertSame('Generated summary excerpt.', $excerpt_comp->generate($context, null, array('title' => 'T', 'content' => 'C')));
		$empty_excerpt = $excerpt_comp->regenerate($post_id, $context, null, 'Concise');
		$this->assertWPError($empty_excerpt);
		$this->assertSame('excerpt_generation_failed', $empty_excerpt->get_error_code());
		$this->assertSame(
			array('Excerpt V1', 'Excerpt V2'),
			$excerpt_comp->generate_variants($post_id, $context, 2, array('ai_service' => $variant_ai))
		);
		$this->assertTrue($excerpt_comp->apply_to_post($post_id, 'Path C:\logs\app.log summary.'));
		$this->assertSame('Path C:\logs\app.log summary.', get_post_field('post_excerpt', $post_id));

		// Slug component + empty slug WP_Error + all-invalid variant slug WP_Error.
		$slug_comp = new AIPS_Slug_Post_Component($mock_gen);
		$this->assertSame('custom-seo-slug', $slug_comp->generate($context, null, array('title' => 'T', 'content' => 'C')));
		$empty_slug = $slug_comp->regenerate($post_id, $context, null, 'Short');
		$this->assertWPError($empty_slug);
		$this->assertSame('slug_generation_failed', $empty_slug->get_error_code());
		$this->assertSame(
			array('clean-slug-one', 'clean-slug-two'),
			$slug_comp->generate_variants($post_id, $context, 2, array('ai_service' => $variant_ai))
		);
		$bad_slug_variants = $slug_comp->generate_variants($post_id, $context, 2, array('ai_service' => $variant_ai));
		$this->assertWPError($bad_slug_variants);
		$this->assertSame('slug_generation_failed', $bad_slug_variants->get_error_code());

		// Abstract base generate_variants() loop partial break vs immediate error.
		$custom_post_comp = new class() extends AIPS_Abstract_Post_Component {
			public $calls = 0;
			public function get_key(): string { return 'custom_test'; }
			public function get_label(): string { return 'Custom Test'; }
			public function get_stage(): string { return self::STAGE_METADATA; }
			public function build_prompt(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null): string { return ''; }
			public function generate(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, array $options = array()) { return 'v'; }
			public function regenerate($target_id, AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, string $directive = '', array $options = array()) {
				$this->calls++;
				if ($this->calls === 1) {
					return 'first-variant';
				}
				return new WP_Error('stop_early', 'Second call failed');
			}
			public function apply_to_post(int $post_id, $value): bool { return true; }
			public function reconcile_status($target_id): bool { return true; }
			public function sanitize_value($value) { return strtoupper((string) $value); }
		};
		$this->assertSame(array('FIRST-VARIANT'), $custom_post_comp->generate_variants($post_id, $context, 3));
		$this->assertWPError($custom_post_comp->generate_variants($post_id, $context, 3));
	}

	/**
	 * Taxonomy, Custom_Meta, Internal_Links, Affiliate_Links, and Integration_Fields
	 * post components cover direct generation, fallback paths, and post persistence.
	 */
	public function test_enrichment_and_persistence_post_components_deep_paths() {
		$candidate_id = self::factory()->post->create(array(
			'post_title'  => 'Redis Object Cache Setup',
			'post_status' => 'publish',
		));
		$target_id = AIPS_Generation_Test_Fixtures::create_draft_post(array(
			'post_title'   => 'Scaling WooCommerce Checkout',
			'post_content' => '<p>Use persistent caching to reduce database load. Try managed hosting for lower TTFB.</p>',
		));
		$context = AIPS_Generation_Test_Fixtures::make_template_context();

		// 1. Taxonomy_Post_Component: suggest_and_assign_for_post (AI JSON + WP_Error fallback).
		$tax_comp = new AIPS_Taxonomy_Post_Component();
		$tax_ai   = new AIPS_Generation_Test_AI_Service();
		$tax_ai
			->queue_json(array('categories' => array('WooCommerce Engineering'), 'tags' => array('checkout', 'redis')))
			->queue_json(new WP_Error('json_err', 'AI failed'));

		$assigned = $tax_comp->suggest_and_assign_for_post($target_id, $tax_ai);
		$this->assertContains('WooCommerce Engineering', $assigned['categories']);
		$this->assertContains('redis', $assigned['tags']);

		$fallback_assigned = $tax_comp->suggest_and_assign_for_post($target_id, $tax_ai);
		$this->assertContains('Technology', $fallback_assigned['categories']);

		// 2. Custom_Meta_Post_Component: source_snapshots + custom_meta.
		$meta_comp = new AIPS_Custom_Meta_Post_Component();
		$meta_val  = $meta_comp->generate($context, null, array(
			'source_snapshots' => array(array('source_id' => 5)),
			'custom_meta'      => array('aips_reading_time' => '6 min', '!!!' => 'ignored'),
		));
		$this->assertArrayHasKey('aips_source_snapshots_used', $meta_val);
		$this->assertSame('6 min', $meta_val['aips_reading_time']);
		$this->assertTrue($meta_comp->apply_to_post($target_id, $meta_val));
		$this->assertSame('6 min', get_post_meta($target_id, 'aips_reading_time', true));

		// 3. Internal_Links_Post_Component: candidate discovery, weave_links, find_insertion_locations, apply & reconcile.
		$links_ai   = new AIPS_Generation_Test_AI_Service();
		$links_comp = new AIPS_Internal_Links_Post_Component(null, $links_ai);
		$candidates = $links_comp->get_candidate_posts($target_id, 5);
		$this->assertNotEmpty($candidates);
		$this->assertSame($candidate_id, $candidates[0]['id']);

		$this->assertWPError($links_comp->weave_links($target_id, '   '));
		$this->assertSame('no_candidates', $links_comp->weave_links($target_id, '<p>Text</p>', '', array('candidates' => array()))->get_error_code());

		$candidate_url = get_permalink($candidate_id);
		$links_ai
			->queue_text(wp_json_encode(array(
				'modified_html'  => '<p>Use <a href="' . $candidate_url . '">persistent caching</a> to reduce load.</p>',
				'inserted_links' => array(array('url' => $candidate_url, 'title' => 'Redis', 'anchor' => 'persistent caching')),
			)))
			->queue_text('Not JSON prose at all')
			->queue_text('[{"anchor":"caching","sentence":"Use persistent caching"}]');

		$woven = $links_comp->regenerate($target_id, $context, null, 'Prefer first paragraph');
		$this->assertIsArray($woven);
		$this->assertStringContainsString('<a href="', $woven['modified_html']);
		$this->assertFalse($links_comp->reconcile_status($target_id));
		$this->assertTrue($links_comp->apply_to_post($target_id, $woven));
		$this->assertTrue($links_comp->reconcile_status($target_id));

		$fallback_weave = $links_comp->weave_links($target_id, '<p>Plain content.</p>');
		$this->assertSame(array(), $fallback_weave['inserted_links']);

		$locations = $links_comp->find_insertion_locations('Find anchor slots', 300);
		$this->assertIsArray($locations);
		$this->assertSame('caching', $locations[0]['anchor']);

		// 4. Affiliate_Links_Post_Component: missing URL, WP_Error, unmatched sentence, and apply_to_post.
		$aff_ai   = new AIPS_Generation_Test_AI_Service();
		$aff_comp = new AIPS_Affiliate_Links_Post_Component(null, $aff_ai);
		$orig     = '<p>Try managed hosting for lower TTFB.</p>';
		$this->assertSame($orig, $aff_comp->generate($context, null, array('content' => $orig, 'affiliate_url' => '')));

		$aff_ai
			->queue_json(new WP_Error('aff_err', 'Timeout'))
			->queue_json(array('match' => 'Sentence that does not exist.', 'replacement' => '[[Sentence]] that does not exist.'));
		$this->assertSame($orig, $aff_comp->inject_affiliate_link($orig, 'https://example.com/aff', 'hosting'));
		$this->assertSame($orig, $aff_comp->inject_affiliate_link($orig, 'https://example.com/aff', 'hosting'));
		$this->assertTrue($aff_comp->apply_to_post($target_id, '<p>Updated with <a href="https://example.com/aff" rel="nofollow sponsored">partner</a>.</p>'));

		// 5. Integration_Fields_Post_Component: generate_single_field + scalar/array apply_to_post.
		$int_ai   = new AIPS_Generation_Test_AI_Service();
		$int_ai->queue_text('Single ACF summary value');
		$int_comp = new AIPS_Integration_Fields_Post_Component(null, $int_ai);
		$this->assertSame(array(), $int_comp->generate($context));
		$single_val = $int_comp->generate_single_field(array('label' => 'Summary', 'name' => 'acf_summary', 'type' => 'text'), $context);
		$this->assertSame('Single ACF summary value', $single_val);
		$this->assertFalse($int_comp->apply_to_post($target_id, array()));
		$this->assertTrue($int_comp->apply_to_post($target_id, array(
			'acf_summary' => 'Scalar Value',
			'acf_specs'   => array('ram' => '16GB'),
		)));
		$this->assertSame('Scalar Value', get_post_meta($target_id, 'acf_summary', true));
		$this->assertSame('{"ram":"16GB"}', get_post_meta($target_id, 'acf_specs', true));
	}

	// -------------------------------------------------------------------------
	// Author Topic Single-Component AI Edit & Topic Embeddings
	// -------------------------------------------------------------------------

	/**
	 * Topic_Title, Topic_Prompt, Topic_Keywords, and Topic_Score components cover
	 * raw_item extraction, live AI generate, regenerate with DB state + directive,
	 * generate_variants, DB persistence via apply(), and reconcile_status().
	 */
	public function test_author_topic_components_ai_edit_persistence_and_reconcile() {
		$fixture  = AIPS_Generation_Test_Fixtures::create_persisted_author_and_topic();
		$topic_id = $fixture['topic_id'];
		$context  = new AIPS_Author_Topic_Generation_Context($fixture['author'], false, 'manual');

		// 1. Topic_Title_Component.
		$title_ai = new AIPS_Generation_Test_AI_Service();
		$title_ai
			->queue_text('"Zero-Trust REST Authentication in WordPress"')
			->queue_text('"Capability-Driven REST Security"')
			->queue_text('Variant Title One')
			->queue_text('Variant Title Two');
		$title_comp = new AIPS_Topic_Title_Component($title_ai);

		$this->assertSame('Raw Title', $title_comp->generate($context, null, array('raw_item' => array('title' => ' "Raw Title" '))));
		$this->assertSame('Zero-Trust REST Authentication in WordPress', $title_comp->generate($context, null, array('custom_directive' => 'Focus on OAuth')));

		$regen_title = $title_comp->regenerate($topic_id, $context, null, 'Make it sharper');
		$this->assertSame('Capability-Driven REST Security', $regen_title);
		$this->assertStringContainsString('Hardening REST API Endpoints in WordPress', $title_ai->get_last_call()['prompt']);
		$this->assertStringContainsString('Make it sharper', $title_ai->get_last_call()['prompt']);

		$title_variants = $title_comp->generate_variants($topic_id, $context, 2);
		$this->assertSame(array('Variant Title One', 'Variant Title Two'), $title_variants);

		$this->assertFalse($title_comp->apply(0, 'Valid Title'));
		$this->assertFalse($title_comp->apply($topic_id, '   '));
		$this->assertTrue($title_comp->apply($topic_id, $regen_title));
		$this->assertTrue($title_comp->reconcile_status($topic_id));
		$this->assertSame('Capability-Driven REST Security', (new AIPS_Author_Topics_Repository())->get_by_id($topic_id)->topic_title);

		// 2. Topic_Prompt_Component.
		$prompt_ai = new AIPS_Generation_Test_AI_Service();
		$prompt_ai
			->queue_text('Cover custom permission callbacks and rate limiting.')
			->queue_text('Angle A')
			->queue_text('Angle B');
		$prompt_comp = new AIPS_Topic_Prompt_Component($prompt_ai);

		$this->assertSame('Raw Prompt', $prompt_comp->generate($context, null, array('raw_item' => array('prompt' => ' Raw Prompt '))));
		$regen_prompt = $prompt_comp->regenerate($topic_id, $context, null, 'Add WP-CLI audit step');
		$this->assertSame('Cover custom permission callbacks and rate limiting.', $regen_prompt);
		$this->assertStringContainsString('Capability-Driven REST Security', $prompt_ai->get_last_call()['prompt']);
		$this->assertSame(array('Angle A', 'Angle B'), $prompt_comp->generate_variants($topic_id, $context, 2));
		$this->assertTrue($prompt_comp->apply((object) array('id' => $topic_id), $regen_prompt));
		$this->assertTrue($prompt_comp->reconcile_status($topic_id));

		// 3. Topic_Keywords_Component.
		$kw_ai = new AIPS_Generation_Test_AI_Service();
		$kw_ai
			->queue_text('rest-api, capabilities, nonces, rest-api')
			->queue_text('oauth, jwt');
		$kw_comp = new AIPS_Topic_Keywords_Component($kw_ai);

		$this->assertSame(array('a', 'b'), $kw_comp->generate($context, null, array('raw_item' => array('keywords' => array('a', 'b')))));
		$regen_kw = $kw_comp->regenerate($topic_id, $context, null, 'Emphasize authentication');
		$this->assertSame(array('rest-api', 'capabilities', 'nonces'), $regen_kw);
		$this->assertSame(array(array('oauth', 'jwt')), $kw_comp->generate_variants($topic_id, $context, 1));
		$this->assertFalse($kw_comp->apply(9999999, $regen_kw));
		$this->assertTrue($kw_comp->apply($topic_id, $regen_kw));
		$this->assertTrue($kw_comp->reconcile_status($topic_id));

		// 4. Topic_Score_Component.
		$score_ai = new AIPS_Generation_Test_AI_Service();
		$score_ai
			->queue_text('88')
			->queue_text('Score: 94 out of 100');
		$score_comp = new AIPS_Topic_Score_Component($score_ai);

		$this->assertSame(65, $score_comp->generate($context, null, array('raw_item' => array('score' => 65))));
		$this->assertSame(88, $score_comp->generate($context, null, array('topic_title' => 'REST Hardening')));
		$this->assertSame(94, $score_comp->regenerate($topic_id, $context, null, 'Strict technical bar'));
		$this->assertSame(array(), $score_comp->generate_variants($topic_id, $context, 3));
		$this->assertSame(50, $score_comp->sanitize_value('unparseable text'));
		$this->assertTrue($score_comp->apply($topic_id, 94));
		$this->assertTrue($score_comp->reconcile_status($topic_id));
		$this->assertSame(94, (int) (new AIPS_Author_Topics_Repository())->get_by_id($topic_id)->score);
	}

	/**
	 * Topic_Embedding_Component covers disabled/cooldown/empty guards, composite
	 * vector averaging across author profile + published posts, DB content_hash
	 * cache hits, transient cache hits, and non-rejected topic indexing enqueueing.
	 */
	public function test_topic_embedding_component_composite_vectors_caching_cooldown_and_indexing() {
		$user_id = self::factory()->user->create(array('role' => 'author'));
		$post_id = self::factory()->post->create(array(
			'post_author' => $user_id,
			'post_status' => 'publish',
			'post_title'  => 'Published Author Vector Post',
		));

		$author = (object) array(
			'id'              => 88,
			'name'            => 'Vector Specialist',
			'niche'           => 'Database Internals',
			'writing_style'   => 'empirical benchmarks',
			'target_audience' => 'DBAs',
			'post_author'     => $user_id,
		);
		$context = new AIPS_Author_Topic_Generation_Context($author, true, 'manual');

		// Stub embeddings repository in-memory.
		$embeddings_repo = new class($post_id) {
			public $store = array();
			public $upserts = 0;

			public function __construct($post_id) {
				$this->store['post:' . $post_id] = (object) array(
					'embedding'    => wp_json_encode(array(0.2, 0.8, 0.4)),
					'content_hash' => 'posthash',
				);
			}

			public function get_by_object($type, $id) {
				return $this->store[$type . ':' . $id] ?? null;
			}

			public function get_by_post_id($pid) {
				return $this->store['post:' . $pid] ?? null;
			}

			public function decode_embedding($raw, $type = '', $id = 0, $hash = '') {
				return is_array($raw) ? $raw : json_decode((string) $raw, true);
			}

			public function upsert($type, $id, $vec, $model, $dims, $hash) {
				$this->upserts++;
				$this->store[$type . ':' . $id] = (object) array(
					'embedding'    => wp_json_encode($vec),
					'content_hash' => $hash,
				);
				return true;
			}
		};

		$embeddings_svc = new class() {
			public $enabled = false;
			public $calls   = 0;
			public function is_enabled() { return $this->enabled; }
			public function get_active_model() { return 'text-embedding-3-small'; }
			public function generate_embedding($text) {
				$this->calls++;
				return array(0.6, 0.4, 0.8);
			}
		};

		$cooldown = false;
		$rate_limiter = $this->getMockBuilder(AIPS_Embeddings_Rate_Limiter::class)
			->disableOriginalConstructor()
			->onlyMethods(array('is_in_cooldown'))
			->getMock();
		$rate_limiter->method('is_in_cooldown')->willReturnCallback(static function () use (&$cooldown) {
			return $cooldown;
		});

		$enqueued_ids = array();
		$indexer = $this->getMockBuilder(AIPS_Content_Indexer_Service::class)
			->disableOriginalConstructor()
			->onlyMethods(array('enqueue_topics_for_indexing'))
			->getMock();
		$indexer->method('enqueue_topics_for_indexing')->willReturnCallback(static function ($ids) use (&$enqueued_ids) {
			$enqueued_ids = $ids;
		});

		$component = new AIPS_Topic_Embedding_Component($embeddings_svc, $embeddings_repo, $rate_limiter, $indexer);

		// 1. Disabled -> null.
		$this->assertFalse($component->is_enabled($context));
		$this->assertNull($component->generate($context));

		// 2. Enabled + cooldown -> null without calling generate_embedding().
		$embeddings_svc->enabled = true;
		$cooldown                = true;
		$this->assertNull($component->generate($context));
		$this->assertSame(0, $embeddings_svc->calls);

		// 3. Enabled + empty profile -> null.
		$cooldown = false;
		$this->assertNull($component->compute_author_composite_embedding((object) array('id' => 89)));

		// 4. Composite averaging: profile [0.6, 0.4, 0.8] + published post [0.2, 0.8, 0.4] => [0.4, 0.6, 0.6].
		$composite = $component->generate($context);
		$this->assertIsArray($composite);
		$this->assertEqualsWithDelta(array(0.4, 0.6, 0.6), $composite, 0.0001);
		$this->assertSame(1, $embeddings_svc->calls);
		$this->assertSame(1, $embeddings_repo->upserts);

		// 5. Transient hit does not call AI again.
		$cached_hit = $component->regenerate(88, $context);
		$this->assertEqualsWithDelta(array(0.4, 0.6, 0.6), $cached_hit, 0.0001);
		$this->assertSame(1, $embeddings_svc->calls);

		// 6. Clearing transient hits DB content_hash cache without calling AI.
		$author_row = $embeddings_repo->get_by_object('author', 88);
		delete_transient('aips_author_composite_vec_88_' . substr($author_row->content_hash, 0, 12));
		$db_hit = $component->generate($context);
		$this->assertEqualsWithDelta(array(0.4, 0.6, 0.6), $db_hit, 0.0001);
		$this->assertSame(1, $embeddings_svc->calls);

		// 7. apply() enqueues only non-rejected topic IDs; reconcile_status() checks repo.
		update_option('aips_indexer_topics_continuous_sync', 1);
		$this->assertTrue($component->apply(array(
			array('id' => 101, 'status' => 'pending'),
			array('id' => 102, 'status' => 'approved'),
			array('id' => 103, 'status' => 'rejected'),
			104,
		), null));
		$this->assertSame(array(101, 102, 104), $enqueued_ids);

		$this->assertFalse($component->reconcile_status(555));
		$embeddings_repo->upsert('topic', 555, array(0.1, 0.2), 'm', 2, 'h');
		$this->assertTrue($component->reconcile_status(555));
		$this->assertSame(array(1.5, 2.0), $component->sanitize_value(array('1.5', 2)));
	}

	/**
	 * Direct unit coverage for domain enrichment and suggestion components:
	 * Content_Audit_Pass, Taxonomy_Suggestion, Planner_Topics, and Field_Suggestion.
	 */
	public function test_audit_taxonomy_planner_and_field_suggestion_components_direct_paths() {
		// 1. Content_Audit_Pass_Component: invalid context, text_only mode, and unparseable fallback error.
		$audit_ai   = new AIPS_Generation_Test_AI_Service();
		$audit_comp = new AIPS_Content_Audit_Pass_Component($audit_ai);
		$this->assertWPError($audit_comp->generate(AIPS_Generation_Test_Fixtures::make_template_context()));

		$audit_ai->queue_text('Plain text gap report');
		$text_ctx = new AIPS_Content_Audit_Generation_Context('gap_fallback', 'Summarize gaps', array(), 0.5, true);
		$this->assertSame('Plain text gap report', $audit_comp->generate($text_ctx));

		$audit_ai
			->queue_json(new WP_Error('no_schema', 'Unsupported'))
			->queue_text('Completely unparseable prose without JSON');
		$json_ctx  = new AIPS_Content_Audit_Generation_Context('decay', 'Find decaying posts');
		$parse_err = $audit_comp->regenerate(0, $json_ctx);
		$this->assertWPError($parse_err);
		$this->assertSame('audit_json_parse_error', $parse_err->get_error_code());
		$this->assertTrue($audit_comp->apply(null, array('ok')));
		$this->assertSame(array(), $audit_comp->generate_variants(0, $json_ctx));

		// 2. Taxonomy_Suggestion_Component: unavailable, empty posts, numbered list parsing, exception guard.
		$tax_ai   = new AIPS_Generation_Test_AI_Service();
		$tax_comp = new AIPS_Taxonomy_Suggestion_Component($tax_ai);
		$post_id  = AIPS_Generation_Test_Fixtures::create_draft_post(array('post_title' => 'Kubernetes for WP'));
		$tax_ctx  = new AIPS_Taxonomy_Generation_Context('post_tag', array($post_id), 'Cloud tags');

		$tax_ai->set_available(false);
		$this->assertSame('ai_not_available', $tax_comp->generate($tax_ctx)->get_error_code());

		$tax_ai->set_available(true);
		$empty_tax_ctx = new AIPS_Taxonomy_Generation_Context('category', array(9999999));
		$this->assertSame('taxonomy_no_posts', $tax_comp->generate($empty_tax_ctx)->get_error_code());

		$tax_ai
			->queue_text("1. Containers\n2) Helm Charts\n- Autoscaling")
			->queue_text(static function () {
				throw new RuntimeException('Simulated transport crash');
			});
		$this->assertSame(array('Containers', 'Helm Charts', 'Autoscaling'), $tax_comp->generate($tax_ctx));
		$this->assertSame('ai_generation_failed', $tax_comp->regenerate(0, $tax_ctx)->get_error_code());

		// 3. Planner_Topics_Component: JSON fenced response, line fallback, empty AI response error.
		$this->ai_service
			->queue_text("```json\n[\"Topic Alpha\", \"Topic Beta\"]\n```")
			->queue_text("Line Topic 1\nLine Topic 2")
			->queue_text('   ');
		$planner_comp = new AIPS_Planner_Topics_Component(new AIPS_Generator());
		$planner_ctx  = new AIPS_Planner_Topic_Generation_Context('Block Themes', 2);

		$this->assertSame(array('Topic Alpha', 'Topic Beta'), $planner_comp->generate($planner_ctx));
		$this->assertSame(array('Line Topic 1', 'Line Topic 2'), $planner_comp->regenerate(0, $planner_ctx));
		$this->assertSame('empty_ai_response', $planner_comp->generate($planner_ctx)->get_error_code());
		$this->assertTrue($planner_comp->apply(null, array('Topic Alpha')));

		// 4. Field_Suggestion_Component: directive regeneration + persist_suggestion + apply guards.
		$field_ai   = new AIPS_Generation_Test_AI_Service();
		$field_ai->queue_text('  High-scale WooCommerce infrastructure  ');
		$field_comp = new AIPS_Field_Suggestion_Component($field_ai, new AIPS_AI_Assistance_Repository());
		$field_ctx  = new AIPS_AI_Assistance_Generation_Context(
			array(
				'form_context'  => 'authors',
				'form_field_id' => 'field_niche',
				'field_name'    => 'Niche',
				'context'       => array('role' => 'Architect'),
			),
			'sess_deep',
			1
		);
		$field_res = $field_comp->regenerate(0, $field_ctx, null, 'Focus on enterprise stores');
		$this->assertSame('High-scale WooCommerce infrastructure', $field_res['response']);
		$this->assertStringContainsString('Focus on enterprise stores', $field_res['prompt']);
		$this->assertFalse($field_comp->apply('not-a-context', $field_res));
		$this->assertTrue($field_comp->apply($field_ctx, $field_res));
		$this->assertSame(array(), $field_comp->generate_variants(0, $field_ctx));
	}
}
