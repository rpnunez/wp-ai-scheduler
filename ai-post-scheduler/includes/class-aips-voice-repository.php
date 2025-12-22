<?php
/**
 * Voice Repository
 *
 * Handles all database operations for voice records.
 * Provides query optimization and abstraction from direct $wpdb usage.
 *
 * @package AI_Post_Scheduler
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Voice_Repository
 *
 * Repository for managing voices in the database.
 * Encapsulates all SQL queries and database operations for voice records.
 */
class AIPS_Voice_Repository extends AIPS_Base_Repository {
    
    /**
     * Initialize the voice repository.
     */
    public function __construct() {
        parent::__construct('aips_voices');
    }
    
    /**
     * Get all voices.
     *
     * @param bool $active_only Optional. Whether to return only active voices.
     * @return array Array of voice objects.
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
     * Save a voice (create or update).
     *
     * @param array $data Voice data.
     * @return int|false Voice ID or false on failure.
     */
    public function save($data) {
        $voice_data = array(
            'name' => sanitize_text_field($data['name']),
            'title_prompt' => wp_kses_post($data['title_prompt']),
            'content_instructions' => wp_kses_post($data['content_instructions']),
            'excerpt_instructions' => isset($data['excerpt_instructions']) ? wp_kses_post($data['excerpt_instructions']) : '',
            'is_active' => isset($data['is_active']) ? 1 : 0,
        );
        
        $format = array('%s', '%s', '%s', '%s', '%d');
        
        if (!empty($data['id'])) {
            $result = $this->update(absint($data['id']), $voice_data, $format);
            return $result !== false ? absint($data['id']) : false;
        } else {
            return $this->insert($voice_data, $format);
        }
    }
    
    /**
     * Toggle voice active status.
     *
     * @param int $id        Voice ID.
     * @param int $is_active Active status (0 or 1).
     * @return int|false Number of rows affected or false on error.
     */
    public function toggle_active($id, $is_active) {
        return $this->update($id, array('is_active' => $is_active), array('%d'));
    }
    
    /**
     * Get active voices count.
     *
     * @return int Number of active voices.
     */
    public function get_active_count() {
        return $this->count(array('is_active' => 1));
    }
    
    /**
     * Search voices by name.
     *
     * @param string $search Search query.
     * @return array Array of matching voice objects.
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
