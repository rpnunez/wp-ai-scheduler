<?php
/**
 * Tests for AIPS_Email_Theme and AIPS_Email_View
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Email_View extends WP_UnitTestCase {

	public function tear_down() {
		AIPS_Email_Theme::flush_cache();
		remove_all_filters('aips_email_theme');
		parent::tear_down();
	}

	// -----------------------------------------------------------------------
	// AIPS_Email_Theme
	// -----------------------------------------------------------------------

	public function test_tokens_expose_the_design_palette() {
		$tokens = AIPS_Email_Theme::tokens();

		$this->assertSame('#3858e9', $tokens['color']['brand']);
		$this->assertSame('#eef1fd', $tokens['color']['brand_tint']);
		$this->assertSame('#f0f0f1', $tokens['color']['page']);
		$this->assertSame(600, $tokens['size']['container']);
	}

	public function test_token_reads_a_single_value_by_dot_path() {
		$this->assertSame('#3858e9', AIPS_Email_Theme::token('color.brand'));
		$this->assertSame('fallback', AIPS_Email_Theme::token('color.nope', 'fallback'));
		$this->assertSame('fallback', AIPS_Email_Theme::token('nope.nope.nope', 'fallback'));
	}

	public function test_tone_returns_foreground_background_and_border() {
		$danger = AIPS_Email_Theme::tone('danger');

		$this->assertSame('#b32d2e', $danger['fg']);
		$this->assertSame('#fcecec', $danger['bg']);
		$this->assertSame('#f3c6c6', $danger['border']);
	}

	public function test_tone_falls_back_to_neutral_for_an_unknown_tone() {
		$this->assertSame(AIPS_Email_Theme::tone('neutral'), AIPS_Email_Theme::tone('not-a-tone'));
	}

	public function test_theme_filter_overrides_one_group_without_dropping_the_others() {
		AIPS_Email_Theme::flush_cache();

		add_filter('aips_email_theme', function ($tokens) {
			$tokens['color']['brand'] = '#ff0000';
			return $tokens;
		});

		$tokens = AIPS_Email_Theme::tokens();

		$this->assertSame('#ff0000', $tokens['color']['brand']);
		// Untouched groups and sibling keys survive the merge.
		$this->assertSame('#f0f0f1', $tokens['color']['page']);
		$this->assertArrayHasKey('heading', $tokens['font']);
	}

	// -----------------------------------------------------------------------
	// AIPS_Email_View: immutability
	// -----------------------------------------------------------------------

	public function test_with_returns_a_new_instance_and_leaves_the_original_alone() {
		$original = AIPS_Email_View::create(array('headline' => 'First'));
		$updated  = $original->with(array('headline' => 'Second'));

		$this->assertNotSame($original, $updated);
		$this->assertSame('First', $original->get('headline'));
		$this->assertSame('Second', $updated->get('headline'));
	}

	public function test_unset_fields_fall_back_to_the_schema_defaults() {
		$view = AIPS_Email_View::create();

		$this->assertSame(AIPS_Email_View::HEADER_NONE, $view->header_mode());
		$this->assertSame(array(), $view->meta_cells());
		$this->assertSame(array(), $view->actions());
		$this->assertSame('', $view->get('headline', ''));
	}

	public function test_has_reports_whether_a_region_is_worth_rendering() {
		$view = AIPS_Email_View::create(array(
			'headline'   => 'Something',
			'excerpt'    => '',
			'meta_cells' => array(array('label' => 'Category', 'value' => 'Security')),
		));

		$this->assertTrue($view->has('headline'));
		$this->assertFalse($view->has('excerpt'));
		$this->assertTrue($view->has('meta_cells'));
		$this->assertFalse($view->has('stat_tiles'));
		$this->assertFalse($view->has('not_a_field'));
	}

	// -----------------------------------------------------------------------
	// AIPS_Email_View: normalisation
	// -----------------------------------------------------------------------

	public function test_an_unknown_header_mode_degrades_to_none() {
		$view = AIPS_Email_View::create(array('header_mode' => 'carousel'));

		$this->assertSame(AIPS_Email_View::HEADER_NONE, $view->header_mode());
	}

	public function test_meta_cells_without_a_value_are_dropped_so_the_grid_reflows() {
		$view = AIPS_Email_View::create(array(
			'meta_cells' => array(
				array('label' => 'Category', 'value' => 'Security'),
				array('label' => 'Length', 'value' => ''),
				array('label' => 'Schedule'),
				array('value' => 'orphan'),
				'not-an-array',
			),
		));

		$cells = $view->meta_cells();

		$this->assertCount(1, $cells);
		$this->assertSame('Category', $cells[0]['label']);
	}

	public function test_a_stat_tile_value_of_zero_is_kept() {
		// "0 errors" is a meaningful tile in the design, so it must not be
		// filtered out as empty.
		$view = AIPS_Email_View::create(array(
			'stat_tiles' => array(
				array('label' => 'Errors', 'value' => '0'),
			),
		));

		$tiles = $view->get('stat_tiles');

		$this->assertCount(1, $tiles);
		$this->assertSame('0', $tiles[0]['value']);
		$this->assertSame(AIPS_Email_View::TONE_NEUTRAL, $tiles[0]['tone']);
	}

	public function test_only_the_first_action_is_primary() {
		$view = AIPS_Email_View::create(array(
			'actions' => array(
				array('label' => 'Review post', 'url' => 'https://example.test/a', 'variant' => 'primary'),
				array('label' => 'Open queue', 'url' => 'https://example.test/b', 'variant' => 'primary'),
			),
		));

		$actions = $view->actions();

		$this->assertCount(2, $actions);
		$this->assertSame('primary', $actions[0]['variant']);
		$this->assertSame('secondary', $actions[1]['variant']);
	}

	public function test_actions_missing_a_label_or_url_are_dropped() {
		$view = AIPS_Email_View::create(array(
			'actions' => array(
				array('label' => 'No URL'),
				array('url' => 'https://example.test/no-label'),
				array('label' => 'Good', 'url' => 'https://example.test/good'),
			),
		));

		$actions = $view->actions();

		$this->assertCount(1, $actions);
		$this->assertSame('Good', $actions[0]['label']);
	}

	public function test_preview_blocks_are_normalised_to_a_known_type() {
		$view = AIPS_Email_View::create(array(
			'preview_blocks' => array(
				array('type' => 'heading', 'text' => 'A heading'),
				array('type' => 'marquee', 'text' => 'Unknown type'),
				array('text' => ''),
			),
		));

		$blocks = $view->get('preview_blocks');

		$this->assertCount(2, $blocks);
		$this->assertSame(AIPS_Email_View::BLOCK_HEADING, $blocks[0]['type']);
		$this->assertSame(AIPS_Email_View::BLOCK_PARAGRAPH, $blocks[1]['type']);
	}

	public function test_an_empty_callout_or_pill_collapses_to_an_empty_array() {
		$view = AIPS_Email_View::create(array(
			'callout'     => array('title' => '', 'body' => ''),
			'status_pill' => array('tone' => 'success'),
		));

		$this->assertFalse($view->has('callout'));
		$this->assertFalse($view->has('status_pill'));
	}

	public function test_a_callout_defaults_to_the_danger_tone() {
		$view = AIPS_Email_View::create(array(
			'callout' => array('title' => 'Provider returned an error'),
		));

		$callout = $view->get('callout');

		$this->assertSame(AIPS_Email_View::TONE_DANGER, $callout['tone']);
		$this->assertSame('', $callout['body']);
	}

	public function test_item_groups_with_no_usable_rows_are_dropped() {
		$view = AIPS_Email_View::create(array(
			'item_groups' => array(
				array('label' => 'Post Generated', 'rows' => array(array('title' => 'A post'))),
				array('label' => 'Empty Group', 'rows' => array()),
				array('rows' => array(array('title' => 'No label'))),
			),
		));

		$groups = $view->get('item_groups');

		$this->assertCount(1, $groups);
		$this->assertSame('Post Generated', $groups[0]['label']);
		$this->assertSame('', $groups[0]['rows'][0]['meta']);
	}

	public function test_to_array_exposes_every_schema_field() {
		$data = AIPS_Email_View::create(array('headline' => 'Hi'))->to_array();

		foreach (array('header_mode', 'eyebrow', 'headline', 'meta_cells', 'actions', 'footer_link', 'preheader') as $field) {
			$this->assertArrayHasKey($field, $data);
		}
	}
}
