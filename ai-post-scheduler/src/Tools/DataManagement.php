<?php
namespace AIPS\Tools;

use AIPS\Helpers\DBHelper;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Data Management Controller
 *
 * Handles export/import plus repair & maintenance actions.
 */
class DataManagement {

	/**
	 * @var IDataManagementExport[]
	 */
	private $export_formats = array();

	/**
	 * @var IDataManagementImport[]
	 */
	private $import_formats = array();

	public function __construct() {
		$this->export_formats = array(
			'mysql' => new DataManagementExportMySQL(),
			'json' => new DataManagementExportJSON(),
		);

		$this->import_formats = array(
			'mysql' => new DataManagementImportMySQL(),
			'json' => new DataManagementImportJSON(),
		);

		add_action('wp_ajax_aips_export_data', array($this, 'ajax_export_data'));
		add_action('wp_ajax_aips_import_data', array($this, 'ajax_import_data'));
		add_action('wp_ajax_aips_repair_db', array($this, 'ajax_repair_db'));
		add_action('wp_ajax_aips_reinstall_db', array($this, 'ajax_reinstall_db'));
		add_action('wp_ajax_aips_wipe_db', array($this, 'ajax_wipe_db'));
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
		} catch (\Exception $e) {
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

		if (!isset($_FILES['import_file'])) {
			wp_send_json_error(array('message' => __('No file uploaded', 'ai-post-scheduler')));
			return;
		}

		$file = $_FILES['import_file'];
		$importer = $this->import_formats[$format];

		$validation = $importer->validate_file($file);
		if (is_wp_error($validation)) {
			wp_send_json_error(array('message' => $validation->get_error_message()));
			return;
		}

		$result = $importer->import($file['tmp_name']);

		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()));
			return;
		}

		wp_send_json_success(array('message' => __('Data imported successfully', 'ai-post-scheduler')));
	}

	public function ajax_repair_db() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => 'Unauthorized'));
		}

		DBHelper::install_tables();
		wp_send_json_success(array('message' => 'Database tables repaired successfully.'));
	}

	public function ajax_reinstall_db() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => 'Unauthorized'));
		}

		$backup = isset($_POST['backup']) && $_POST['backup'] === 'true';
		$data = null;

		$db_helper = new DBHelper();
		if ($backup) {
			$data = $db_helper->backup_data();
		}

		$db_helper->drop_tables();
		DBHelper::install_tables();

		if ($backup && $data) {
			$db_helper->restore_data($data);
		}

		wp_send_json_success(array('message' => 'Database tables reinstalled successfully.'));
	}

	public function ajax_wipe_db() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => 'Unauthorized'));
		}

		$db_helper = new DBHelper();
		$db_helper->truncate_tables();
		wp_send_json_success(array('message' => 'Plugin data wiped successfully.'));
	}
}
