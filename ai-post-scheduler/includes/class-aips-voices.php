<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Voices {
    
    private $repository;
    private $controller;
    
    public function __construct($repository = null) {
        $this->repository = $repository ?: new AIPS_Voice_Repository();
        
        // Initialize the controller to handle AJAX actions
        $this->controller = new AIPS_Voice_Controller($this->repository);
    }
    
    // Delegate methods to repository for backward compatibility or direct usage

    public function get_all($active_only = false) {
        return $this->repository->get_all($active_only);
    }
    
    public function get($id) {
        return $this->repository->get($id);
    }
    
    public function save($data) {
        return $this->repository->save($data);
    }
    
    public function delete($id) {
        return $this->repository->delete($id);
    }
    
    public function render_page() {
        $voices = $this->repository->get_all();
        
        include AIPS_PLUGIN_DIR . 'templates/admin/voices.php';
    }
}
