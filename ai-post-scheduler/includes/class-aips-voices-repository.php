<?php
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
 * Class AIPS_Voices_Repository
 *
 * Repository pattern implementation for voice data access.
 * Encapsulates all database operations related to voices.
 */
class AIPS_Voices_Repository {

    /**
     * @var self|null Singleton instance.
     */
    private static $instance = null;

    /**
     * Get the shared singleton instance.
     *
     * @return self
     */
    public static function instance(): self {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @var string Table name (with prefix)
     */
    private $table_name;

    /**
     * @var wpdb WordPress database abstraction object
     */
    private $wpdb;

    /**
     * @var AIPS_Cache In-request identity-map cache (array driver).
     */
    private $cache = null;

    /**
     * Initialize the repository.
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'aips_voices';
        $this->cache = AIPS_Cache_Factory::named( 'aips_voices_repository', 'array' );
    }

    /**
     * Get all voices.
     *
     * Results are cached for the duration of the request so repeat calls
     * within the same request do not issue additional DB queries.
     *
     * @param bool $active_only Whether to return only active voices.
     * @return array Array of voice objects.
     */
    public function get_all($active_only = false) {
        $key = 'all:' . ( $active_only ? '1' : '0' );
        if ( $this->cache->has( $key ) ) {
            return $this->cache->get( $key );
        }
        $where  = $active_only ? "WHERE is_active = 1" : "";
        $result = $this->wpdb->get_results( "SELECT * FROM {$this->table_name} $where ORDER BY name ASC" );
        $this->cache->set( $key, $result );
        return $result;
    }

    /**
     * Get a single voice by ID.
     *
     * Non-null results are cached for the duration of the request.
     *
     * @param int $id Voice ID.
     * @return object|null Voice object or null if not found.
     */
    public function get_by_id($id) {
        $key = 'id:' . (int) $id;
        if ( $this->cache->has( $key ) ) {
            return $this->cache->get( $key );
        }
        $result = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $id ) );
        if ( $result !== null ) {
            $this->cache->set( $key, $result );
        }
        return $result;
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

        if ( $result ) {
            $this->cache->flush();
        }

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

        if ( $result !== false ) {
            $this->cache->flush();
        }

        return $result !== false;
    }

    /**
     * Delete a voice.
     *
     * @param int $id Voice ID.
     * @return bool True on success, false on failure.
     */
    public function delete($id) {
        $result = $this->wpdb->delete($this->table_name, array('id' => $id), array('%d'));
        if ( $result !== false ) {
            $this->cache->flush();
        }
        return $result !== false;
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
