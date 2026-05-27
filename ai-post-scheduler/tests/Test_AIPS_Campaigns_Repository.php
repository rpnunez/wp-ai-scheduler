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

	public function setUp(): void {
		parent::setUp();

		$this->reset_singleton(AIPS_Campaigns_Repository::class);
		$this->reset_singleton(AIPS_Template_Repository::class);
		$this->reset_singleton(AIPS_Schedule_Repository::class);

		global $wpdb;
		$this->original_wpdb = $wpdb;
		$this->wpdb_stub = new AIPS_Test_Campaigns_Repository_WPDB_Stub();
		$wpdb = $this->wpdb_stub;
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

		$repository = new AIPS_Campaigns_Repository();
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

		$repository = new AIPS_Campaigns_Repository();
		$campaign = $repository->get_campaign_by_id(24);

		$this->assertSame(24, (int) $campaign->id);
		$this->assertStringContainsString('WHERE c.id = 24', $this->wpdb_stub->last_get_results_query);
		$this->assertStringNotContainsString('ORDER BY c.is_archived ASC, c.created_at DESC', $this->wpdb_stub->last_get_results_query);
	}

	public function test_can_delete_campaign_checks_all_history_entries() {
		$this->wpdb_stub->var_to_return = '3';

		$repository = new AIPS_Campaigns_Repository();

		$this->assertFalse($repository->can_delete_campaign(55));
		$this->assertStringContainsString('WHERE campaign_id = 55', $this->wpdb_stub->last_get_var_query);
		$this->assertStringNotContainsString("status = 'completed'", $this->wpdb_stub->last_get_var_query);
		$this->assertStringNotContainsString('post_id IS NOT NULL', $this->wpdb_stub->last_get_var_query);
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

	public function get_var($query, $x = 0, $y = 0) {
		$this->last_get_var_query = preg_replace('/\s+/', ' ', trim($query));

		return $this->var_to_return;
	}

	public function query($query) {
		return true;
	}
}
