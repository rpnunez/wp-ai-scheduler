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

/**
 * Class AIPS_Template_Repository
 *
 * Repository pattern implementation for template data access.
 * Encapsulates all database operations related to templates.
 */
class AIPS_Template_Repository {
    
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
     * @param bool $active_only Optional. Return only active templates. Default false.
     * @return array Array of template objects.
     */
    public function get_all($active_only = false) {
        $where = $active_only ? "WHERE is_active = 1" : "";
        return $this->wpdb->get_results("SELECT * FROM {$this->table_name} $where ORDER BY name ASC");
    }
    
    /**
     * Get a single template by ID.
     *
     * @param int $id Template ID.
     * @return object|null Template object or null if not found.
     */
    public function get_by_id($id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Get templates by voice ID.
     *
     * @param int $voice_id Voice ID.
     * @return array Array of template objects using this voice.
     */
    public function get_by_voice($voice_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE voice_id = %d ORDER BY name ASC",
            $voice_id
        ));
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
     * Create a new template.
     *
     * @param array $data {
     *     Template data.
     *
     *     @type string $name                    Template name.
     *     @type string $prompt_template         AI prompt template.
     *     @type string $title_prompt            Title generation prompt.
     *     @type int    $voice_id                Voice ID.
     *     @type int    $post_quantity           Number of posts to generate.
     *     @type string $image_prompt            Image generation prompt.
     *     @type int    $generate_featured_image Generate featured image flag.
     *     @type string $post_status             Post status (draft, publish, etc.).
     *     @type int    $post_category           Post category ID.
     *     @type string $post_tags               Comma-separated tags.
     *     @type int    $post_author             Post author ID.
     *     @type int    $is_active               Active status flag.
     * }
     * @return int|false The inserted ID on success, false on failure.
     */
    public function create($data) {
        $insert_data = array(
            'name' => sanitize_text_field($data['name']),
            'prompt_template' => wp_kses_post($data['prompt_template']),
            'title_prompt' => isset($data['title_prompt']) ? sanitize_text_field($data['title_prompt']) : '',
            'voice_id' => isset($data['voice_id']) ? absint($data['voice_id']) : null,
            'post_quantity' => isset($data['post_quantity']) ? absint($data['post_quantity']) : 1,
            'image_prompt' => isset($data['image_prompt']) ? wp_kses_post($data['image_prompt']) : '',
            'generate_featured_image' => isset($data['generate_featured_image']) ? 1 : 0,
            'post_status' => sanitize_text_field($data['post_status']),
            'post_category' => absint($data['post_category']),
            'post_tags' => isset($data['post_tags']) ? sanitize_text_field($data['post_tags']) : '',
            'post_author' => isset($data['post_author']) ? absint($data['post_author']) : get_current_user_id(),
            'is_active' => isset($data['is_active']) ? 1 : 0,
        );
        
        $format = array('%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%d', '%s', '%d', '%d');
        
        $result = $this->wpdb->insert($this->table_name, $insert_data, $format);
        
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
        
        if (isset($data['post_status'])) {
            $update_data['post_status'] = sanitize_text_field($data['post_status']);
            $format[] = '%s';
        }
        
        if (isset($data['post_category'])) {
            $update_data['post_category'] = absint($data['post_category']);
            $format[] = '%d';
        }
        
        if (isset($data['post_tags'])) {
            $update_data['post_tags'] = sanitize_text_field($data['post_tags']);
            $format[] = '%s';
        }
        
        if (isset($data['post_author'])) {
            $update_data['post_author'] = absint($data['post_author']);
            $format[] = '%d';
        }
        
        if (isset($data['is_active'])) {
            $update_data['is_active'] = $data['is_active'] ? 1 : 0;
            $format[] = '%d';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        return $this->wpdb->update(
            $this->table_name,
            $update_data,
            array('id' => $id),
            $format,
            array('%d')
        ) !== false;
    }
    
    /**
     * Delete a template by ID.
     *
     * @param int $id Template ID.
     * @return bool True on success, false on failure.
     */
    public function delete($id) {
        return $this->wpdb->delete($this->table_name, array('id' => $id), array('%d')) !== false;
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
            'total' => (int) $results->total,
            'active' => (int) $results->active,
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
}
