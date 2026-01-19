<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Voice Repository
 *
 * Database abstraction layer for voice operations.
 * Provides a clean interface for CRUD operations on the voices table.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.0
 */
class AIPS_Voice_Repository {

    /**
     * @var string The voices table name (with prefix)
     */
    private $table_name;

    /**
     * @var wpdb WordPress database abstraction object
     */
    private $wpdb;

    /**
     * Initialize the repository.
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'aips_voices';
    }

    /**
     * Get all voices.
     *
     * @param bool $active_only Optional. Return only active voices. Default false.
     * @return array Array of voice objects.
     */
    public function get_all($active_only = false) {
        $where = $active_only ? "WHERE is_active = 1" : "";
        return $this->wpdb->get_results("SELECT * FROM {$this->table_name} $where ORDER BY name ASC");
    }

    /**
     * Get a single voice by ID.
     *
     * @param int $id Voice ID.
     * @return object|null Voice object or null if not found.
     */
    public function get_by_id($id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ));
    }

    /**
     * Search voices by name.
     *
     * @param string $search      Search term.
     * @param int    $limit       Optional. Max results. Default 20.
     * @param bool   $active_only Optional. Return only active voices. Default true.
     * @return array Array of voice objects (id and name only).
     */
    public function search($search, $limit = 20, $active_only = true) {
        $where = $active_only ? "WHERE is_active = 1" : "WHERE 1=1";

        if (!empty($search)) {
            $where .= $this->wpdb->prepare(" AND name LIKE %s", '%' . $this->wpdb->esc_like($search) . '%');
        }

        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, name FROM {$this->table_name} $where ORDER BY name ASC LIMIT %d",
            $limit
        ));
    }

    /**
     * Create a new voice.
     *
     * @param array $data Voice data.
     * @return int|false The inserted ID on success, false on failure.
     */
    public function create($data) {
        $insert_data = array(
            'name' => sanitize_text_field($data['name']),
            'title_prompt' => wp_kses_post($data['title_prompt']),
            'content_instructions' => wp_kses_post($data['content_instructions']),
            'excerpt_instructions' => isset($data['excerpt_instructions']) ? wp_kses_post($data['excerpt_instructions']) : '',
            'is_active' => isset($data['is_active']) ? 1 : 0,
        );

        $result = $this->wpdb->insert(
            $this->table_name,
            $insert_data,
            array('%s', '%s', '%s', '%s', '%d')
        );

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Update an existing voice.
     *
     * @param int   $id   Voice ID.
     * @param array $data Data to update.
     * @return bool True on success, false on failure.
     */
    public function update($id, $data) {
        $update_data = array(
            'name' => sanitize_text_field($data['name']),
            'title_prompt' => wp_kses_post($data['title_prompt']),
            'content_instructions' => wp_kses_post($data['content_instructions']),
            'excerpt_instructions' => isset($data['excerpt_instructions']) ? wp_kses_post($data['excerpt_instructions']) : '',
            'is_active' => isset($data['is_active']) ? 1 : 0,
        );

        $result = $this->wpdb->update(
            $this->table_name,
            $update_data,
            array('id' => absint($id)),
            array('%s', '%s', '%s', '%s', '%d'),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Delete a voice by ID.
     *
     * @param int $id Voice ID.
     * @return bool True on success, false on failure.
     */
    public function delete($id) {
        $result = $this->wpdb->delete($this->table_name, array('id' => $id), array('%d'));
        return $result !== false;
    }

    /**
     * Set active status for a voice.
     *
     * @param int  $id        Voice ID.
     * @param bool $is_active Active status.
     * @return bool True on success, false on failure.
     */
    public function set_active($id, $is_active) {
        return $this->wpdb->update(
            $this->table_name,
            array('is_active' => $is_active ? 1 : 0),
            array('id' => $id),
            array('%d'),
            array('%d')
        ) !== false;
    }
}
