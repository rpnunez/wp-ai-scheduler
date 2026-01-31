<?php
/**
 * Tests for Schedule Repository
 *
 * @package AI_Post_Scheduler
 */

if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        return false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        return true;
    }
}

class MockWPDB {
    public $prefix = 'wp_';
    public $insert_id = 0;
    public $last_error = '';

    private $tables = array();

    public function __construct() {
        // Initialize tables
        $this->tables['wp_aips_schedule'] = array();
        $this->tables['wp_aips_templates'] = array();
    }

    public function prepare($query, ...$args) {
        if (empty($args)) {
            return $query;
        }
        foreach ($args as $arg) {
            $query = preg_replace('/%[sd]/', is_numeric($arg) ? $arg : "'$arg'", $query, 1);
        }
        return $query;
    }

    public function get_results($query, $output = OBJECT) {
        // Normalize whitespace
        $query = preg_replace('/\s+/', ' ', $query);
        $query = trim($query);

        $results = array();

        // Handle get_upcoming
        // Regex adjusted for normalized whitespace
        if (preg_match('/SELECT s\.\*, t\.name as template_name FROM wp_aips_schedule s LEFT JOIN wp_aips_templates t ON s\.template_id = t\.id WHERE s\.is_active = 1 ORDER BY s\.next_run ASC LIMIT (\d+)/i', $query, $matches)) {
            $limit = intval($matches[1]);
            $schedule_rows = $this->tables['wp_aips_schedule'];

            // Filter active
            $active_rows = array_filter($schedule_rows, function($row) {
                return isset($row['is_active']) && $row['is_active'] == 1;
            });

            // Sort by next_run
            usort($active_rows, function($a, $b) {
                return strcmp($a['next_run'], $b['next_run']);
            });

            // Join with templates
            $joined = array();
            foreach ($active_rows as $row) {
                $template_name = 'Unknown';
                foreach ($this->tables['wp_aips_templates'] as $t) {
                    if ($t['id'] == $row['template_id']) {
                        $template_name = $t['name'];
                        break;
                    }
                }
                $obj = (object) $row;
                $obj->template_name = $template_name;
                $joined[] = $obj;
            }

            return array_slice($joined, 0, $limit);
        }

        // Handle get_by_id
        if (preg_match('/SELECT \* FROM wp_aips_schedule WHERE id = (\d+)/i', $query, $matches)) {
            $id = intval($matches[1]);
            foreach ($this->tables['wp_aips_schedule'] as $row) {
                if ($row['id'] == $id) {
                    return (object) $row;
                }
            }
            return null;
        }

        // Handle get_due_schedules
        if (strpos($query, 'SELECT s.*, t.name as template_name') !== false && strpos($query, 'WHERE s.is_active = 1') !== false && strpos($query, 'AND s.next_run <=') !== false) {
             $schedule_rows = $this->tables['wp_aips_schedule'];
             $results = [];

             // Extract time comparison
             if (preg_match("/s\.next_run <= '([^']+)'/", $query, $time_matches)) {
                 $check_time = $time_matches[1];

                 foreach ($schedule_rows as $row) {
                     if ($row['is_active'] == 1) {
                         if ($row['next_run'] <= $check_time) {
                            $obj = (object) $row;
                            $obj->template_name = 'Mock Template';
                            $results[] = $obj;
                         }
                     }
                 }
             }

             // Sort by next_run ASC as per query
             usort($results, function($a, $b) {
                 return strcmp($a->next_run, $b->next_run);
             });

             return $results;
        }

        return array();
    }

    public function get_row($query, $output = OBJECT, $y = 0) {
        $res = $this->get_results($query, $output);
        if (is_array($res)) {
            // If get_results returned an array of objects (like get_by_id logic above which actually returns object directly in my bad logic, let's fix it)
            // Wait, get_results returns array. get_row returns object.

            // If get_results logic returned single object, return it
            if (is_object($res)) return $res;

            return !empty($res) ? $res[0] : null;
        }
        return $res; // It was already an object or null
    }

    public function insert($table, $data, $format = null) {
        static $next_id = 1;
        if (!isset($this->tables[$table])) {
            $this->tables[$table] = array();
        }

        $data['id'] = $next_id++;
        $this->tables[$table][] = $data;
        $this->insert_id = $data['id'];
        return true;
    }

    public function update($table, $data, $where, $format = null, $where_format = null) {
        if (!isset($this->tables[$table])) return false;

        $updated = false;
        foreach ($this->tables[$table] as &$row) {
            // Simplified WHERE: only supports id
            if (isset($where['id']) && $row['id'] == $where['id']) {
                $row = array_merge($row, $data);
                $updated = true;
            }
        }
        return $updated;
    }

    public function delete($table, $where, $where_format = null) {
        if (!isset($this->tables[$table])) return false;

        foreach ($this->tables[$table] as $key => $row) {
            if (isset($where['id']) && $row['id'] == $where['id']) {
                unset($this->tables[$table][$key]);
                return true;
            }
        }
        return false;
    }

    public function query($query) {
        if (strpos($query, 'DELETE FROM') === 0) {
            // Simplified DELETE ALL
            if (preg_match('/DELETE FROM (\S+)/', $query, $matches)) {
                $table = $matches[1];
                $this->tables[$table] = array();
                return true;
            }
        }
        return true;
    }
}

class Test_AIPS_Schedule_Repository extends WP_UnitTestCase {

	private $repository;
	private $template_repo;

	public function setUp(): void {
		parent::setUp();

        // Override global wpdb with our stateful mock
        $GLOBALS['wpdb'] = new MockWPDB();

		$this->repository = new AIPS_Schedule_Repository();
		$this->template_repo = new AIPS_Template_Repository();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Helper to create a dummy template.
	 */
	private function create_template($name = 'Test Template') {
		return $this->template_repo->create(array(
			'name' => $name,
			'prompt_template' => 'Write about {{topic}}',
			'post_status' => 'draft',
            'post_category' => 1,
            'post_tags' => '',
            'post_author' => 1,
            'generate_featured_image' => 0,
            'featured_image_source' => 'ai_prompt',
            'featured_image_unsplash_keywords' => '',
            'is_active' => 1
		));
	}

	/**
	 * Test creating a schedule.
	 */
	public function test_create_schedule() {
		$template_id = $this->create_template();

		$schedule_id = $this->repository->create(array(
			'template_id' => $template_id,
			'frequency' => 'daily',
			'next_run' => date('Y-m-d H:i:s', strtotime('+1 day')),
			'is_active' => 1,
			'topic' => 'Test Topic',
		));

		$this->assertIsInt($schedule_id);
		$this->assertGreaterThan(0, $schedule_id);

		$schedule = $this->repository->get_by_id($schedule_id);
		$this->assertEquals($template_id, $schedule->template_id);
		$this->assertEquals('daily', $schedule->frequency);
		$this->assertEquals('Test Topic', $schedule->topic);
	}

	/**
	 * Test updating a schedule.
	 */
	public function test_update_schedule() {
		$template_id = $this->create_template();
		$schedule_id = $this->repository->create(array(
			'template_id' => $template_id,
			'frequency' => 'daily',
			'next_run' => date('Y-m-d H:i:s', strtotime('+1 day')),
			'is_active' => 1,
		));

		$updated = $this->repository->update($schedule_id, array(
			'frequency' => 'weekly',
			'is_active' => 0,
		));

		$this->assertTrue($updated);

		$schedule = $this->repository->get_by_id($schedule_id);
		$this->assertEquals('weekly', $schedule->frequency);
		$this->assertEquals(0, $schedule->is_active);
	}

	/**
	 * Test deleting a schedule.
	 */
	public function test_delete_schedule() {
		$template_id = $this->create_template();
		$schedule_id = $this->repository->create(array(
			'template_id' => $template_id,
			'frequency' => 'daily',
			'next_run' => date('Y-m-d H:i:s', strtotime('+1 day')),
		));

		$deleted = $this->repository->delete($schedule_id);
		$this->assertTrue($deleted);

		$schedule = $this->repository->get_by_id($schedule_id);
		$this->assertNull($schedule);
	}

	/**
	 * Test getting upcoming schedules.
	 */
	public function test_get_upcoming() {
		$template_id = $this->create_template('Upcoming Template');

		// Schedule 1: Tomorrow
		$this->repository->create(array(
			'template_id' => $template_id,
			'frequency' => 'daily',
			'next_run' => date('Y-m-d H:i:s', strtotime('+1 day')),
			'is_active' => 1,
		));

		// Schedule 2: In 2 days
		$this->repository->create(array(
			'template_id' => $template_id,
			'frequency' => 'daily',
			'next_run' => date('Y-m-d H:i:s', strtotime('+2 days')),
			'is_active' => 1,
		));

		// Schedule 3: Inactive (should be ignored)
		$this->repository->create(array(
			'template_id' => $template_id,
			'frequency' => 'daily',
			'next_run' => date('Y-m-d H:i:s', strtotime('+1 hour')),
			'is_active' => 0,
		));

		$upcoming = $this->repository->get_upcoming(5);

		$this->assertCount(2, $upcoming);
		// Check order
		$this->assertLessThan($upcoming[1]->next_run, $upcoming[0]->next_run);
		// Check join
		$this->assertEquals('Upcoming Template', $upcoming[0]->template_name);
	}

    /**
     * Test get_due_schedules
     */
    public function test_get_due_schedules() {
        $template_id = $this->create_template();

        // Due now (active)
        $this->repository->create(array(
            'template_id' => $template_id,
            'frequency' => 'daily',
            'next_run' => date('Y-m-d H:i:s', strtotime('-1 minute')),
            'is_active' => 1,
        ));

        // Future (active) - not due
        $this->repository->create(array(
            'template_id' => $template_id,
            'frequency' => 'daily',
            'next_run' => date('Y-m-d H:i:s', strtotime('+10 minutes')),
            'is_active' => 1,
        ));

        // Due now (inactive) - should not be picked
        $this->repository->create(array(
            'template_id' => $template_id,
            'frequency' => 'daily',
            'next_run' => date('Y-m-d H:i:s', strtotime('-1 minute')),
            'is_active' => 0,
        ));

        // We need to pass current time to ensure consistent testing
        $due = $this->repository->get_due_schedules(current_time('mysql'));

        $this->assertCount(1, $due);
    }
}
