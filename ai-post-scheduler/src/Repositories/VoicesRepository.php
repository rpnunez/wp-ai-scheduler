<?php
namespace AIPS\Repositories;

/**
 * Voices Repository
 *
 * Database abstraction layer for voices operations.
 * Provides a clean interface for CRUD operations on the voices table.
 *
 * @package AI_Post_Scheduler
 * @since 1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class VoicesRepository
 *
 * Repository pattern implementation for voice data access.
 * Encapsulates all database operations related to voices.
 */
class VoicesRepository {

    /**
     * @var string Table name (with prefix)
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
     * @param bool $active_only Whether to return only active voices.
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
        return $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id));
    }

    /**
     * Create a new voice.
     *
     * @param array $data Voice data.
     * @return int|false Inserted ID or false on failure.
     */
    public function create($data) {
        $insert_data = array(
            'name' => isset($data['name']) ? sanitize_text_field($data['name']) : '',
            'title_prompt' => isset($data['title_prompt']) ? wp_kses_post($data['title_prompt']) : '',
            'content_instructions' => isset($data['content_instructions']) ? wp_kses_post($data['content_instructions']) : '',
            'excerpt_instructions' => isset($data['excerpt_instructions']) ? wp_kses_post($data['excerpt_instructions']) : '',
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        );

        $format = array('%s', '%s', '%s', '%s', '%d');

        $result = $this->wpdb->insert($this->table_name, $insert_data, $format);

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Update an existing voice.
     *
     * @param int $id Voice ID.
     * @param array $data Voice data to update.
     * @return bool True on success, false on failure.
     */
    public function update($id, $data) {
        $update_data = array();
        $format = array();

        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $format[] = '%s';
        }

        if (isset($data['title_prompt'])) {
            $update_data['title_prompt'] = wp_kses_post($data['title_prompt']);
            $format[] = '%s';
        }

        if (isset($data['content_instructions'])) {
            $update_data['content_instructions'] = wp_kses_post($data['content_instructions']);
            $format[] = '%s';
        }

        if (isset($data['excerpt_instructions'])) {
            $update_data['excerpt_instructions'] = wp_kses_post($data['excerpt_instructions']);
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
     * Delete a voice.
     *
     * @param int $id Voice ID.
     * @return bool True on success, false on failure.
     */
    public function delete($id) {
        return $this->wpdb->delete($this->table_name, array('id' => $id), array('%d'));
    }

    /**
     * Search voices by name.
     *
     * @param string $term Search term.
     * @param int $limit Max results.
     * @return array Array of voice objects (id, name).
     */
    public function search($term, $limit = 20) {
        $limit = absint($limit);

        if ($term) {
            $like = '%' . $this->wpdb->esc_like($term) . '%';
            $sql  = $this->wpdb->prepare(
                "SELECT id, name FROM {$this->table_name} WHERE is_active = 1 AND name LIKE %s ORDER BY name ASC LIMIT %d",
                $like,
                $limit
            );
        } else {
            $sql = $this->wpdb->prepare(
                "SELECT id, name FROM {$this->table_name} WHERE is_active = 1 ORDER BY name ASC LIMIT %d",
                $limit
            );
        }

        return $this->wpdb->get_results($sql);
    }
}
