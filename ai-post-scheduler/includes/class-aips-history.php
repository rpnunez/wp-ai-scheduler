<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_History {
    
    private $table_name;
    
    /**
     * @var AIPS_History_Repository Repository for database operations
     */
    private $repository;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aips_history';
        $this->repository = new AIPS_History_Repository();
        
        // Hooks and AJAX handlers have been moved to AIPS_History_Controller
    }
    
    public function get_history($args = array()) {
        return $this->repository->get_history($args);
    }
    
    public function get_stats() {
        return $this->repository->get_stats();
    }

    public function get_template_stats($template_id) {
        return $this->repository->get_template_stats($template_id);
    }

    public function get_all_template_stats() {
        return $this->repository->get_all_template_stats();
    }
    
    public function clear_history($status = '') {
        return $this->repository->delete_by_status($status);
    }
}
