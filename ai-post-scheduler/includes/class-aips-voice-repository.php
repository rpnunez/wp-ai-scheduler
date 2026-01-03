<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Voice_Repository {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aips_voices';
    }

    public function get_all($active_only = false) {
        global $wpdb;

        $where = $active_only ? "WHERE is_active = 1" : "";
        return $wpdb->get_results("SELECT * FROM {$this->table_name} $where ORDER BY name ASC");
    }

    public function get($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id));
    }

    public function search($term, $limit = 20) {
        global $wpdb;

        $where = $term ? $wpdb->prepare("WHERE is_active = 1 AND name LIKE %s", '%' . $wpdb->esc_like($term) . '%') : "WHERE is_active = 1";

        return $wpdb->get_results("SELECT id, name FROM {$this->table_name} $where ORDER BY name ASC LIMIT " . absint($limit));
    }

    public function save($data) {
        global $wpdb;

        $voice_data = array(
            'name' => sanitize_text_field($data['name']),
            'title_prompt' => wp_kses_post($data['title_prompt']),
            'content_instructions' => wp_kses_post($data['content_instructions']),
            'excerpt_instructions' => isset($data['excerpt_instructions']) ? wp_kses_post($data['excerpt_instructions']) : '',
            'is_active' => isset($data['is_active']) ? 1 : 0,
        );

        if (!empty($data['id'])) {
            $wpdb->update(
                $this->table_name,
                $voice_data,
                array('id' => absint($data['id'])),
                array('%s', '%s', '%s', '%s', '%d'),
                array('%d')
            );
            return absint($data['id']);
        } else {
            $wpdb->insert(
                $this->table_name,
                $voice_data,
                array('%s', '%s', '%s', '%s', '%d')
            );
            return $wpdb->insert_id;
        }
    }

    public function delete($id) {
        global $wpdb;
        return $wpdb->delete($this->table_name, array('id' => $id), array('%d'));
    }
}
