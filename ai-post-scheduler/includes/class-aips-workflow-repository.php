<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Workflow Repository
 *
 * Handles persistence for AI Post Scheduler workflows.
 */
class AIPS_Workflow_Repository {

    /**
     * @var wpdb
     */
    private $wpdb;

    /**
     * @var string
     */
    private $table_name;

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'aips_workflows';
    }

    /**
     * Return all workflows ordered by creation date.
     *
     * @return array
     */
    public function get_all() {
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->table_name} ORDER BY created_at DESC"
        );
    }

    /**
     * Return a workflow by ID.
     *
     * @param int $id
     * @return object|null
     */
    public function get_by_id($id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id)
        );
    }

    /**
     * Create a workflow.
     *
     * @param array $data
     * @return int|false
     */
    public function create(array $data) {
        $insert_data = array(
            'name' => sanitize_text_field($data['name'] ?? ''),
            'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
            'status' => isset($data['status']) ? sanitize_key($data['status']) : 'generated',
            'is_active' => isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 1,
        );

        $format = array('%s', '%s', '%s', '%d');

        $result = $this->wpdb->insert($this->table_name, $insert_data, $format);

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Update an existing workflow.
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, array $data) {
        $update_data = array();
        $format = array();

        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $format[] = '%s';
        }

        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
            $format[] = '%s';
        }

        if (isset($data['status'])) {
            $update_data['status'] = sanitize_key($data['status']);
            $format[] = '%s';
        }

        if (isset($data['is_active'])) {
            $update_data['is_active'] = $data['is_active'] ? 1 : 0;
            $format[] = '%d';
        }

        if (empty($update_data)) {
            return false;
        }

        $result = $this->wpdb->update(
            $this->table_name,
            $update_data,
            array('id' => $id),
            $format,
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Delete a workflow.
     *
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        return (bool) $this->wpdb->delete($this->table_name, array('id' => $id), array('%d'));
    }
}
