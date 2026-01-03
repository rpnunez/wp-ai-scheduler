<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Voices {
    
    /**
     * @var AIPS_Voice_Repository
     */
    private $repository;

    /**
     * @var AIPS_Voice_Controller
     */
    private $controller;
    
    public function __construct() {
        $this->repository = new AIPS_Voice_Repository();
        $this->controller = new AIPS_Voice_Controller();
    }
    
    /**
     * Get all voices (Proxy to Repository).
     *
     * @param bool $active_only Whether to retrieve only active voices.
     * @return array
     */
    public function get_all($active_only = false) {
        return $this->repository->get_all($active_only);
    }
    
    /**
     * Get a specific voice (Proxy to Repository).
     *
     * @param int $id The voice ID.
     * @return object|null
     */
    public function get($id) {
        return $this->repository->get($id);
    }
    
    /**
     * Save a voice (Proxy to Repository).
     *
     * @param array $data Voice data.
     * @return int|false
     */
    public function save($data) {
        return $this->repository->save($data);
    }
    
    /**
     * Delete a voice (Proxy to Repository).
     *
     * @param int $id The voice ID.
     * @return int|false
     */
    public function delete($id) {
        return $this->repository->delete($id);
    }
    
    /**
     * Render the admin page.
     *
     * @return void
     */
    public function render_page() {
        $voices = $this->get_all();
        
        include AIPS_PLUGIN_DIR . 'templates/admin/voices.php';
    }
}
