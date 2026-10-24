<?php
/**
 * Tests for Modular Article Structures, Pattern Archetypes, Components & Delimiters.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Structure_Components_And_Archetypes extends WP_UnitTestCase {

	private $structure_repo;
	private $component_repo;
	private $section_rel_repo;
	private $selector;

	public function setUp(): void {
		parent::setUp();
		if (!class_exists('WP_Error')) {
			$this->markTestSkipped('WP test environment not available.');
		}

		AIPS_Cache_Factory::reset();

		$this->structure_repo   = new AIPS_Article_Structure_Repository();
		$this->component_repo   = AIPS_Component_Repository::instance();
		$this->section_rel_repo = AIPS_Structure_Section_Rel_Repository::instance();
		$this->selector         = new AIPS_Template_Type_Selector();
	}

	public function tearDown(): void {
		global $wpdb;
		if ($wpdb && !property_exists($wpdb, 'get_col_return_val')) {
			$wpdb->query("DELETE FROM {$wpdb->prefix}aips_article_structures WHERE name LIKE 'Test Archetype%'");
			$wpdb->query("DELETE FROM {$wpdb->prefix}aips_components WHERE slug LIKE 'test_%'");
		}
		delete_option('aips_default_article_structure_id');
		AIPS_Config::get_instance()->flush_option_cache();
		AIPS_Cache_Factory::reset();
		parent::tearDown();
	}

	/**
	 * Test AIPS_Structure_Delimiter_Parser for Gutenberg and Classic shortcodes.
	 */
	public function test_delimiter_parser_gutenberg_and_classic() {
		$parser = new AIPS_Structure_Delimiter_Parser();

		$raw_content = "Here is an introduction.\n\n" .
			"[STEP_LIST]\n1. First Step: Do this initial step.\n2. Second Step: Follow up with this.\n[/STEP_LIST]\n\n" .
			"[PROS]\n- Fast setup\n- Low latency\n[/PROS]\n\n" .
			"[CONS]\n- Requires subscription\n[/CONS]\n\n" .
			"[RATING score=\"9.2\" max=\"10\"]A great solution overall.[/RATING]\n\n" .
			"[FAQ]\nQ: Is it easy to configure?\nA: Yes, it configures in minutes.\n[/FAQ]\n\n" .
			"[CALLOUT type=\"tip\"]Remember to back up your data.[/CALLOUT]";

		// 1. Gutenberg Parse
		$gutenberg_result = $parser->parse($raw_content, 'gutenberg');
		$this->assertIsArray($gutenberg_result);
		$this->assertArrayHasKey('content', $gutenberg_result);
		$this->assertArrayHasKey('extracted_data', $gutenberg_result);

		// Verify Gutenberg core block comments
		$this->assertStringContainsString('<!-- wp:list {"ordered":true}', $gutenberg_result['content']);
		$this->assertStringContainsString('<!-- wp:columns', $gutenberg_result['content']);
		$this->assertStringContainsString('<!-- wp:details', $gutenberg_result['content']);

		// Verify Extracted Data for Schema
		$data = $gutenberg_result['extracted_data'];
		$this->assertNotEmpty($data['step_list']);
		$this->assertCount(2, $data['step_list']);
		$this->assertEquals('First Step', $data['step_list'][0]['title']);

		$this->assertNotEmpty($data['pros']);
		$this->assertNotEmpty($data['cons']);
		$this->assertEquals(9.2, $data['rating']['score']);

		$this->assertNotEmpty($data['faq']);
		$this->assertEquals('Is it easy to configure?', $data['faq'][0]['question']);

		// 2. Classic Shortcode Parse
		$classic_result = $parser->parse($raw_content, 'classic');
		$this->assertStringContainsString('[aips_step_list]', $classic_result['content']);
		$this->assertStringContainsString('[aips_pros_cons', $classic_result['content']);
		$this->assertStringContainsString('[aips_rating', $classic_result['content']);
		$this->assertStringContainsString('[aips_faq', $classic_result['content']);
		$this->assertStringContainsString('[aips_callout', $classic_result['content']);
	}

	/**
	 * Test AIPS_Structure_Schema_Builder generates valid JSON-LD schemas.
	 */
	public function test_schema_builder_generation() {
		$schema_builder = new AIPS_Structure_Schema_Builder();

		$extracted_data = array(
			'step_list' => array(
				array('step_number' => 1, 'title' => 'Download software', 'description' => 'Grab latest installer.'),
				array('step_number' => 2, 'title' => 'Install package', 'description' => 'Run installer.'),
			),
			'faq' => array(
				array('question' => 'Does it support Windows?', 'answer' => 'Yes, fully supported.'),
			),
			'rating' => array(
				'score' => 9.0,
				'max' => 10,
				'criteria' => array('Speed' => 9.5),
			),
		);

		$context = array(
			'title'       => 'How to Setup Testing',
			'description' => 'A step-by-step setup guide.',
			'url'         => 'https://example.com/how-to-setup',
		);

		$graph = $schema_builder->build_schema($extracted_data, $context);
		$this->assertIsArray($graph);
		$this->assertEquals('https://schema.org', $graph['@context']);
		$this->assertArrayHasKey('@graph', $graph);

		$types = array_column($graph['@graph'], '@type');
		$this->assertContains('HowTo', $types);
		$this->assertContains('FAQPage', $types);
		$this->assertContains('Review', $types);
	}

	/**
	 * Test AIPS_Structure_Shortcodes renders HTML.
	 */
	public function test_structure_shortcodes_render() {
		AIPS_Structure_Shortcodes::register();

		$pros_cons_html = do_shortcode('[aips_pros_cons pros="Fast, Clean" cons="Expensive"]');
		$this->assertStringContainsString('aips-pros-cons-box', $pros_cons_html);
		$this->assertStringContainsString('Fast', $pros_cons_html);
		$this->assertStringContainsString('Expensive', $pros_cons_html);

		$rating_html = do_shortcode('[aips_rating score="8.5" max="10"]Overall good value[/aips_rating]');
		$this->assertStringContainsString('aips-rating-box', $rating_html);
		$this->assertStringContainsString('8.5', $rating_html);

		$callout_html = do_shortcode('[aips_callout type="warning"]High voltage[/aips_callout]');
		$this->assertStringContainsString('aips-callout-box', $callout_html);
		$this->assertStringContainsString('High voltage', $callout_html);
	}

	/**
	 * Test AIPS_Component_Repository CRUD and default restoration.
	 */
	public function test_component_repository() {
		global $wpdb;
		if ($wpdb && property_exists($wpdb, 'get_col_return_val')) {
			$this->markTestSkipped('Database tests cannot run with mocked wpdb.');
		}

		$created_id = $this->component_repo->create(array(
			'name'             => 'Test Step List',
			'slug'             => 'test_step_list',
			'archetype'        => 'how_to',
			'description'      => 'A test step list',
			'prompt_blueprint' => 'Instructions for testing',
			'is_active'        => 1,
		));

		$this->assertGreaterThan(0, $created_id);

		$found = $this->component_repo->get_by_slug('test_step_list');
		$this->assertNotNull($found);
		$this->assertEquals('Test Step List', $found->name);

		// Test delete
		$deleted = $this->component_repo->delete($created_id);
		$this->assertTrue($deleted);
		$this->assertNull($this->component_repo->get_by_slug('test_step_list'));

		// Test restore defaults
		$restored = $this->component_repo->restore_defaults();
		$this->assertGreaterThanOrEqual(0, $restored);
	}

	/**
	 * Test AIPS_Structure_Section_Rel_Repository atomic sync and clone.
	 */
	public function test_structure_section_rel_sync_and_clone() {
		global $wpdb;
		if ($wpdb && property_exists($wpdb, 'get_col_return_val')) {
			$this->markTestSkipped('Database tests cannot run with mocked wpdb.');
		}

		$struct_id = $this->structure_repo->create(array(
			'name'           => 'Test Archetype Structure',
			'description'    => 'Structure with relational sections',
			'archetype'      => 'review',
			'editor_mode'    => 'gutenberg',
			'enable_schema'  => 1,
			'structure_data' => wp_json_encode(array('sections' => array())),
			'is_active'      => 1,
		));

		$sections_to_sync = array(
			array(
				'section_key'       => 'scorecard',
				'heading_text'      => 'Product Scorecard',
				'heading_level'     => 'h2',
				'target_word_count' => 150,
				'custom_prompt'     => 'Use [RATING] delimiter.',
			),
			array(
				'section_key'       => 'pros_cons',
				'heading_text'      => 'Pros & Cons',
				'heading_level'     => 'h3',
				'target_word_count' => 200,
				'custom_prompt'     => 'Use [PROS] and [CONS] delimiters.',
			),
		);

		$sync_result = $this->section_rel_repo->sync_structure_sections($struct_id, $sections_to_sync);
		$this->assertTrue($sync_result);

		$retrieved = $this->section_rel_repo->get_by_structure_id($struct_id);
		$this->assertCount(2, $retrieved);
		$this->assertEquals('scorecard', $retrieved[0]['section_key']);
		$this->assertEquals('pros_cons', $retrieved[1]['section_key']);

		// Clone structure
		$cloned_id = $this->structure_repo->clone($struct_id);
		$this->assertGreaterThan(0, $cloned_id);

		$cloned_sections = $this->section_rel_repo->get_by_structure_id($cloned_id);
		$this->assertCount(2, $cloned_sections);
		$this->assertEquals('scorecard', $cloned_sections[0]['section_key']);
	}

	/**
	 * Test 5-tier template type selector precedence hierarchy.
	 */
	public function test_template_type_selector_5_tier_hierarchy() {
		global $wpdb;
		if ($wpdb && property_exists($wpdb, 'get_col_return_val')) {
			$this->markTestSkipped('Database tests cannot run with mocked wpdb.');
		}

		// Create 5 test structures
		$site_default_id = $this->structure_repo->create(array('name' => 'Test Archetype Tier 5 Site Default', 'structure_data' => '{}', 'is_active' => 1));
		$template_struct_id = $this->structure_repo->create(array('name' => 'Test Archetype Tier 4 Template', 'structure_data' => '{}', 'is_active' => 1));
		$campaign_struct_id = $this->structure_repo->create(array('name' => 'Test Archetype Tier 3 Campaign', 'structure_data' => '{}', 'is_active' => 1));
		$author_struct_id   = $this->structure_repo->create(array('name' => 'Test Archetype Tier 2 Author', 'structure_data' => '{}', 'is_active' => 1));
		$schedule_struct_id = $this->structure_repo->create(array('name' => 'Test Archetype Tier 1 Schedule', 'structure_data' => '{}', 'is_active' => 1));

		update_option('aips_default_article_structure_id', $site_default_id);
		AIPS_Config::get_instance()->flush_option_cache();

		// Tier 5: Only site default available
		$res5 = $this->selector->get_effective_article_structure();
		$this->assertEquals($site_default_id, $res5);

		// Tier 4: Template specified
		$template = (object) array('article_structure_id' => $template_struct_id);
		$res4 = $this->selector->get_effective_article_structure($template);
		$this->assertEquals($template_struct_id, $res4);

		// Tier 3: Campaign override
		$campaign = (object) array('article_structure_id' => $campaign_struct_id);
		$res3 = $this->selector->get_effective_article_structure($template, null, null, $campaign);
		$this->assertEquals($campaign_struct_id, $res3);

		// Tier 2: Author override
		$author = (object) array('article_structure_id' => $author_struct_id);
		$res2 = $this->selector->get_effective_article_structure($template, null, $author, $campaign);
		$this->assertEquals($author_struct_id, $res2);

		// Tier 1: Schedule override
		$schedule = (object) array('article_structure_id' => $schedule_struct_id);
		$res1 = $this->selector->get_effective_article_structure($template, $schedule, $author, $campaign);
		$this->assertEquals($schedule_struct_id, $res1);
	}
}
