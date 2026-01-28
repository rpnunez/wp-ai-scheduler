<?php
/**
 * Tests for Bolt Optimization in AIPS_Template_Type_Selector
 *
 * Verifies that the get_schedule_execution_count method uses the passed created_at property
 * to avoid unnecessary subqueries.
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Bolt_Optimization_Selector_Test extends WP_UnitTestCase {

	private $selector;
	private $structure_repo;
	private $schedule_repo;
	private $template_id;

	public function setUp(): void {
		parent::setUp();
		$this->selector = new AIPS_Template_Type_Selector();
		$this->structure_repo = new AIPS_Article_Structure_Repository();
		$this->schedule_repo = new AIPS_Schedule_Repository();

		// Create a dummy template ID (we don't need the actual template row for this test,
		// just the ID for foreign key references if strict, but here we just need consistency)
		$this->template_id = 999;

		// Create test structures
		$this->create_test_structures();
	}

	public function tearDown(): void {
		global $wpdb;
		$table_structures = $wpdb->prefix . 'aips_article_structures';
		$table_schedule = $wpdb->prefix . 'aips_schedule';
		$table_history = $wpdb->prefix . 'aips_history';

		$wpdb->query("DELETE FROM $table_structures WHERE name LIKE 'Bolt Test%'");
		$wpdb->query("DELETE FROM $table_schedule WHERE template_id = {$this->template_id}");
		$wpdb->query("DELETE FROM $table_history WHERE template_id = {$this->template_id}");

		parent::tearDown();
	}

	private function create_test_structures() {
		// Create 2 structures to test sequential rotation
		$structures = array(
			array(
				'name' => 'Bolt Test Structure A',
				'description' => 'A',
				'structure_data' => '{}',
				'is_active' => 1,
				'is_default' => 1,
			),
			array(
				'name' => 'Bolt Test Structure B',
				'description' => 'B',
				'structure_data' => '{}',
				'is_active' => 1,
				'is_default' => 0,
			),
		);

		foreach ($structures as $structure) {
			$this->structure_repo->create($structure);
		}
	}

	/**
	 * Verifies that passing created_at in the schedule object is respected.
	 *
	 * Scenario:
	 * 1. Schedule created at T0 (in DB).
	 * 2. History created at T1 (T1 > T0).
	 * 3. We construct a schedule object with created_at = T2 (T2 > T1).
	 *
	 * - Without optimization: The code fetches T0 from DB, sees T1 > T0, counts 1.
	 * - With optimization: The code uses T2, sees T1 < T2, counts 0.
	 */
	public function test_optimization_uses_passed_created_at() {
		global $wpdb;

		// 1. Create Schedule in DB at T0 ('2023-01-01 12:00:00')
		$schedule_data = array(
			'template_id' => $this->template_id,
			'frequency' => 'daily',
			'next_run' => '2023-01-02 12:00:00',
			'is_active' => 1,
			'rotation_pattern' => 'sequential',
		);
		// Manually insert to force created_at
		$table_schedule = $wpdb->prefix . 'aips_schedule';
		$wpdb->insert($table_schedule, array_merge($schedule_data, ['created_at' => '2023-01-01 12:00:00']));
		$schedule_id = $wpdb->insert_id;

		// 2. Create History at T1 ('2023-02-01 12:00:00')
		$table_history = $wpdb->prefix . 'aips_history';
		$wpdb->insert($table_history, array(
			'template_id' => $this->template_id,
			'status' => 'completed',
			'created_at' => '2023-02-01 12:00:00'
		));

		// Clear cache just in case
		delete_transient('aips_sched_cnt_' . $schedule_id);

		// 3. Test Control: Normal behavior (fetches from DB or uses T0 if passed matching DB)
		// If we pass ID, it should look up DB, find T0, see history > T0, count = 1.
		// Sequential: 1 % 2 = 1 => Structure B (second one).

		// Get structures to identify IDs
		$structures = $this->structure_repo->get_all(true);
		// Filter for our test structures
		$test_structures = array_values(array_filter($structures, function($s) {
			return strpos($s->name, 'Bolt Test') !== false;
		}));

		$this->assertCount(2, $test_structures, "Should have 2 test structures");
		$structure_A_id = $test_structures[0]->id;
		$structure_B_id = $test_structures[1]->id;

		// Control check
		$schedule_from_db = $this->schedule_repo->get_by_id($schedule_id);
		$selected_id_control = $this->selector->select_structure($schedule_from_db);

		// Count should be 1. 1 % 2 = 1. Should be Structure B (index 1).
		// Wait, select_sequential Logic: $index = $count % count($active_structures);
		// If we have other active structures in DB, this math changes.
		// We should ensure only our test structures are active or mock the active list.
		// But select_by_pattern fetches all active structures.
		// Let's rely on the count directly if we could access it, but we can't.
		// Instead, we can observe that with T2 (Mar 1), count should be 0.
		// 0 % N = 0. Should be Structure A (index 0).

		// Note: This relies on the sort order of structures. Repo sorts by name ASC.
		// 'Bolt Test Structure A' comes before 'Bolt Test Structure B'.
		// But if there are other structures "A..." they might come before.
		// To be robust, we assume checking the optimization logic via the count effect is enough.
		// Even better, we can verify if the result CHANGES when we supply T2.

		// Clear cache again
		delete_transient('aips_sched_cnt_' . $schedule_id);

		// 4. Test Optimization: Pass object with T2 ('2023-03-01 12:00:00')
		$schedule_optimized = (object) array(
			'id' => $schedule_id,
			'template_id' => $this->template_id,
			'rotation_pattern' => 'sequential',
			'created_at' => '2023-03-01 12:00:00' // Future date! History (Feb) is older than this.
		);

		$selected_id_optimized = $this->selector->select_structure($schedule_optimized);

		// Analysis:
		// If unoptimized: It ignores 'created_at', queries DB (Jan 1), finds history (Feb 1), count = 1.
		// If optimized: It uses 'created_at' (Mar 1), history (Feb 1) < Mar 1, count = 0.

		// So if optimized, the count is different (0 vs 1).
		// Consequently, the selected structure index will be different (0 vs 1).
		// So $selected_id_optimized should != $selected_id_control.

		$this->assertNotEquals($selected_id_control, $selected_id_optimized, "Optimization failed: Result should differ when passing a future created_at date.");

		// We can also verify that optimized selected the FIRST structure (index 0).
		// Assuming 'Bolt Test Structure A' is the very first active structure alphabetically?
		// Maybe not. But we know index 0 != index 1.
	}
}
