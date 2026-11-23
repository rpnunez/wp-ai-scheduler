<?php
/**
 * Tests for the redesigned Schedules list table rendering.
 *
 * Covers the column set, the Enabled / Paused switch, the Timing column
 * states, the Schedule column meta line and the lazy details placeholder.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Schedules_List_Table extends WP_UnitTestCase {

	/** @var AIPS_Schedules_List_Table */
	private $table;

	public function setUp(): void {
		parent::setUp();
		$this->table = new AIPS_Schedules_List_Table();
	}

	/**
	 * Call a protected table method.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Arguments.
	 * @return mixed
	 */
	private function call($method, array $args = array()) {
		$reflection = new ReflectionMethod($this->table, $method);
		$reflection->setAccessible(true);
		return $reflection->invokeArgs($this->table, $args);
	}

	/**
	 * Minimal normalised schedule row.
	 *
	 * @param array $overrides Field overrides.
	 * @return array
	 */
	private function make_item(array $overrides = array()) {
		return array_merge(array(
			'id'            => 7,
			'type'          => AIPS_Unified_Schedule_Service::TYPE_TEMPLATE,
			'title'         => 'Weekly Roundup',
			'subtitle'      => 'Roundup Template',
			'frequency'     => 'daily',
			'next_run'      => time() + (2 * HOUR_IN_SECONDS) + (5 * MINUTE_IN_SECONDS) + 30,
			'last_run'      => time() - DAY_IN_SECONDS,
			'is_active'     => 1,
			'status'        => 'active',
			'stats_count'   => 37,
			'circuit_state' => 'closed',
			'can_delete'    => true,
		), $overrides);
	}

	public function test_columns_drop_pipeline_activity_and_health() {
		$columns = $this->table->get_columns();

		$this->assertSame(array('cb', 'title', 'timing', 'status', 'actions'), array_keys($columns));
		$this->assertArrayNotHasKey('type', $columns);
		$this->assertArrayNotHasKey('stats', $columns);
	}

	public function test_sortable_columns_match_visible_columns() {
		$sortable = $this->call('get_sortable_columns');

		$this->assertSame(array('title', 'timing', 'status'), array_keys($sortable));
		$this->assertSame('next_run', $sortable['timing'][0]);
	}

	public function test_status_switch_renders_enabled_state() {
		$html = $this->call('column_status', array($this->make_item()));

		$this->assertStringContainsString('aips-status-switch', $html);
		$this->assertStringContainsString('role="switch"', $html);
		$this->assertStringContainsString('aips-unified-toggle-schedule', $html);
		$this->assertMatchesRegularExpression('/checked=([\'"])checked\1/', $html);
		$this->assertStringContainsString('Enabled', $html);
		$this->assertStringContainsString('Paused', $html);
		// The old standalone status / health badges are gone.
		$this->assertStringNotContainsString('aips-badge', $html);
		$this->assertStringNotContainsString('Healthy', $html);
	}

	public function test_status_switch_renders_paused_state() {
		$html = $this->call('column_status', array($this->make_item(array('is_active' => 0))));

		$this->assertDoesNotMatchRegularExpression('/checked=([\'"])checked\1/', $html);
	}

	public function test_timing_shows_frequency_relative_next_and_last_run() {
		$html = $this->call('column_timing', array($this->make_item()));

		$this->assertStringContainsString('aips-sched-freq', $html);
		$this->assertStringContainsString('Once Daily', $html);
		$this->assertStringContainsString('in 2h 5m', $html);
		$this->assertStringContainsString('Next', $html);
		$this->assertStringContainsString('Last', $html);
		// Filler text and cron hooks are no longer rendered.
		$this->assertStringNotContainsString('Most recent run across stages', $html);
		$this->assertStringNotContainsString('aips_generate', $html);
	}

	public function test_timing_shows_paused_when_inactive() {
		$html = $this->call('column_timing', array($this->make_item(array('is_active' => 0))));

		$this->assertMatchesRegularExpression('#<dd class="aips-sched-time-muted">Paused</dd>#', $html);
		$this->assertStringNotContainsString('aips-sched-rel', $html);
	}

	public function test_timing_shows_due_now_when_overdue() {
		$html = $this->call('column_timing', array($this->make_item(array('next_run' => time() - HOUR_IN_SECONDS))));

		$this->assertStringContainsString('Due now', $html);
	}

	public function test_timing_shows_never_without_last_run() {
		$html = $this->call('column_timing', array($this->make_item(array('last_run' => 0))));

		$this->assertStringContainsString('Never', $html);
	}

	public function test_timing_shows_mixed_frequency_with_stage_tooltip() {
		$item = $this->make_item(array(
			'type'            => AIPS_Unified_Schedule_Service::TYPE_BLUEPRINT,
			'frequency'       => '',
			'mixed_frequency' => true,
			'stages'          => array(
				array('label' => 'Stage 1 · Topics', 'frequency' => 'weekly'),
				array('label' => 'Stage 2 · Posts', 'frequency' => 'daily'),
			),
		));

		$html = $this->call('column_timing', array($item));

		$this->assertStringContainsString('Mixed', $html);
		$this->assertStringContainsString('Stage 1 · Topics: Once Weekly', $html);
		$this->assertStringContainsString('Stage 2 · Posts: Once Daily', $html);
	}

	public function test_title_shows_type_chip_without_output_count_or_subtitle() {
		$html = $this->call('column_title', array($this->make_item(array(
			'type'       => AIPS_Unified_Schedule_Service::TYPE_BLUEPRINT,
			'subtitle'   => 'Persona Niche',
			'can_delete' => false,
		))));

		$this->assertStringContainsString('aips-sched-type--blueprint', $html);
		$this->assertStringContainsString('Blueprint', $html);
		$this->assertStringNotContainsString('37 posts', $html);
		$this->assertStringNotContainsString('Persona Niche', $html);
		$this->assertStringContainsString('aips-row-expand-toggle', $html);
		// Hover row actions are kept.
		$this->assertStringContainsString('row-actions', $html);
		$this->assertStringContainsString('aips-view-unified-history', $html);
		$this->assertStringNotContainsString('aips-sched-alert', $html);
	}

	public function test_column_actions_renders_flush_button_group() {
		$html = $this->call('column_actions', array($this->make_item()));

		$this->assertStringContainsString('aips-sched-actions-group', $html);
		$this->assertStringContainsString('aips-unified-run-now', $html);
		$this->assertStringContainsString('aips-row-action-overflow-toggle', $html);
		$this->assertStringContainsString('aips-row-action-menu', $html);
	}

	public function test_title_flags_schedules_that_need_attention() {
		$open   = $this->call('column_title', array($this->make_item(array('circuit_state' => 'open'))));
		$failed = $this->call('column_title', array($this->make_item(array('status' => 'failed'))));

		$this->assertStringContainsString('aips-sched-alert', $open);
		$this->assertStringContainsString('aips-sched-alert', $failed);
	}

	public function test_title_escapes_schedule_name() {
		$html = $this->call('column_title', array($this->make_item(array('title' => '<script>x</script>'))));

		$this->assertStringNotContainsString('<script>x</script>', $html);
		$this->assertStringContainsString('&lt;script&gt;', $html);
	}

	public function test_details_placeholder_is_lazy() {
		$html = $this->table->single_row_details($this->make_item());

		$this->assertStringContainsString('aips-sched-details', $html);
		$this->assertStringContainsString('data-loaded="0"', $html);
		$this->assertStringContainsString('data-type="template_schedule"', $html);
		// The old drawer sections are gone.
		$this->assertStringNotContainsString('Configuration &amp; Cron', $html);
		$this->assertStringNotContainsString('Pipeline Stages', $html);
	}

	public function test_relative_time_formatting() {
		$this->assertSame('in 3d 4h', $this->call('format_relative_short', array(time() + (3 * DAY_IN_SECONDS) + (4 * HOUR_IN_SECONDS) + 30)));
		$this->assertSame('in 15m', $this->call('format_relative_short', array(time() + (15 * MINUTE_IN_SECONDS) + 30)));
		$this->assertSame('in under a minute', $this->call('format_relative_short', array(time() + 20)));
	}

	public function test_views_run_through_core_views_filter() {
		$screen   = WP_Screen::get('toplevel_page_aips-automations');
		$property = new ReflectionProperty($this->table, 'screen');
		$property->setAccessible(true);
		$property->setValue($this->table, $screen);

		$callback = function($views) {
			$views['custom'] = '<a href="#" class="aips-schedules-view">Custom view</a>';
			return $views;
		};
		add_filter('views_' . $screen->id, $callback);

		ob_start();
		$this->table->views();
		$html = ob_get_clean();

		remove_filter('views_' . $screen->id, $callback);

		$this->assertStringContainsString('Custom view', $html);
		$this->assertStringContainsString('All Schedules', $html);
	}

	public function test_table_is_not_striped() {
		$classes = $this->call('get_table_classes');

		$this->assertNotContains('striped', $classes);
		$this->assertContains('aips-schedules-table', $classes);
	}
}
