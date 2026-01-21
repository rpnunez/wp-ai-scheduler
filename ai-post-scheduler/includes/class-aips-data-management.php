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
			wp_send_json_error(array('message' => __('Unauthorized', 'ai-post-scheduler')));
			return;
		}
		
		$format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'mysql';
		
		if (!isset($this->export_formats[$format])) {
			wp_send_json_error(array('message' => __('Invalid export format', 'ai-post-scheduler')));
			return;
		}
		
		try {
			$exporter = $this->export_formats[$format];
			$exporter->do_export();
			// Script will exit after sending download
		} catch (Exception $e) {
			wp_send_json_error(array('message' => $e->getMessage()));
		}
	}
	
	/**
	 * Handle import AJAX request
	 */
	public function ajax_import_data() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Unauthorized', 'ai-post-scheduler')));
			return;
		}
		
		$format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'mysql';
		
		if (!isset($this->import_formats[$format])) {
			wp_send_json_error(array('message' => __('Invalid import format', 'ai-post-scheduler')));
			return;
		}
		
		// Check if file was uploaded
		if (!isset($_FILES['import_file'])) {
			wp_send_json_error(array('message' => __('No file uploaded', 'ai-post-scheduler')));
			return;
		}
		
		$file = $_FILES['import_file'];
		$importer = $this->import_formats[$format];
		
		// Validate file
		$validation = $importer->validate_file($file);
		if (is_wp_error($validation)) {
			wp_send_json_error(array('message' => $validation->get_error_message()));
			return;
		}
		
		// Perform import
		$result = $importer->import($file['tmp_name']);
		
		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()));
			return;
		}
		
		wp_send_json_success(array('message' => __('Data imported successfully', 'ai-post-scheduler')));
	}
}
