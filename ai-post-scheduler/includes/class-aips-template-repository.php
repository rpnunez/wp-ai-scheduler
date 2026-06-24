<?php
/**
 * Template Repository
 *
 * Database abstraction layer for template operations.
 * Provides a clean interface for CRUD operations on the templates table.
 *
 * @package AI_Post_Scheduler
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!trait_exists('AIPS_Cacheable_Repository')) {
    require_once __DIR__ . '/trait-aips-cacheable-repository.php';
}

/**
 * Class AIPS_Template_Repository
 *
 * Repository pattern implementation for template data access.
 * Encapsulates all database operations related to templates.
 */
class AIPS_Template_Repository {
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
     * @var string The templates table name (with prefix)
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
        $this->table_name = $wpdb->prefix . 'aips_templates';
    }

    /**
     * Get all templates with optional filtering.
     *
     * Results are cached with a long-tier persistent cache and invalidated
     * whenever a template is created, updated, or deleted.
     *
     * @param bool $active_only Optional. Return only active templates. Default false.
     * @return array Array of template objects.
     */
    public function get_all($active_only = false) {
        return $this->cache_read(
            'templates.get_all',
            array( 'active_only' => (bool) $active_only ),
            function() use ( $active_only ) {
                $where  = $active_only ? "WHERE is_active = 1" : "";
                return $this->wpdb->get_results( "SELECT * FROM {$this->table_name} $where ORDER BY name ASC" );
            }
        );
    }

    /**
     * Get a single template by ID.
     *
     * Non-null results are cached with a long-tier persistent cache.
     * Null results (record not found) are never cached.
     *
     * @param int $id Template ID.
     * @return object|null Template object or null if not found.
     */
    public function get_by_id($id) {
        return $this->cache_read(
            'templates.get_by_id',
            array( 'template_id' => absint( $id ) ),
            function() use ( $id ) {
                return $this->wpdb->get_row( $this->wpdb->prepare(
                    "SELECT * FROM {$this->table_name} WHERE id = %d",
                    $id
                ) );
            }
        );
    }

    /**
     * Search templates by name.
     *
     * @param string $search_term Search term.
     * @return array Array of matching template objects.
     */
    public function search($search_term) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE name LIKE %s ORDER BY name ASC",
            '%' . $this->wpdb->esc_like($search_term) . '%'
        ));
    }

    /**
     * Normalise a raw post_category value to a JSON-encoded array string
     * suitable for DB storage.
     *
     * Accepts:
     *   - array of ints  → JSON-encodes after filtering
     *   - int > 0        → single-element JSON array
     *   - numeric string → treated as int
     *   - null / 0 / ''  → stores as null (no category)
     *   - JSON string    → decoded, filtered, re-encoded
     *
     * @param mixed $value Raw post_category value.
     * @return string|null JSON array string or null.
     */
    private function sanitise_post_categories( $value ) {
        $ids = AIPS_Template_Data::parse_post_categories( $value );
        return empty( $ids ) ? null : wp_json_encode( $ids );
    }

    /**
     * Create a new template.
     *
     * @param array $data {
     *     Template data.
     *
     *     @type string       $name                    Template name.
     *     @type string       $prompt_template         AI prompt template.
     *     @type string       $title_prompt            Title generation prompt.
     *     @type int          $voice_id                Voice ID.
     *     @type int          $post_quantity           Number of posts to generate.
     *     @type string       $image_prompt            Image generation prompt.
     *     @type int          $generate_featured_image Generate featured image flag.
     *     @type string       $featured_image_source   Source of featured image (ai_prompt|unsplash|media_library).
     *     @type string       $featured_image_unsplash_keywords Keywords for Unsplash image search.
     *     @type string       $featured_image_media_ids Comma-separated list of media library attachment IDs.
     *     @type string       $post_status             Post status (draft, publish, etc.).
     *     @type int|array    $post_category           Category ID or array of category IDs.
     *     @type string       $post_tags               Comma-separated tags.
     *     @type int          $post_author             Post author ID.
     *     @type int          $is_active               Active status flag.
     * }
     * @return int|false The inserted ID on success, false on failure.
     */
    public function create($data) {
        $now = AIPS_DateTime::now()->timestamp();

        $allowed_sources = array('ai_prompt', 'unsplash', 'media_library');
        $source = isset($data['featured_image_source']) ? sanitize_text_field($data['featured_image_source']) : 'ai_prompt';

        $insert_data = array(
            'name' => sanitize_text_field($data['name']),
            'prompt_template' => wp_kses_post($data['prompt_template']),
            'title_prompt' => isset($data['title_prompt']) ? sanitize_text_field($data['title_prompt']) : '',
            'voice_id' => isset($data['voice_id']) ? absint($data['voice_id']) : null,
            'post_quantity' => isset($data['post_quantity']) ? absint($data['post_quantity']) : 1,
            'image_prompt' => isset($data['image_prompt']) ? wp_kses_post($data['image_prompt']) : '',
            'generate_featured_image' => filter_var(isset($data['generate_featured_image']) ? $data['generate_featured_image'] : 0, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
            'featured_image_source' => in_array($source, $allowed_sources, true) ? $source : 'ai_prompt',
            'featured_image_unsplash_keywords' => isset($data['featured_image_unsplash_keywords']) ? sanitize_textarea_field($data['featured_image_unsplash_keywords']) : '',
            'featured_image_media_ids' => isset($data['featured_image_media_ids']) ? sanitize_text_field($data['featured_image_media_ids']) : '',
            'post_status' => sanitize_text_field($data['post_status']),
            'post_type' => isset($data['post_type']) ? sanitize_key($data['post_type']) : 'post',
            'post_category' => $this->sanitise_post_categories( isset( $data['post_category'] ) ? $data['post_category'] : null ),
            'post_tags' => isset($data['post_tags']) ? sanitize_text_field($data['post_tags']) : '',
            'post_author' => isset($data['post_author']) ? absint($data['post_author']) : get_current_user_id(),
            'include_sources' => isset($data['include_sources']) ? (int) $data['include_sources'] : 0,
            'source_group_ids' => isset($data['source_group_ids']) ? sanitize_text_field($data['source_group_ids']) : wp_json_encode(array()),
            'campaign_id' => !empty($data['campaign_id']) ? absint($data['campaign_id']) : null,
            'is_active' => isset($data['is_active']) ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        );

        $format = array('%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%d', '%d');

        $result = $this->wpdb->insert($this->table_name, $insert_data, $format);

        if ( $result ) {
            $this->invalidate_cache_domain( 'template', array(), 'template_created' );
        }

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Update an existing template.
     *
     * @param int   $id   Template ID.
     * @param array $data Data to update (same structure as create).
     * @return bool True on success, false on failure.
     */
    public function update($id, $data) {
        $update_data = array();
        $format = array();
        $allowed_sources = array('ai_prompt', 'unsplash', 'media_library');

        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $format[] = '%s';
        }

        if (isset($data['prompt_template'])) {
            $update_data['prompt_template'] = wp_kses_post($data['prompt_template']);
            $format[] = '%s';
        }

        if (isset($data['title_prompt'])) {
            $update_data['title_prompt'] = sanitize_text_field($data['title_prompt']);
            $format[] = '%s';
        }

        if (isset($data['voice_id'])) {
            $update_data['voice_id'] = $data['voice_id'] ? absint($data['voice_id']) : null;
            $format[] = '%d';
        }

        if (isset($data['post_quantity'])) {
            $update_data['post_quantity'] = absint($data['post_quantity']);
            $format[] = '%d';
        }

        if (isset($data['image_prompt'])) {
            $update_data['image_prompt'] = wp_kses_post($data['image_prompt']);
            $format[] = '%s';
        }

        if (isset($data['generate_featured_image'])) {
            $update_data['generate_featured_image'] = $data['generate_featured_image'] ? 1 : 0;
            $format[] = '%d';
        }

        if (isset($data['featured_image_source'])) {
            $source = sanitize_text_field($data['featured_image_source']);
            $update_data['featured_image_source'] = in_array($source, $allowed_sources, true) ? $source : 'ai_prompt';
            $format[] = '%s';
        }

        if (isset($data['featured_image_unsplash_keywords'])) {
            $update_data['featured_image_unsplash_keywords'] = sanitize_textarea_field($data['featured_image_unsplash_keywords']);
            $format[] = '%s';
        }

        if (isset($data['featured_image_media_ids'])) {
            $update_data['featured_image_media_ids'] = sanitize_text_field($data['featured_image_media_ids']);
            $format[] = '%s';
        }

        if (isset($data['post_status'])) {
            $update_data['post_status'] = sanitize_text_field($data['post_status']);
            $format[] = '%s';
        }

        if (isset($data['post_type'])) {
            $update_data['post_type'] = sanitize_key($data['post_type']);
            $format[] = '%s';
        }

        if (isset($data['post_category'])) {
            $update_data['post_category'] = $this->sanitise_post_categories( $data['post_category'] );
            $format[] = '%s';
        }

        if (isset($data['post_tags'])) {
            $update_data['post_tags'] = sanitize_text_field($data['post_tags']);
            $format[] = '%s';
        }

        if (isset($data['post_author'])) {
            $update_data['post_author'] = absint($data['post_author']);
            $format[] = '%d';
        }

        if (isset($data['include_sources'])) {
            $update_data['include_sources'] = (int) $data['include_sources'];
            $format[] = '%d';
        }

        if (isset($data['source_group_ids'])) {
            $update_data['source_group_ids'] = sanitize_text_field($data['source_group_ids']);
            $format[] = '%s';
        }

        if (array_key_exists('campaign_id', $data)) {
            $update_data['campaign_id'] = !empty($data['campaign_id']) ? absint($data['campaign_id']) : null;
            $format[] = '%d';
        }

        if (isset($data['is_active'])) {
            $update_data['is_active'] = $data['is_active'] ? 1 : 0;
            $format[] = '%d';
        }

        if (empty($update_data)) {
            return false;
        }

        $update_data['updated_at'] = AIPS_DateTime::now()->timestamp();
        $format[] = '%d';

        $result = $this->wpdb->update(
            $this->table_name,
            $update_data,
            array('id' => $id),
            $format,
            array('%d')
        ) !== false;

        if ( $result ) {
            $this->invalidate_cache_domain( 'template', array( 'template_id' => absint( $id ) ), 'template_updated' );
        }

        return $result;
    }

    /**
     * Delete a template by ID.
     *
     * @param int $id Template ID.
     * @return bool True on success, false on failure.
     */
    public function delete($id) {
        $result = $this->wpdb->delete($this->table_name, array('id' => $id), array('%d')) !== false;
        if ( $result ) {
            $this->invalidate_cache_domain( 'template', array( 'template_id' => absint( $id ) ), 'template_deleted' );
        }
        return $result;
    }

    /**
     * Count templates owned by a campaign.
     *
     * @param int $campaign_id Campaign ID.
     * @return int
     */
    public function count_by_campaign($campaign_id) {
        return (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE campaign_id = %d",
            absint($campaign_id)
        ));
    }

    /**
     * Toggle template active status.
     *
     * @param int  $id        Template ID.
     * @param bool $is_active Active status.
     * @return bool True on success, false on failure.
     */
    public function set_active($id, $is_active) {
        return $this->update($id, array('is_active' => $is_active));
    }

    /**
     * Count templates by status.
     *
     * @return array {
     *     @type int $total  Total number of templates.
     *     @type int $active Number of active templates.
     * }
     */
    public function count_by_status() {
        $results = $this->wpdb->get_row("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active
            FROM {$this->table_name}
        ");

        return array(
            'total' => isset($results->total) ? (int) $results->total : 0,
            'active' => isset($results->active) ? (int) $results->active : 0,
        );
    }

    /**
     * Check if a template name already exists.
     *
     * @param string $name         Template name.
     * @param int    $exclude_id   Optional. Exclude this ID from check. Default 0.
     * @return bool True if name exists, false otherwise.
     */
    public function name_exists($name, $exclude_id = 0) {
        if ($exclude_id > 0) {
            $result = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE name = %s AND id != %d",
                $name,
                $exclude_id
            ));
        } else {
            $result = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE name = %s",
                $name
            ));
        }

        return $result > 0;
    }

    /**
     * Return the repository cache group for template reads.
     *
     * @return string
     */
    protected function repository_cache_group(): string {
        return 'aips_templates';
    }

    /**
     * Return the explicit repository cache policies for template reads.
     *
     * @return array
     */
    protected function repository_cache_policies(): array {
        return array(
            'templates.get_all'   => array(
                'tier'        => 'long',
                'tags'        => array( 'templates' ),
                'description' => 'Cache template list reads including active-only filtering.',
            ),
            'templates.get_by_id' => array(
                'tier'        => 'long',
                'tags'        => array( 'templates', 'template:{template_id}' ),
                'cache_null'  => false,
                'description' => 'Cache single-template reads by ID.',
            ),
        );
    }
}
