<?php
/**
 * Tests for AIPS_Voices_Repository
 *
 * @package AI_Post_Scheduler
 */

class Mock_WPDB_Stateful_Voices {
    public $prefix = 'wp_';
    public $insert_id = 0;
    private $data = array();

    public function esc_like($text) {
        return addcslashes($text, '_%\\');
    }

    public function prepare($query, ...$args) {
        if (empty($args)) {
            return $query;
        }
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }
        foreach ($args as $arg) {
            $query = preg_replace('/%[sd]/', is_numeric($arg) ? $arg : "'$arg'", $query, 1);
        }
        return $query;
    }

    public function insert($table, $data, $format = null) {
        $this->insert_id++;
        $data['id'] = $this->insert_id;
        $this->data[$table][$this->insert_id] = (object) $data;
        return true;
    }

    public function update($table, $data, $where, $format = null, $where_format = null) {
        if (!isset($this->data[$table])) return false;

        $id = $where['id'];
        if (isset($this->data[$table][$id])) {
            foreach ($data as $key => $value) {
                $this->data[$table][$id]->$key = $value;
            }
            return true;
        }
        return false;
    }

    public function delete($table, $where, $where_format = null) {
        if (!isset($this->data[$table])) return false;

        $id = $where['id'];
        if (isset($this->data[$table][$id])) {
            unset($this->data[$table][$id]);
            return true;
        }
        return false;
    }

    public function get_row($query, $output = OBJECT, $y = 0) {
        if (preg_match('/WHERE id = (\d+)/', $query, $matches)) {
            $id = (int)$matches[1];
            foreach ($this->data as $table => $rows) {
                 if (isset($rows[$id])) return $rows[$id];
            }
        }
        return null;
    }

    public function get_results($query, $output = OBJECT) {
        $table_name = null;
        foreach (array_keys($this->data) as $t) {
            if (strpos($query, $t) !== false) {
                $table_name = $t;
                break;
            }
        }

        if (!$table_name || !isset($this->data[$table_name])) return array();

        $results = array_values($this->data[$table_name]);

        if (strpos($query, 'is_active = 1') !== false) {
            $results = array_filter($results, function($row) {
                return isset($row->is_active) && $row->is_active == 1;
            });
        }

        if (preg_match("/name LIKE '([^']+)'/", $query, $matches)) {
             $term = trim($matches[1], "%"); // remove wildcard
             $results = array_filter($results, function($row) use ($term) {
                return stripos($row->name, $term) !== false;
            });
        }

        return array_values($results);
    }

    public function query($query) {
        $this->data = array();
        return true;
    }
}

class AIPS_Voices_Repository_Test extends WP_UnitTestCase {

    private $repository;
    private $original_wpdb;

    public function setUp(): void {
        parent::setUp();
        global $wpdb;
        $this->original_wpdb = $wpdb;
        $wpdb = new Mock_WPDB_Stateful_Voices();

        $this->repository = new AIPS_Voices_Repository();
    }

    public function tearDown(): void {
        global $wpdb;
        $wpdb = $this->original_wpdb;
        parent::tearDown();
    }

    public function test_create_voice() {
        $data = array(
            'name' => 'Test Voice',
            'title_prompt' => 'Title Prompt',
            'content_instructions' => 'Content Instructions',
            'excerpt_instructions' => 'Excerpt Instructions',
            'is_active' => 1,
        );

        $id = $this->repository->create($data);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $voice = $this->repository->get_by_id($id);
        $this->assertEquals('Test Voice', $voice->name);
        $this->assertEquals('Title Prompt', $voice->title_prompt);
        $this->assertEquals(1, $voice->is_active);
    }

    public function test_update_voice() {
        $data = array(
            'name' => 'Test Voice Update',
            'title_prompt' => 'Title Prompt',
            'content_instructions' => 'Content Instructions',
            'is_active' => 1,
        );

        $id = $this->repository->create($data);

        $update_data = array(
            'name' => 'Test Voice Updated',
            'title_prompt' => 'Updated Title Prompt',
        );

        $result = $this->repository->update($id, $update_data);
        $this->assertTrue($result);

        $voice = $this->repository->get_by_id($id);
        $this->assertEquals('Test Voice Updated', $voice->name);
        $this->assertEquals('Updated Title Prompt', $voice->title_prompt);
        $this->assertEquals('Content Instructions', $voice->content_instructions); // Should remain unchanged
    }

    public function test_delete_voice() {
        $data = array(
            'name' => 'Test Voice Delete',
            'title_prompt' => 'Title Prompt',
            'content_instructions' => 'Content Instructions',
            'is_active' => 1,
        );

        $id = $this->repository->create($data);
        $result = $this->repository->delete($id);

        $this->assertTrue($result);

        $voice = $this->repository->get_by_id($id);
        $this->assertNull($voice);
    }

    public function test_get_all_voices() {
        $data1 = array(
            'name' => 'Test Voice A',
            'title_prompt' => 'Title Prompt',
            'content_instructions' => 'Content Instructions',
            'is_active' => 1,
        );

        $data2 = array(
            'name' => 'Test Voice B',
            'title_prompt' => 'Title Prompt',
            'content_instructions' => 'Content Instructions',
            'is_active' => 0,
        );

        $this->repository->create($data1);
        $this->repository->create($data2);

        $all = $this->repository->get_all();
        $active_only = $this->repository->get_all(true);

        $this->assertGreaterThanOrEqual(2, count($all));

        // Verify active only filter
        $found_inactive = false;
        foreach ($active_only as $voice) {
            if ($voice->name === 'Test Voice B') {
                $found_inactive = true;
                break;
            }
        }
        $this->assertFalse($found_inactive);
    }

    public function test_search_voices() {
        $data = array(
            'name' => 'Test Voice Searchable',
            'title_prompt' => 'Title Prompt',
            'content_instructions' => 'Content Instructions',
            'is_active' => 1,
        );

        $this->repository->create($data);

        $results = $this->repository->search('Searchable');

        $this->assertNotEmpty($results);
        $found = false;
        foreach ($results as $voice) {
            if ($voice->name === 'Test Voice Searchable') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);

        // Search respects active flag
        $data_inactive = array(
            'name' => 'Test Voice Searchable Inactive',
            'title_prompt' => 'Title Prompt',
            'content_instructions' => 'Content Instructions',
            'is_active' => 0,
        );
        $this->repository->create($data_inactive);

        $results_inactive = $this->repository->search('Searchable Inactive');
        $this->assertEmpty($results_inactive);
    }
}
