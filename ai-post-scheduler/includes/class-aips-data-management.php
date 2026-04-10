<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Data Management Controller
 * 
 * Handles export and import of plugin data
 */
class AIPS_Data_Management {
	
	/**
	 * Available export formats
	 * 
	 * @var array
	 */
	private $export_formats = array();
	
	/**
	 * Available import formats
	 * 
	 * @var array
	 */
	private $import_formats = array();
	
	/**
	 * Constructor
	 */
	public function __construct() {
		// Register export formats
		$this->export_formats = array(
			'mysql' => new AIPS_Data_Management_Export_MySQL(),
			'json' => new AIPS_Data_Management_Export_JSON(),
		);
		
		// Register import formats
		$this->import_formats = array(
			'mysql' => new AIPS_Data_Management_Import_MySQL(),
			'json' => new AIPS_Data_Management_Import_JSON(),
		);
		
		// Register AJAX handlers
		add_action('wp_ajax_aips_export_data', array($this, 'ajax_export_data'));
		add_action('wp_ajax_aips_import_data', array($this, 'ajax_import_data'));
	}
	
	/**
	 * Get available export formats
	 * 
	 * @return array
	 */
	public function get_export_formats() {
		$formats = array();
		foreach ($this->export_formats as $key => $exporter) {
			$formats[$key] = $exporter->get_format_name();
		}
		return $formats;
	}
	
	/**
	 * Get available import formats
	 * 
	 * @return array
	 */
	public function get_import_formats() {
		$formats = array();
		foreach ($this->import_formats as $key => $importer) {
			$formats[$key] = $importer->get_format_name();
		}
		return $formats;
	}
	
	/**
	 * Handle export AJAX request
	 */
	public function ajax_export_data() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::error(__('Unauthorized', 'ai-post-scheduler'));
			return;
		}
		
		$format = isset($_POST['format']) ? sanitize_text_field(wp_unslash($_POST['format'])) : 'mysql';
		
		if (!isset($this->export_formats[$format])) {
			AIPS_Ajax_Response::error(__('Invalid export format', 'ai-post-scheduler'));
			return;
		}
		
		try {
			$exporter = $this->export_formats[$format];
			$exporter->do_export();
			// Script will exit after sending download
		} catch (Exception $e) {
			error_log('AIPS Export Error: ' . $e->getMessage());
			AIPS_Ajax_Response::error(__('An error occurred during export. Please check server logs.', 'ai-post-scheduler'));
		}
	}
	
	/**
	 * Handle import AJAX request
	 */
	public function ajax_import_data() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::error(__('Unauthorized', 'ai-post-scheduler'));
			return;
		}
		
		$format = isset($_POST['format']) ? sanitize_text_field(wp_unslash($_POST['format'])) : 'mysql';
		
		if (!isset($this->import_formats[$format])) {
			AIPS_Ajax_Response::error(__('Invalid import format', 'ai-post-scheduler'));
			return;
		}
		
		// Check if file was uploaded
		if (!isset($_FILES['import_file'])) {
			AIPS_Ajax_Response::error(__('No file uploaded', 'ai-post-scheduler'));
			return;
		}
		
		$file = $_FILES['import_file'];
		$importer = $this->import_formats[$format];
		
		// Validate file
		$validation = $importer->validate_file($file);
		if (is_wp_error($validation)) {
			AIPS_Ajax_Response::error(array('message' => $validation->get_error_message()));
			return;
		}
		
		// Perform import
		$result = $importer->import($file['tmp_name']);
		
		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error(array('message' => $result->get_error_message()));
			return;
		}
		
		AIPS_Ajax_Response::success(array(), __('Data imported successfully', 'ai-post-scheduler'));
	}
}
