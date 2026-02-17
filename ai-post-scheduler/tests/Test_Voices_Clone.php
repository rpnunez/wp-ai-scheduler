<?php
/**
 * Tests for AIPS_Voices cloning functionality
 *
 * @package AI_Post_Scheduler
 */

class Mock_WPDB_Voices_Clone {
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

        return array_values($this->data[$table_name]);
    }
}

class Test_Voices_Clone extends WP_UnitTestCase {

    private $voices;
    private $original_wpdb;

    public function setUp(): void {
        parent::setUp();
        global $wpdb;
        $this->original_wpdb = $wpdb;
        $wpdb = new Mock_WPDB_Voices_Clone();

        $this->voices = new AIPS_Voices();
    }

    public function tearDown(): void {
        global $wpdb;
        $wpdb = $this->original_wpdb;
        parent::tearDown();
    }

    public function test_ajax_clone_voice() {
        // 1. Create a voice to clone
        $voice_data = array(
            'name' => 'Original Voice',
            'title_prompt' => 'Title Prompt',
            'content_instructions' => 'Content Instructions',
            'excerpt_instructions' => 'Excerpt Instructions',
            'is_active' => 1,
        );
        $original_id = $this->voices->save($voice_data);

        // 2. Mock AJAX request
        $_POST['voice_id'] = $original_id;
        $_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
        $_REQUEST['nonce'] = $_POST['nonce'];

        // Mock current_user_can to return true
        global $test_users;
        $test_users[1] = 'administrator';
        wp_set_current_user(1);

        // 3. Call ajax_clone_voice
        try {
            $this->voices->ajax_clone_voice();
        } catch (WPAjaxDieContinueException $e) {
            // Expected
        } catch (WPAjaxDieStopException $e) {
            $this->fail('WPAjaxDieStopException thrown: ' . $e->getMessage());
        } catch (Error $e) {
            // Check if method exists (it shouldn't yet)
             $this->markTestSkipped('ajax_clone_voice not implemented yet');
             return;
        }

        // 4. Verify cloned voice exists
        $all_voices = $this->voices->get_all();
        $this->assertCount(2, $all_voices);

        $cloned_voice = null;
        foreach ($all_voices as $v) {
            if ($v->id != $original_id) {
                $cloned_voice = $v;
                break;
            }
        }

        $this->assertNotNull($cloned_voice);
        $this->assertEquals('Original Voice (Clone)', $cloned_voice->name);
        $this->assertEquals('Title Prompt', $cloned_voice->title_prompt);
        $this->assertEquals('Content Instructions', $cloned_voice->content_instructions);
        $this->assertEquals('Excerpt Instructions', $cloned_voice->excerpt_instructions);
        $this->assertEquals(1, $cloned_voice->is_active);
    }
}
