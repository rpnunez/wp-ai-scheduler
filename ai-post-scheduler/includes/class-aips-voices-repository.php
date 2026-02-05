<?php
/**
 * Voices Repository
 *
 * Database abstraction layer for voice operations.
 * Provides a clean interface for CRUD operations on the voices table.
 *
 * @package AI_Post_Scheduler
 * @since 1.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Voices_Repository
 *
 * Repository pattern implementation for voices data access.
 * Encapsulates all database operations related to voices.
 */
class AIPS_Voices_Repository {

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
        return $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id));
    }

    /**
     * Create or update a voice.
     *
     * @param array $data Voice data.
     * @return int|false The inserted/updated ID on success, false on failure.
     */
    public function save($data) {
        $voice_data = array(
            'name' => sanitize_text_field($data['name']),
            'title_prompt' => wp_kses_post($data['title_prompt']),
            'content_instructions' => wp_kses_post($data['content_instructions']),
            'excerpt_instructions' => isset($data['excerpt_instructions']) ? wp_kses_post($data['excerpt_instructions']) : '',
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        );

        if (!empty($data['id'])) {
            $this->wpdb->update(
                $this->table_name,
                $voice_data,
                array('id' => absint($data['id'])),
                array('%s', '%s', '%s', '%s', '%d'),
                array('%d')
            );
            return absint($data['id']);
        } else {
            $this->wpdb->insert(
                $this->table_name,
                $voice_data,
                array('%s', '%s', '%s', '%s', '%d')
            );
            return $this->wpdb->insert_id;
        }
    }

    /**
     * Delete a voice by ID.
     *
     * @param int $id Voice ID.
     * @return bool True on success, false on failure.
     */
    public function delete($id) {
        return $this->wpdb->delete($this->table_name, array('id' => $id), array('%d'));
    }

    /**
     * Search voices.
     *
     * @param string $search Search term.
     * @param int $limit Optional. Limit results. Default 20.
     * @return array Array of voice objects (id, name).
     */
    public function search($search, $limit = 20) {
        $where = $search ? $this->wpdb->prepare("WHERE is_active = 1 AND name LIKE %s", '%' . $this->wpdb->esc_like($search) . '%') : "WHERE is_active = 1";

        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, name FROM {$this->table_name} $where ORDER BY name ASC LIMIT %d",
            $limit
        ));
    }
}
