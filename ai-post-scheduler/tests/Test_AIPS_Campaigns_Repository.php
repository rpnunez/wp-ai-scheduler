<?php
/**
 * Tests for AIPS_Campaigns_Repository.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Campaigns_Repository extends WP_UnitTestCase {

	/**
	 * @var object
	 */
	private $original_wpdb;

	/**
	 * @var AIPS_Test_Campaigns_Repository_WPDB_Stub
	 */
	private $wpdb_stub;

	/**
	 * @var PHPUnit\Framework\MockObject\MockObject
	 */
	private $template_repository;

	/**
	 * @var PHPUnit\Framework\MockObject\MockObject
	 */
	private $schedule_repository;

	/**
	 * @var array<int,string>
	 */
	private $original_classes = array();

	public function setUp(): void {
		parent::setUp();

		$this->reset_singleton(AIPS_Campaigns_Repository::class);
		$this->reset_singleton(AIPS_Template_Repository::class);
		$this->reset_singleton(AIPS_Schedule_Repository::class);

		global $wpdb;
		$this->original_wpdb = $wpdb;
		$this->wpdb_stub = new AIPS_Test_Campaigns_Repository_WPDB_Stub();
		$wpdb = $this->wpdb_stub;

		$this->template_repository = $this->getMockBuilder('AIPS_Template_Repository')
			->disableOriginalConstructor()
			->onlyMethods(array('create', 'delete', 'get_by_id'))
			->getMock();
		$this->schedule_repository = $this->getMockBuilder('AIPS_Schedule_Repository')
			->disableOriginalConstructor()
			->onlyMethods(array('delete', 'set_active'))
			->getMock();

		$this->template_repository->method('create')->willReturn(201);
		$this->template_repository->method('delete')->willReturn(true);
		$this->template_repository->method('get_by_id')->willReturn(null);
		$this->schedule_repository->method('delete')->willReturn(true);
		$this->schedule_repository->method('set_active')->willReturn(true);

		$this->define_test_dependency_classes();
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb = $this->original_wpdb;

		$this->reset_singleton(AIPS_Campaigns_Repository::class);
		$this->reset_singleton(AIPS_Template_Repository::class);
		$this->reset_singleton(AIPS_Schedule_Repository::class);

		parent::tearDown();
	}

	public function test_get_campaigns_applies_archived_and_id_filters_and_uses_total_history_for_delete_guard() {
		$this->wpdb_stub->results_to_return = array(
			(object) array(
				'id' => 42,
				'generated_posts_count' => '0',
				'total_history_count' => '2',
				'linked_template_count' => '1',
				'linked_schedule_count' => '1',
				'active_schedule_count' => '1',
			),
		);

		$repository = $this->new_repository();
		$campaigns = $repository->get_campaigns(true, 42);

		$this->assertCount(1, $campaigns);
		$this->assertSame(2, $campaigns[0]->total_history_count);
		$this->assertFalse($campaigns[0]->can_delete);
		$this->assertStringContainsString('WHERE c.is_archived = 1 AND c.id = 42', $this->wpdb_stub->last_get_results_query);
	}

	public function test_get_campaign_by_id_uses_filtered_campaign_query() {
		$this->wpdb_stub->results_to_return = array(
			(object) array(
				'id' => 24,
				'generated_posts_count' => '1',
				'total_history_count' => '1',
				'linked_template_count' => '2',
				'linked_schedule_count' => '3',
				'active_schedule_count' => '1',
			),
		);

		$repository = $this->new_repository();
		$campaign = $repository->get_campaign_by_id(24);

		$this->assertSame(24, (int) $campaign->id);
		$this->assertStringContainsString('WHERE c.id = 24', $this->wpdb_stub->last_get_results_query);
		$this->assertStringNotContainsString('ORDER BY c.is_archived ASC, c.created_at DESC', $this->wpdb_stub->last_get_results_query);
	}

	public function test_can_delete_campaign_checks_all_history_entries() {
		$this->wpdb_stub->var_to_return = '3';

		$repository = $this->new_repository();

		$this->assertFalse($repository->can_delete_campaign(55));
		$this->assertStringContainsString('WHERE campaign_id = 55', $this->wpdb_stub->last_get_var_query);
		$this->assertStringNotContainsString("status = 'completed'", $this->wpdb_stub->last_get_var_query);
		$this->assertStringNotContainsString('post_id IS NOT NULL', $this->wpdb_stub->last_get_var_query);
	}

	public function test_create_campaign_bundle_returns_wp_error_when_campaign_insert_fails() {
		$this->wpdb_stub->insert_return_values[] = false;

		$repository = $this->new_repository();
		$result = $repository->create_campaign_bundle($this->get_campaign_payload());

		$this->assertInstanceOf('WP_Error', $result);
		$this->assertSame('campaign_create_failed', $result->get_error_code());
		$this->assertContains('START TRANSACTION', $this->wpdb_stub->queries);
		$this->assertContains('ROLLBACK', $this->wpdb_stub->queries);
		$this->assertNotContains('COMMIT', $this->wpdb_stub->queries);
	}

	public function test_delete_campaign_returns_delete_blocked_wp_error_when_history_exists() {
		$this->wpdb_stub->var_to_return = '2';

		$repository = $this->new_repository();
		$result = $repository->delete_campaign(55);

		$this->assertInstanceOf('WP_Error', $result);
		$this->assertSame('delete_blocked', $result->get_error_code());
	}

	public function test_delete_campaign_returns_specific_error_when_schedule_delete_fails() {
		$schedule_repository = $this->getMockBuilder('AIPS_Schedule_Repository')
			->disableOriginalConstructor()
			->onlyMethods(array('delete'))
			->getMock();
		$schedule_repository->method('delete')->willReturn(false);

		$repository = $this->getMockBuilder('AIPS_Campaigns_Repository')
			->setConstructorArgs(array($this->template_repository, $schedule_repository))
			->onlyMethods(array('can_delete_campaign', 'get_schedules_by_campaign', 'get_templates_by_campaign'))
			->getMock();

		$repository->method('can_delete_campaign')->willReturn(true);
		$repository->method('get_schedules_by_campaign')->willReturn(array((object) array('id' => 9)));
		$repository->method('get_templates_by_campaign')->willReturn(array());

		$result = $repository->delete_campaign(88);

		$this->assertInstanceOf('WP_Error', $result);
		$this->assertSame('campaign_schedule_delete_failed', $result->get_error_code());
		$this->assertContains('ROLLBACK', $this->wpdb_stub->queries);
	}

	public function test_update_campaign_returns_true_when_no_fields_change() {
		$repository = $this->new_repository();

		$this->assertTrue($repository->update_campaign(13, array()));
		$this->assertNull($this->wpdb_stub->last_update_call);
	}

	private function get_campaign_payload() {
		return array(
			'campaign_name'         => 'Campaign Alpha',
			'content_goal'          => 'Goal',
			'campaign_mode'         => 'template',
			'is_active'             => 1,
			'frequency'             => 'daily',
			'start_time'            => '2026-06-01T09:00',
			'article_structure_id'  => 0,
			'rotation_pattern'      => '',
			'author_id'             => 0,
			'post_type_rules'       => '',
			'blackout_dates'        => '',
			'time_window_start'     => '',
			'time_window_end'       => '',
			'day_preferences'       => '',
			'season_end_date'       => 0,
			'template_mode'         => 'new',
			'template_id'           => 0,
			'title_prompt'          => '',
			'voice_id'              => 0,
			'post_status'           => 'draft',
			'post_type'             => 'post',
			'post_category'         => 0,
			'post_tags'             => '',
			'post_author'           => 1,
			'prompt_template'       => 'Prompt body',
		);
	}

	private function define_test_dependency_classes() {
		if (!class_exists('AIPS_Scheduler', false)) {
			eval('class AIPS_Scheduler { public function save_schedule($data) { return 101; } }');
			$this->original_classes[] = 'AIPS_Scheduler';
		}
	}

	private function new_repository() {
		$repository = new AIPS_Campaigns_Repository($this->template_repository, $this->schedule_repository);
		$reflection = new ReflectionClass($repository);

		$cache_initialized = $reflection->getProperty('cache_initialized');
		$cache_initialized->setAccessible(true);
		$cache_initialized->setValue($repository, true);

		$cache = $reflection->getProperty('cache');
		$cache->setAccessible(true);
		$cache->setValue($repository, null);

		return $repository;
	}

	/**
	 * @param string $class_name Class name.
	 * @return void
	 */
	private function reset_singleton($class_name) {
		$reflection = new ReflectionClass($class_name);

		if (!$reflection->hasProperty('instance')) {
			return;
		}

		$instance = $reflection->getProperty('instance');
		$instance->setAccessible(true);
		$instance->setValue(null);
	}
}

class AIPS_Test_Campaigns_Repository_WPDB_Stub {

	public $prefix = 'wp_';

	public $results_to_return = array();

	public $var_to_return = null;

	public $last_get_results_query = '';

	public $last_get_var_query = '';

	public $last_update_call = null;

	public $insert_return_values = array();

	public $queries = array();

	public $insert_id = 321;

	public $delete_return_value = 1;

	public $last_error = '';

	public function prepare($query, ...$args) {
		if (count($args) === 1 && is_array($args[0])) {
			$args = $args[0];
		}

		foreach ($args as $arg) {
			$replacement = is_numeric($arg) ? (string) $arg : "'" . $arg . "'";
			$query = preg_replace('/%[ds]/', $replacement, $query, 1);
		}

		return $query;
	}

	public function get_results($query, $output = OBJECT) {
		$this->last_get_results_query = preg_replace('/\s+/', ' ', trim($query));

		return $this->results_to_return;
	}

	public function get_row($query, $output = OBJECT, $y = 0) {
		$this->last_get_results_query = preg_replace('/\s+/', ' ', trim($query));

		return null;
	}

	public function get_var($query, $x = 0, $y = 0) {
		$this->last_get_var_query = preg_replace('/\s+/', ' ', trim($query));

		return $this->var_to_return;
	}

	public function query($query) {
		$this->queries[] = preg_replace('/\s+/', ' ', trim($query));
		return true;
	}

	public function suppress_errors($suppress = true) {
		return false;
	}

	public function _escape($data) {
		return $data;
	}

	public function insert($table, $data, $format = null) {
		if (!empty($this->insert_return_values)) {
			return array_shift($this->insert_return_values);
		}

		return 1;
	}

	public function update($table, $data, $where, $format = null, $where_format = null) {
		$this->last_update_call = array(
			'table' => $table,
			'data' => $data,
			'where' => $where,
		);

		return 1;
	}

	public function delete($table, $where, $where_format = null) {
		return $this->delete_return_value;
	}
}
