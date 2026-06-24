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

if (!trait_exists('AIPS_Cacheable_Repository')) {
    require_once __DIR__ . '/trait-aips-cacheable-repository.php';
}

/**
 * Class AIPS_Voices_Repository
 *
 * Repository pattern implementation for voice data access.
 * Encapsulates all database operations related to voices.
 */
class AIPS_Voices_Repository {
    use AIPS_Cacheable_Repository;

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
     * Results are cached with a long-tier persistent cache and invalidated
     * whenever a voice is created, updated, or deleted.
     *
     * @param bool $active_only Whether to return only active voices.
     * @return array Array of voice objects.
     */
    public function get_all($active_only = false) {
        return $this->cache_read(
            'voices.get_all',
            array( 'active_only' => (bool) $active_only ),
            function() use ( $active_only ) {
                $where  = $active_only ? "WHERE is_active = 1" : "";
                return $this->wpdb->get_results( "SELECT * FROM {$this->table_name} $where ORDER BY name ASC" );
            }
        );
    }

    /**
     * Get a single voice by ID.
     *
     * Non-null results are cached with a long-tier persistent cache.
     * Null results (record not found) are never cached.
     *
     * @param int $id Voice ID.
     * @return object|null Voice object or null if not found.
     */
    public function get_by_id($id) {
        return $this->cache_read(
            'voices.get_by_id',
            array( 'voice_id' => absint( $id ) ),
            function() use ( $id ) {
                return $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $id ) );
            }
        );
    }

    /**
     * Create a new voice.
     *
     * @param array $data Voice data.
     * @return int|false Inserted ID or false on failure.
     */
    public function create($data) {
        $now = AIPS_DateTime::now()->timestamp();

        $insert_data = array(
            'name' => isset($data['name']) ? sanitize_text_field($data['name']) : '',
            'title_prompt' => isset($data['title_prompt']) ? wp_kses_post($data['title_prompt']) : '',
            'content_instructions' => isset($data['content_instructions']) ? wp_kses_post($data['content_instructions']) : '',
            'excerpt_instructions' => isset($data['excerpt_instructions']) ? wp_kses_post($data['excerpt_instructions']) : '',
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'created_at' => $now,
        );

        $format = array('%s', '%s', '%s', '%s', '%d', '%d');

        $result = $this->wpdb->insert($this->table_name, $insert_data, $format);

        if ( $result ) {
            $this->invalidate_cache_domain( 'voice', array(), 'voice_created' );
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
            $this->invalidate_cache_domain( 'voice', array( 'voice_id' => absint( $id ) ), 'voice_updated' );
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
            $this->invalidate_cache_domain( 'voice', array( 'voice_id' => absint( $id ) ), 'voice_deleted' );
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

    /**
     * Return the repository cache group for voice reads.
     *
     * @return string
     */
    protected function repository_cache_group(): string {
        return 'aips_voices';
    }

    /**
     * Return the explicit repository cache policies for voice reads.
     *
     * @return array
     */
    protected function repository_cache_policies(): array {
        return array(
            'voices.get_all'   => array(
                'tier'        => 'long',
                'tags'        => array( 'voices' ),
                'description' => 'Cache voice list reads including active-only filtering.',
            ),
            'voices.get_by_id' => array(
                'tier'        => 'long',
                'tags'        => array( 'voices', 'voice:{voice_id}' ),
                'cache_null'  => false,
                'description' => 'Cache single voice reads by ID.',
            ),
        );
    }
}
