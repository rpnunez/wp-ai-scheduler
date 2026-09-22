<?php
/**
 * Tests for AIPS_Prompt_Profiles_Repository.
 *
 * @package AI_Post_Scheduler
 */

class Mock_WPDB_Stateful_Prompt_Profiles {
	public $prefix = 'wp_';
	public $insert_id = 0;
	public $last_error = '';
	public $table_name;
	public $data = array();

	public function __construct() {
		$this->table_name = $this->prefix . 'aips_prompt_profiles';
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

	public function update($table, $data, $where, $format = null, $where_format = null) {
		if (!isset($this->data[$table])) {
			return false;
		}

		if (isset($where['id'])) {
			$id = (int) $where['id'];
			if (isset($this->data[$table][$id])) {
				foreach ($data as $k => $v) {
					$this->data[$table][$id]->$k = $v;
				}
				return 1;
			}
			return 0;
		}

		return 0;
	}

	public function delete($table, $where, $where_format = null) {
		if (!isset($this->data[$table])) {
			return false;
		}

		if (isset($where['id'])) {
			$id = (int) $where['id'];
			if (isset($this->data[$table][$id])) {
				unset($this->data[$table][$id]);
				return 1;
			}
			return 0;
		}

		return 0;
	}

	public function get_row($query, $output = OBJECT, $y = 0) {
		$table = $this->table_name;
		if (!isset($this->data[$table])) {
			return null;
		}

		// By ID
		if (preg_match('/WHERE id = (\d+)/', $query, $m)) {
			$id = (int) $m[1];
			return isset($this->data[$table][$id]) ? clone $this->data[$table][$id] : null;
		}

		// By Slug
		if (preg_match("/WHERE slug = '([^']+)'/", $query, $m)) {
			$slug = $m[1];
			foreach ($this->data[$table] as $row) {
				if (isset($row->slug) && $row->slug === $slug) {
					return clone $row;
				}
			}
			return null;
		}

		// Default and active
		if (strpos($query, 'is_default = 1 AND is_active = 1') !== false) {
			foreach ($this->data[$table] as $row) {
				if (!empty($row->is_default) && !empty($row->is_active)) {
					return clone $row;
				}
			}
			return null;
		}

		// First active
		if (strpos($query, 'WHERE is_active = 1 ORDER BY id ASC') !== false || strpos($query, 'WHERE is_active = 1') !== false) {
			foreach ($this->data[$table] as $row) {
				if (!empty($row->is_active)) {
					return clone $row;
				}
			}
			return null;
		}

		return null;
	}

	public function get_results($query, $output = OBJECT) {
		$table = $this->table_name;
		if (!isset($this->data[$table])) {
			return array();
		}

		$rows = array();
		foreach ($this->data[$table] as $row) {
			if (strpos($query, 'WHERE is_active = 1') !== false) {
				if (empty($row->is_active)) {
					continue;
				}
			}
			$rows[] = clone $row;
		}

		return $rows;
	}

	public function get_var($query, $x = 0, $y = 0) {
		$table = $this->table_name;
		if (!isset($this->data[$table])) {
			return null;
		}

		// COUNT(*) WHERE id != %d AND is_active = 1
		if (preg_match('/SELECT COUNT\(\*\) FROM .* WHERE id != (\d+) AND is_active = 1/', $query, $m)) {
			$exclude_id = (int) $m[1];
			$count = 0;
			foreach ($this->data[$table] as $row) {
				if ((int) $row->id !== $exclude_id && !empty($row->is_active)) {
					$count++;
				}
			}
			return $count;
		}

		// Slug check for generate_unique_slug
		if (preg_match("/SELECT id FROM .* WHERE slug = '([^']+)'/", $query, $m)) {
			$slug = $m[1];
			$exclude_id = 0;
			if (preg_match('/AND id != (\d+)/', $query, $em)) {
				$exclude_id = (int) $em[1];
			}
			foreach ($this->data[$table] as $row) {
				if (isset($row->slug) && $row->slug === $slug && (int) $row->id !== $exclude_id) {
					return $row->id;
				}
			}
			return null;
		}

		return null;
	}

	public function query($query) {
		$table = $this->table_name;
		if (!isset($this->data[$table])) {
			return true;
		}

		// UPDATE ... SET is_default = 0 [WHERE id != %d]
		if (strpos($query, 'SET is_default = 0') !== false) {
			$except_id = 0;
			if (preg_match('/WHERE id != (\d+)/', $query, $m)) {
				$except_id = (int) $m[1];
			}
			foreach ($this->data[$table] as $row) {
				if ((int) $row->id !== $except_id) {
					$row->is_default = 0;
				}
			}
			return true;
		}

		return true;
	}
}

class Test_AIPS_Prompt_Profiles_Repository extends WP_UnitTestCase {

	/** @var Mock_WPDB_Stateful_Prompt_Profiles */
	private $wpdb_mock;

	/** @var wpdb */
	private $original_wpdb;

	/** @var AIPS_Prompt_Profiles_Repository */
	private $repository;

	public function setUp(): void {
		parent::setUp();
		global $wpdb;
		$this->original_wpdb = $wpdb;
		$this->wpdb_mock = new Mock_WPDB_Stateful_Prompt_Profiles();
		$wpdb = $this->wpdb_mock;

		// Reset singleton instance
		$ref = new ReflectionProperty('AIPS_Prompt_Profiles_Repository', 'instance');
		$ref->setAccessible(true);
		$ref->setValue(null, null);

		$this->repository = new AIPS_Prompt_Profiles_Repository();
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb = $this->original_wpdb;
		parent::tearDown();
	}

	public function test_create_generates_unique_slug_and_persists() {
		$id1 = $this->repository->create(array(
			'name'        => 'Marketing Lead',
			'description' => 'Test profile description',
			'is_active'   => 1,
		));

		$this->assertIsInt($id1);
		$this->assertGreaterThan(0, $id1);

		$profile1 = $this->repository->get_by_id($id1);
		$this->assertNotNull($profile1);
		$this->assertEquals('Marketing Lead', $profile1->name);
		$this->assertEquals('marketing-lead', $profile1->slug);
		$this->assertEquals(0, $profile1->is_builtin);

		// Second profile with same name gets slug with suffix
		$id2 = $this->repository->create(array(
			'name'      => 'Marketing Lead',
			'is_active' => 1,
		));

		$this->assertIsInt($id2);
		$profile2 = $this->repository->get_by_id($id2);
		$this->assertEquals('marketing-lead-2', $profile2->slug);
	}

	public function test_decorate_profile_marks_builtin_archetypes() {
		$id = $this->repository->create(array(
			'name'      => 'SEO Maximizer',
			'slug'      => 'seo-maximizer',
			'is_active' => 1,
		));

		$profile = $this->repository->get_by_id($id);
		$this->assertEquals(1, $profile->is_builtin);

		$by_slug = $this->repository->get_by_slug('seo-maximizer');
		$this->assertEquals(1, $by_slug->is_builtin);
	}

	public function test_cannot_delete_builtin_archetype() {
		$id = $this->repository->create(array(
			'name'      => 'Standard Default',
			'slug'      => 'standard-default',
			'is_active' => 1,
		));

		$result = $this->repository->delete($id);
		$this->assertWPError($result);
		$this->assertEquals('cannot_delete_builtin', $result->get_error_code());
	}

	public function test_cannot_delete_sole_default_profile() {
		$id = $this->repository->create(array(
			'name'       => 'Custom Profile Only',
			'slug'       => 'custom-profile-only',
			'is_default' => 1,
			'is_active'  => 1,
		));

		$result = $this->repository->delete($id);
		$this->assertWPError($result);
		$this->assertEquals('cannot_delete_sole_default', $result->get_error_code());
	}

	public function test_delete_normal_profile_succeeds() {
		$id1 = $this->repository->create(array(
			'name'       => 'Default Profile',
			'slug'       => 'default-profile',
			'is_default' => 1,
			'is_active'  => 1,
		));

		$id2 = $this->repository->create(array(
			'name'       => 'Extra Profile',
			'slug'       => 'extra-profile',
			'is_default' => 0,
			'is_active'  => 1,
		));

		$result = $this->repository->delete($id2);
		$this->assertTrue($result);
		$this->assertNull($this->repository->get_by_id($id2));
	}

	public function test_set_default_swaps_default_flag() {
		$id1 = $this->repository->create(array(
			'name'       => 'Profile One',
			'is_default' => 1,
			'is_active'  => 1,
		));

		$id2 = $this->repository->create(array(
			'name'       => 'Profile Two',
			'is_default' => 0,
			'is_active'  => 1,
		));

		$this->repository->set_default($id2);

		$p1 = $this->repository->get_by_id($id1);
		$p2 = $this->repository->get_by_id($id2);

		$this->assertEquals(0, $p1->is_default);
		$this->assertEquals(1, $p2->is_default);
	}
}
