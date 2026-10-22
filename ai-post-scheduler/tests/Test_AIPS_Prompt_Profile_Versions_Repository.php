<?php
/**
 * Tests for AIPS_Prompt_Profile_Versions_Repository.
 *
 * @package AI_Post_Scheduler
 */

class Mock_WPDB_Stateful_Prompt_Profile_Versions {
	public $prefix = 'wp_';
	public $insert_id = 0;
	public $last_error = '';
	public $table_name;
	public $data = array();

	public function __construct() {
		$this->table_name = $this->prefix . 'aips_prompt_profile_versions';
		$this->data[$this->table_name] = array();
	}

	public function prepare($query, ...$args) {
		if (empty($args)) {
			return $query;
		}
		if (count($args) === 1 && is_array($args[0])) {
			$args = $args[0];
		}
		foreach ($args as $arg) {
			$replacement = is_numeric($arg) ? $arg : "'" . addslashes($arg) . "'";
			$query = preg_replace('/%[sd]/', $replacement, $query, 1);
		}
		return $query;
	}

	public function insert($table, $data, $format = null) {
		$this->insert_id++;
		$data['id'] = (int) $this->insert_id;
		$row = (object) $data;
		$this->data[$table][$this->insert_id] = $row;
		return 1;
	}

	public function get_row($query, $output = OBJECT, $y = 0) {
		$table = $this->table_name;
		if (!isset($this->data[$table])) {
			return null;
		}

		if (preg_match('/WHERE id = (\d+)/', $query, $m)) {
			$id = (int) $m[1];
			return isset($this->data[$table][$id]) ? clone $this->data[$table][$id] : null;
		}

		return null;
	}

	public function get_results($query, $output = OBJECT) {
		$table = $this->table_name;
		if (!isset($this->data[$table])) {
			return array();
		}

		$rows = array();

		if (preg_match('/WHERE profile_id = (\d+)/', $query, $m)) {
			$profile_id = (int) $m[1];
			foreach ($this->data[$table] as $row) {
				if ((int) $row->profile_id === $profile_id) {
					$rows[] = clone $row;
				}
			}
		} else {
			foreach ($this->data[$table] as $row) {
				$rows[] = clone $row;
			}
		}

		if (strpos($query, 'ORDER BY version_number DESC') !== false) {
			usort($rows, function ($a, $b) {
				return (int) $b->version_number - (int) $a->version_number;
			});
		}

		return $rows;
	}

	public function get_var($query, $x = 0, $y = 0) {
		$table = $this->table_name;
		if (!isset($this->data[$table])) {
			return null;
		}

		if (preg_match('/SELECT MAX\(version_number\) FROM .* WHERE profile_id = (\d+)/', $query, $m)) {
			$profile_id = (int) $m[1];
			$max = null;
			foreach ($this->data[$table] as $row) {
				if ((int) $row->profile_id === $profile_id) {
					$max = $max === null ? (int) $row->version_number : max($max, (int) $row->version_number);
				}
			}
			return $max;
		}

		return null;
	}

	public function query($query) {
		return true;
	}
}

class Test_AIPS_Prompt_Profile_Versions_Repository extends WP_UnitTestCase {

	/** @var Mock_WPDB_Stateful_Prompt_Profile_Versions */
	private $wpdb_mock;

	/** @var wpdb */
	private $original_wpdb;

	/** @var AIPS_Prompt_Profile_Versions_Repository */
	private $repository;

	public function setUp(): void {
		parent::setUp();
		global $wpdb;
		$this->original_wpdb = $wpdb;
		$this->wpdb_mock = new Mock_WPDB_Stateful_Prompt_Profile_Versions();
		$wpdb = $this->wpdb_mock;

		// Reset singleton instance
		$ref = new ReflectionProperty('AIPS_Prompt_Profile_Versions_Repository', 'instance');
		$ref->setAccessible(true);
		$ref->setValue(null, null);

		$this->repository = new AIPS_Prompt_Profile_Versions_Repository();
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb = $this->original_wpdb;
		parent::tearDown();
	}

	public function test_get_latest_version_number_returns_zero_when_none_exist() {
		$this->assertSame(0, $this->repository->get_latest_version_number(999));
	}

	public function test_create_snapshot_auto_increments_version_number() {
		$stages = array('title_prompt' => 'Write a title.');

		$id1 = $this->repository->create_snapshot(5, $stages, array(), 'First snapshot', 1);
		$this->assertIsInt($id1);
		$this->assertGreaterThan(0, $id1);

		$version1 = $this->repository->get_version($id1);
		$this->assertSame(1, $version1->version_number);

		$id2 = $this->repository->create_snapshot(5, $stages, array(), 'Second snapshot', 1);
		$version2 = $this->repository->get_version($id2);
		$this->assertSame(2, $version2->version_number);

		$this->assertSame(2, $this->repository->get_latest_version_number(5));
	}

	public function test_create_snapshot_rejects_invalid_profile_id() {
		$result = $this->repository->create_snapshot(0, array(), array(), 'summary', 1);
		$this->assertWPError($result);
		$this->assertSame('invalid_profile_id', $result->get_error_code());
	}

	public function test_get_version_json_round_trips_stages_and_parameters() {
		$stages = array(
			'title_prompt'   => 'Write a title about {{topic}}.',
			'content_prompt' => 'Write the full article.',
		);
		$parameters = array('temperature' => 0.7, 'max_tokens' => 2048);

		$id = $this->repository->create_snapshot(7, $stages, $parameters, 'Snapshot with params', 42);

		$version = $this->repository->get_version($id);
		$this->assertIsArray($version->stages);
		$this->assertSame($stages, $version->stages);
		$this->assertIsArray($version->parameters);
		$this->assertSame($parameters, $version->parameters);
		$this->assertSame(7, (int) $version->profile_id);
		$this->assertSame(42, (int) $version->created_by);
		$this->assertSame('Snapshot with params', $version->change_summary);
	}

	public function test_get_version_returns_empty_array_parameters_when_not_provided() {
		$id = $this->repository->create_snapshot(3, array('title_prompt' => 'x'), array(), 'No params', null);

		$version = $this->repository->get_version($id);
		$this->assertSame(array(), $version->parameters);
	}

	public function test_get_version_returns_null_when_not_found() {
		$this->assertNull($this->repository->get_version(99999));
	}

	public function test_get_versions_orders_newest_first() {
		$this->repository->create_snapshot(11, array('title_prompt' => 'v1'), array(), 'v1 summary', 1);
		$this->repository->create_snapshot(11, array('title_prompt' => 'v2'), array(), 'v2 summary', 1);
		$this->repository->create_snapshot(11, array('title_prompt' => 'v3'), array(), 'v3 summary', 1);

		// A version for a different profile must not leak into the results.
		$this->repository->create_snapshot(22, array('title_prompt' => 'other profile'), array(), 'other', 1);

		$versions = $this->repository->get_versions(11);

		$this->assertCount(3, $versions);
		$this->assertSame(3, $versions[0]->version_number);
		$this->assertSame(2, $versions[1]->version_number);
		$this->assertSame(1, $versions[2]->version_number);

		foreach ($versions as $version) {
			$this->assertSame(11, (int) $version->profile_id);
			$this->assertIsArray($version->stages);
		}
	}

	public function test_get_versions_returns_empty_array_for_unknown_profile() {
		$this->assertSame(array(), $this->repository->get_versions(777));
	}
}
