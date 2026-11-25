<?php
/**
 * Tests for AIPS_Email_View_Factory
 *
 * The factory is where the redesign's curated metadata actually comes from, so
 * these tests pin down per-type composition, the derivations (word count, reading
 * time, category, local time), and the rule that a cell with no value disappears.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Email_View_Factory extends WP_UnitTestCase {

	/**
	 * @var AIPS_Email_View_Factory
	 */
	private $factory;

	public function set_up() {
		parent::set_up();
		$this->factory = new AIPS_Email_View_Factory();
	}

	public function tear_down() {
		remove_all_filters('aips_email_view');
		parent::tear_down();
	}

	// -----------------------------------------------------------------------
	// Shared chrome
	// -----------------------------------------------------------------------

	public function test_every_view_carries_the_site_name_and_settings_footer_link() {
		$view = $this->factory->build('system_error', array('title' => 'Boom'));

		$this->assertSame(get_bloginfo('name'), $view->get('site_name'));

		$footer = $view->get('footer_link');
		$this->assertNotEmpty($footer['url']);
		$this->assertStringContainsString('tab=notifications', $footer['url']);
	}

	public function test_the_subject_keeps_the_bracketed_site_prefix() {
		$view = $this->factory->build('system_error', array('title' => 'Disk full'));

		$this->assertSame(sprintf('[%s] Disk full', get_bloginfo('name')), $view->get('subject'));
	}

	public function test_the_view_filter_can_replace_the_composition() {
		add_filter('aips_email_view', function ($view) {
			return $view->with(array('headline' => 'Filtered'));
		});

		$view = $this->factory->build('system_error', array('title' => 'Boom'));

		$this->assertSame('Filtered', $view->get('headline'));
	}

	// -----------------------------------------------------------------------
	// Post events
	// -----------------------------------------------------------------------

	public function test_post_generated_uses_the_status_banner_with_a_pill() {
		$post_id = self::factory()->post->create(array(
			'post_title'   => 'Continuous Pentesting in Production',
			'post_status'  => 'draft',
			'post_content' => '<p>First paragraph.</p>',
		));

		$view = $this->factory->build('post_generated', array(
			'title' => 'Continuous Pentesting in Production',
			'meta'  => array('post_id' => $post_id, 'post_status' => 'draft'),
		));

		$this->assertSame(AIPS_Email_View::HEADER_BANNER, $view->header_mode());
		$this->assertSame('Continuous Pentesting in Production', $view->get('headline'));
		// The banner carries the status, so the headline drops to the smaller size.
		$this->assertSame('md', $view->get('headline_size'));

		$pill = $view->get('status_pill');
		$this->assertSame(AIPS_Email_View::TONE_WARNING, $pill['tone']);
	}

	public function test_a_published_post_gets_a_success_pill_and_a_view_post_button() {
		$post_id = self::factory()->post->create(array(
			'post_title'  => 'Shipped',
			'post_status' => 'publish',
		));

		$view = $this->factory->build('post_generated', array(
			'title' => 'Shipped',
			'url'   => get_edit_post_link($post_id, 'raw'),
			'meta'  => array('post_id' => $post_id, 'post_status' => 'publish'),
		));

		$pill = $view->get('status_pill');
		$this->assertSame(AIPS_Email_View::TONE_SUCCESS, $pill['tone']);

		$labels = wp_list_pluck($view->actions(), 'label');
		$this->assertContains('View post', $labels);
	}

	public function test_a_draft_post_links_to_the_generated_posts_screen_not_a_preview() {
		// A preview URL's nonce belongs to whoever created it, not to whoever opens
		// the email later, so drafts must not offer one.
		$post_id = self::factory()->post->create(array('post_status' => 'draft'));

		$view = $this->factory->build('post_generated', array(
			'title' => 'A draft',
			'url'   => get_edit_post_link($post_id, 'raw'),
			'meta'  => array('post_id' => $post_id, 'post_status' => 'draft'),
		));

		foreach ($view->actions() as $action) {
			$this->assertStringNotContainsString('preview', $action['url']);
		}
	}

	public function test_post_metadata_cells_follow_the_designs_order() {
		$category_id = self::factory()->category->create(array('name' => 'Security'));
		$post_id     = self::factory()->post->create(array(
			'post_title'    => 'A post',
			'post_status'   => 'draft',
			'post_content'  => str_repeat('word ', 450),
			'post_category' => array($category_id),
		));

		$view = $this->factory->build('post_generated', array(
			'title' => 'A post',
			'meta'  => array(
				'post_id'       => $post_id,
				'post_status'   => 'draft',
				'template_name' => 'Security News',
			),
		));

		$labels = wp_list_pluck($view->meta_cells(), 'label');

		$this->assertSame(array('Category', 'Generated', 'Template', 'Length'), $labels);
	}

	public function test_the_length_cell_reports_words_and_reading_time() {
		$post_id = self::factory()->post->create(array(
			// 450 words at 225 wpm is exactly 2 minutes.
			'post_content' => str_repeat('word ', 450),
			'post_status'  => 'draft',
		));

		$view  = $this->factory->build('post_generated', array('meta' => array('post_id' => $post_id)));
		$cells = $this->cells_by_label($view);

		$this->assertSame('450 words · 2 min', $cells['Length']);
	}

	public function test_an_uncategorised_post_drops_the_category_cell() {
		$post_id = self::factory()->post->create(array(
			'post_status'  => 'draft',
			'post_content' => 'Short body.',
		));

		// The default "Uncategorized" term is assigned automatically, so remove it
		// to exercise the genuinely empty case.
		wp_set_post_terms($post_id, array(), 'category');

		$view  = $this->factory->build('post_generated', array('meta' => array('post_id' => $post_id)));
		$cells = $this->cells_by_label($view);

		$this->assertArrayNotHasKey('Category', $cells);
		// The remaining cells still render.
		$this->assertArrayHasKey('Generated', $cells);
	}

	public function test_a_source_label_is_split_into_its_own_cell_label_and_value() {
		$post_id = self::factory()->post->create(array('post_status' => 'draft'));

		$view  = $this->factory->build('post_generated', array(
			'meta' => array('post_id' => $post_id, 'source_label' => 'Author Topic: CI pipeline fuzzing'),
		));
		$cells = $this->cells_by_label($view);

		$this->assertArrayHasKey('Author Topic', $cells);
		$this->assertSame('CI pipeline fuzzing', $cells['Author Topic']);
	}

	public function test_an_unknown_creation_method_does_not_claim_the_post_was_scheduled() {
		// Regression: generated_value() used to default to "Scheduled", so a
		// manually generated post whose payload omitted creation_method was
		// reported as scheduled. With the method unknown, the cell shows the
		// timestamp alone rather than asserting something false.
		$post_id = self::factory()->post->create(array('post_status' => 'draft'));

		$cells = $this->cells_by_label($this->factory->build('post_generated', array(
			'meta' => array('post_id' => $post_id),
		)));

		$this->assertArrayHasKey('Generated', $cells);
		$this->assertStringNotContainsString('Scheduled', $cells['Generated']);
		$this->assertStringNotContainsString('Manual', $cells['Generated']);
	}

	public function test_the_quota_model_cell_reads_the_key_the_live_emitter_sends() {
		// AIPS_AI_Service::emit_quota_alert_notification() sends `ai_model`;
		// earlier revisions only read `model`, so live quota emails lost the cell.
		$cells = $this->cells_by_label($this->factory->build('quota_alert', array(
			'title' => 'Quota alert',
			'meta'  => array('request_type' => 'text', 'ai_model' => 'gemini-2.5-pro'),
		)));

		$this->assertSame('gemini-2.5-pro', $cells['AI model']);
	}

	public function test_the_quota_model_cell_is_not_duplicated_when_both_keys_are_present() {
		$view = $this->factory->build('quota_alert', array(
			'title' => 'Quota alert',
			'meta'  => array('ai_model' => 'gemini-2.5-pro', 'model' => 'other'),
		));

		$labels = wp_list_pluck($view->meta_cells(), 'label');

		$this->assertSame(array('AI model'), $labels);
		$this->assertSame('gemini-2.5-pro', $this->cells_by_label($view)['AI model']);
	}

	public function test_the_generated_cell_distinguishes_manual_from_scheduled() {
		$post_id = self::factory()->post->create(array('post_status' => 'draft'));

		$manual = $this->cells_by_label($this->factory->build('post_generated', array(
			'meta' => array('post_id' => $post_id, 'creation_method' => 'manual'),
		)));
		$this->assertStringStartsWith('Manual', $manual['Generated']);

		$scheduled = $this->cells_by_label($this->factory->build('post_generated', array(
			'meta' => array('post_id' => $post_id, 'creation_method' => 'scheduled'),
		)));
		$this->assertStringStartsWith('Scheduled', $scheduled['Generated']);
	}

	public function test_content_preview_blocks_are_typed_plain_text_and_truncated() {
		$post_id = self::factory()->post->create(array(
			'post_status'  => 'draft',
			'post_content' => '<p>' . str_repeat('alpha ', 100) . '</p>'
				. '<h2>Myth 1: Annual tests are enough</h2>'
				. '<p>Second body paragraph.</p>'
				. '<p>A fourth block that must not appear.</p>',
		));

		$view   = $this->factory->build('post_generated', array('meta' => array('post_id' => $post_id)));
		$blocks = $view->get('preview_blocks');

		$this->assertCount(3, $blocks);
		$this->assertSame(AIPS_Email_View::BLOCK_PARAGRAPH, $blocks[0]['type']);
		$this->assertSame(AIPS_Email_View::BLOCK_HEADING, $blocks[1]['type']);
		$this->assertSame('Myth 1: Annual tests are enough', $blocks[1]['text']);

		// The long opening paragraph is clamped rather than faded out with CSS.
		$this->assertLessThanOrEqual(221, mb_strlen($blocks[0]['text']));
		$this->assertStringEndsWith('…', $blocks[0]['text']);
	}

	public function test_preview_blocks_never_carry_markup_from_the_post() {
		$post_id = self::factory()->post->create(array(
			'post_status'  => 'draft',
			'post_content' => '<p>Safe <strong>text</strong> <script>alert(1)</script></p>',
		));

		$view   = $this->factory->build('post_generated', array('meta' => array('post_id' => $post_id)));
		$blocks = $view->get('preview_blocks');

		$this->assertStringNotContainsString('<', $blocks[0]['text']);
	}

	public function test_post_ready_for_review_shows_no_content_preview() {
		$post_id = self::factory()->post->create(array(
			'post_status'  => 'draft',
			'post_content' => '<p>Body copy.</p>',
		));

		$view = $this->factory->build('post_ready_for_review', array(
			'meta' => array('post_id' => $post_id, 'post_status' => 'draft'),
		));

		$this->assertSame(AIPS_Email_View::HEADER_BANNER, $view->header_mode());
		$this->assertFalse($view->has('preview_blocks'));
		$this->assertFalse($view->has('excerpt'));
	}

	public function test_a_warning_type_uses_the_warning_banner_tone() {
		$view = $this->factory->build('post_rejected', array(
			'title' => 'Post rejected',
			'meta'  => array('post_id' => 0),
		));

		$this->assertSame(AIPS_Email_View::TONE_WARNING, $view->get('header_tone'));
	}

	// -----------------------------------------------------------------------
	// Alerts
	// -----------------------------------------------------------------------

	public function test_quota_alert_composes_a_danger_eyebrow_callout_and_cells() {
		$view = $this->factory->build('quota_alert', array(
			'title' => 'Quota alert: embedding',
			'meta'  => array(
				'request_type'  => 'Embedding',
				'error_message' => 'Monthly rate limit reached (500/500)',
				'error_detail'  => 'Requests resume when the limit resets.',
				'error_code'    => 'rate_limit_exceeded',
				'model'         => 'gemini-embedding-2',
				'usage'         => '500 / 500 monthly',
			),
		));

		$this->assertSame(AIPS_Email_View::HEADER_NONE, $view->header_mode());
		$this->assertSame(AIPS_Email_View::TONE_DANGER, $view->get('eyebrow_tone'));
		// The design appends the subject of the alert to the eyebrow.
		$this->assertStringContainsString('Embedding', $view->get('eyebrow'));
		$this->assertSame('AI requests are blocked for Embedding operations', $view->get('headline'));

		$callout = $view->get('callout');
		$this->assertSame('Monthly rate limit reached (500/500)', $callout['title']);
		$this->assertSame('Requests resume when the limit resets.', $callout['body']);

		$labels = wp_list_pluck($view->meta_cells(), 'label');
		$this->assertSame(array('Request type', 'AI model', 'Error code', 'Usage'), $labels);
	}

	public function test_alert_cells_absent_from_the_payload_are_omitted() {
		$view = $this->factory->build('quota_alert', array(
			'title' => 'Quota alert',
			'meta'  => array('request_type' => 'Embedding'),
		));

		$labels = wp_list_pluck($view->meta_cells(), 'label');

		$this->assertSame(array('Request type'), $labels);
	}

	public function test_generation_failed_names_the_resource_in_the_headline() {
		$view = $this->factory->build('generation_failed', array(
			'title' => 'Generation failed: Daily Security',
			'meta'  => array(
				'resource_label' => 'Daily Security',
				'error_message'  => 'Provider returned an error',
				'attempts'       => '2 of 3',
			),
		));

		$this->assertSame('Daily Security could not generate a post', $view->get('headline'));

		$cells = $this->cells_by_label($view);
		$this->assertSame('2 of 3', $cells['Attempts']);
	}

	public function test_a_retry_timestamp_renders_as_a_local_clock_time() {
		$retry_at = AIPS_DateTime::now()->timestamp() + 1800;

		$view  = $this->factory->build('generation_failed', array(
			'title' => 'Generation failed',
			'meta'  => array('next_retry' => $retry_at),
		));
		$cells = $this->cells_by_label($view);

		$expected = AIPS_DateTime::fromTimestamp($retry_at)->toTimeDisplay();
		$this->assertSame($expected, $cells['Next retry']);
	}

	public function test_a_failure_offers_no_state_changing_button() {
		$view = $this->factory->build('generation_failed', array(
			'title' => 'Generation failed',
			'url'   => 'https://example.test/history',
			'meta'  => array('resource_label' => 'Daily Security'),
		));

		// The design's "Retry now" resolves to the history screen: an email URL is
		// not a safe place for an action token.
		foreach ($view->actions() as $action) {
			$this->assertStringNotContainsString('retry', strtolower($action['url']));
		}

		$labels = wp_list_pluck($view->actions(), 'label');
		$this->assertContains('View history', $labels);
	}

	// -----------------------------------------------------------------------
	// Rollups
	// -----------------------------------------------------------------------

	public function test_a_rollup_builds_three_stat_tiles() {
		$view = $this->factory->build('weekly_summary', array(
			'title' => 'Weekly generation summary',
			'meta'  => array('generated' => 11, 'review_ready' => 55, 'errors' => 0, 'window' => 7 * DAY_IN_SECONDS),
		));

		$tiles = $view->get('stat_tiles');

		$this->assertCount(3, $tiles);
		$this->assertSame('11', $tiles[0]['value']);
		// Something waiting for review is flagged amber; zero errors stays neutral.
		$this->assertSame(AIPS_Email_View::TONE_WARNING, $tiles[1]['tone']);
		$this->assertSame(AIPS_Email_View::TONE_NEUTRAL, $tiles[2]['tone']);
		$this->assertSame('11 posts generated, 55 ready for review', $view->get('headline'));
	}

	public function test_an_empty_review_queue_is_not_flagged() {
		$view = $this->factory->build('weekly_summary', array(
			'meta' => array('generated' => 3, 'review_ready' => 0, 'errors' => 0),
		));

		$tiles = $view->get('stat_tiles');

		$this->assertSame(AIPS_Email_View::TONE_NEUTRAL, $tiles[1]['tone']);
	}

	public function test_errors_are_toned_red_when_present() {
		$view = $this->factory->build('daily_digest', array(
			'meta' => array('generated' => 1, 'review_ready' => 0, 'errors' => 4),
		));

		$tiles = $view->get('stat_tiles');

		$this->assertSame(AIPS_Email_View::TONE_DANGER, $tiles[2]['tone']);
	}

	public function test_the_daily_digest_keeps_the_brand_bar_and_summaries_do_not() {
		$daily = $this->factory->build('daily_digest', array('meta' => array('generated' => 1)));
		$week  = $this->factory->build('weekly_summary', array('meta' => array('generated' => 1)));

		$this->assertSame(AIPS_Email_View::HEADER_BRAND, $daily->header_mode());
		$this->assertSame(AIPS_Email_View::HEADER_NONE, $week->header_mode());
	}

	public function test_rollup_item_rows_show_the_time_each_post_was_generated() {
		$created_at = AIPS_DateTime::now()->timestamp() - 3600;

		$view = $this->factory->build('daily_digest', array(
			'meta' => array(
				'generated' => 1,
				'items'     => array(
					array('title' => 'A generated post', 'url' => 'https://example.test/p', 'created_at' => $created_at),
					array('title' => ''),
				),
			),
		));

		$rows = $view->get('item_rows');

		$this->assertCount(1, $rows);
		$this->assertSame('A generated post', $rows[0]['title']);
		$this->assertSame(AIPS_DateTime::fromTimestamp($created_at)->toTimeDisplay(), $rows[0]['meta']);
	}

	public function test_a_rollup_eyebrow_names_the_period() {
		$view = $this->factory->build('weekly_summary', array(
			'meta' => array('generated' => 1, 'window' => 7 * DAY_IN_SECONDS),
		));

		// e.g. "Weekly Summary · Nov 2–8, 2026"
		$this->assertStringContainsString('·', $view->get('eyebrow'));
		$this->assertMatchesRegularExpression('/\d{4}$/', $view->get('eyebrow'));
	}

	// -----------------------------------------------------------------------
	// Summarized digest
	// -----------------------------------------------------------------------

	public function test_the_summarized_digest_groups_items_by_notification_type() {
		$view = $this->factory->build('summarized_digest', array(
			'title' => 'Notification Digest: 3 updates',
			'meta'  => array(
				'count' => 3,
				'items' => array(
					array('type' => 'post_generated', 'title' => 'First', 'meta' => array('post_status_label' => 'Draft')),
					array('type' => 'post_generated', 'title' => 'Second'),
					array('type' => 'post_ready_for_review', 'title' => 'Third'),
				),
			),
		));

		$groups = $view->get('item_groups');

		$this->assertCount(2, $groups);
		$this->assertCount(2, $groups[0]['rows']);
		$this->assertSame('Draft', $groups[0]['rows'][0]['meta']);
		$this->assertStringContainsString('3 updates', $view->get('headline'));
	}

	// -----------------------------------------------------------------------
	// Fallback
	// -----------------------------------------------------------------------

	public function test_an_unknown_type_still_composes_a_renderable_view() {
		$view = $this->factory->build('some_third_party_type', array(
			'title'   => 'Third-party event',
			'message' => 'Something happened elsewhere.',
			'level'   => 'warning',
		));

		$this->assertSame(AIPS_Email_View::HEADER_BRAND, $view->header_mode());
		$this->assertSame('Third-party event', $view->get('headline'));
		$this->assertSame('Something happened elsewhere.', $view->get('body_text'));
		$this->assertSame(AIPS_Email_View::TONE_WARNING, $view->get('eyebrow_tone'));
	}

	public function test_pre_escaped_sender_strings_are_decoded_so_they_are_not_double_escaped() {
		// Senders historically ran values through esc_html() before dispatch.
		$view = $this->factory->build('system_error', array(
			'title' => esc_html("Jane's schedule failed"),
		));

		$this->assertSame("Jane's schedule failed", $view->get('headline'));
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Index a view's metadata cells by label.
	 *
	 * @param AIPS_Email_View $view View to read.
	 * @return array<string, string>
	 */
	private function cells_by_label(AIPS_Email_View $view) {
		$cells = array();

		foreach ($view->meta_cells() as $cell) {
			$cells[$cell['label']] = $cell['value'];
		}

		return $cells;
	}
}
