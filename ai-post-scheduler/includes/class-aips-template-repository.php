<?php
/**
 * Template Repository
 *
 * Handles all database operations for template records.
 * Provides query optimization and abstraction from direct $wpdb usage.
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
 * Repository for managing templates in the database.
 * Encapsulates all SQL queries and database operations for template records.
 */
class AIPS_Template_Repository extends AIPS_Base_Repository {
    
    /**
     * Initialize the template repository.
     */
    public function __construct() {
        parent::__construct('aips_templates');
    }
    
    /**
     * Get all templates.
     *
     * @param bool $active_only Optional. Whether to return only active templates.
     * @return array Array of template objects.
     */
    public function get_all($active_only = false) {
        $args = array(
            'order_by' => 'name',
            'order' => 'ASC',
        );
        
        if ($active_only) {
            $args['where'] = array('is_active' => 1);
        }
        
        return $this->find_all($args);
    }
    
    /**
     * Save a template (create or update).
     *
     * @param array $data Template data.
     * @return int|false Template ID or false on failure.
     */
    public function save($data) {
        $template_data = array(
            'name' => sanitize_text_field($data['name']),
            'prompt_template' => wp_kses_post($data['prompt_template']),
            'title_prompt' => isset($data['title_prompt']) ? sanitize_text_field($data['title_prompt']) : '',
            'voice_id' => isset($data['voice_id']) ? absint($data['voice_id']) : NULL,
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
        
        if (!empty($data['id'])) {
            $result = $this->update(absint($data['id']), $template_data, $format);
            return $result !== false ? absint($data['id']) : false;
        } else {
            return $this->insert($template_data, $format);
        }
    }
    
    /**
     * Toggle template active status.
     *
     * @param int $id        Template ID.
     * @param int $is_active Active status (0 or 1).
     * @return int|false Number of rows affected or false on error.
     */
    public function toggle_active($id, $is_active) {
        return $this->update($id, array('is_active' => $is_active), array('%d'));
    }
    
    /**
     * Get active templates count.
     *
     * @return int Number of active templates.
     */
    public function get_active_count() {
        return $this->count(array('is_active' => 1));
    }
    
    /**
     * Search templates by name.
     *
     * @param string $search Search query.
     * @return array Array of matching template objects.
     */
    public function search($search) {
        return $this->find_all(array(
            'where' => array(
                'name' => array(
                    'operator' => 'LIKE',
                    'value' => $search,
                ),
            ),
            'order_by' => 'name',
            'order' => 'ASC',
        ));
    }
}
