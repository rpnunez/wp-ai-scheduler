<?php
/**
 * Import & Export Controller
 *
 * Handles AJAX requests for starter presets management (install/remove/status),
 * granular data exports, and smart JSON imports with conflict resolution.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Import_Export_Controller {

	/**
	 * @var AIPS_Preset_Installer
	 */
	private $installer;

	/**
	 * @var AIPS_Selective_Exporter
	 */
	private $exporter;

	/**
	 * @var AIPS_Selective_Importer
	 */
	private $importer;

	/**
	 * Constructor.
	 *
	 * Registers AJAX actions.
	 */
	public function __construct(
		?AIPS_Preset_Installer $installer = null,
		?AIPS_Selective_Exporter $exporter = null,
		?AIPS_Selective_Importer $importer = null
	) {
		$this->installer = $installer ?: new AIPS_Preset_Installer();
		$this->exporter  = $exporter ?: new AIPS_Selective_Exporter();
		$this->importer  = $importer ?: new AIPS_Selective_Importer();

		add_action('wp_ajax_aips_get_presets_status', array($this, 'ajax_get_presets_status'));
		add_action('wp_ajax_aips_install_preset', array($this, 'ajax_install_preset'));
		add_action('wp_ajax_aips_remove_preset', array($this, 'ajax_remove_preset'));
		add_action('wp_ajax_aips_export_data', array($this, 'ajax_export_data'));
		add_action('wp_ajax_aips_analyze_import_file', array($this, 'ajax_analyze_import_file'));
		add_action('wp_ajax_aips_execute_import', array($this, 'ajax_execute_import'));
	}

	/**
	 * Retrieve all starter presets with live installation and usage metrics.
	 */
	public function ajax_get_presets_status(): void {
		if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$presets_raw = AIPS_Preset_Registry::get_presets();
		$installed   = $this->installer->get_installed_presets();

		$output = array();
		foreach ($presets_raw as $id => $p) {
			$is_inst = in_array($id, $installed, true);
			$usage   = $is_inst ? $this->installer->get_usage_stats($id) : array('post_count' => 0, 'schedules_run' => 0);

			$output[] = array(
				'id'            => $id,
				'name'          => $p['name'],
				'tagline'       => $p['tagline'],
				'description'   => $p['description'],
				'icon'          => $p['icon'],
				'is_installed'  => $is_inst,
				'post_count'    => $usage['post_count'],
				'schedules_run' => $usage['schedules_run'],
				'author_name'   => $p['author']['name'],
				'template_name' => $p['template']['name'],
				'voice_name'    => $p['voice']['name'],
				'structure_name'=> $p['structure']['name'],
			);
		}

		AIPS_Ajax_Response::success(array('presets' => $output));
	}

	/**
	 * Install a preset blueprint.
	 */
	public function ajax_install_preset(): void {
		if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$preset_id    = isset($_POST['preset_id']) ? sanitize_key($_POST['preset_id']) : '';
		$set_strategy = isset($_POST['set_strategy']) ? filter_var($_POST['set_strategy'], FILTER_VALIDATE_BOOLEAN) : true;

		if (empty($preset_id)) {
			AIPS_Ajax_Response::error(__('Missing preset ID.', 'ai-post-scheduler'));
		}

		$result = $this->installer->install($preset_id, $set_strategy);
		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message());
		}

		$preset = AIPS_Preset_Registry::get_preset($preset_id);
		$name   = $preset ? $preset['name'] : $preset_id;

		AIPS_Ajax_Response::success(array(
			'message'  => sprintf(__('Preset "%s" installed successfully.', 'ai-post-scheduler'), $name),
			'entities' => $result,
		));
	}

	/**
	 * Remove an installed preset with safety verification.
	 */
	public function ajax_remove_preset(): void {
		if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$preset_id = isset($_POST['preset_id']) ? sanitize_key($_POST['preset_id']) : '';
		$force     = isset($_POST['force']) ? filter_var($_POST['force'], FILTER_VALIDATE_BOOLEAN) : false;

		if (empty($preset_id)) {
			AIPS_Ajax_Response::error(__('Missing preset ID.', 'ai-post-scheduler'));
		}

		$result = $this->installer->remove($preset_id, $force);
		if (is_wp_error($result)) {
			$data = $result->get_error_data();
			AIPS_Ajax_Response::error(
				$result->get_error_message(),
				$result->get_error_code() ?: 'error',
				200,
				is_array($data) ? $data : array()
			);
		}

		$preset = AIPS_Preset_Registry::get_preset($preset_id);
		$name   = $preset ? $preset['name'] : $preset_id;

		AIPS_Ajax_Response::success(array(
			'message' => sprintf(__('Preset "%s" uninstalled successfully.', 'ai-post-scheduler'), $name),
		));
	}

	/**
	 * Generate and return an export package for selected types.
	 */
	public function ajax_export_data(): void {
		if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$types_input = isset($_POST['types']) ? (array) $_POST['types'] : array();
		$types       = array_map('sanitize_key', $types_input);

		if (empty($types)) {
			AIPS_Ajax_Response::error(__('Please select at least one item type to export.', 'ai-post-scheduler'));
		}

		$package  = $this->exporter->export_data($types);
		$json_str = (string) wp_json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		$filename = 'aips-export-' . gmdate('Y-m-d_H-i-s') . '.json';

		AIPS_Ajax_Response::success(array(
			'filename' => $filename,
			'json'     => $json_str,
			'package'  => $package,
		));
	}

	/**
	 * Analyze an uploaded export JSON file before importing.
	 */
	public function ajax_analyze_import_file(): void {
		if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		if (empty($_FILES['import_file']) || empty($_FILES['import_file']['tmp_name'])) {
			AIPS_Ajax_Response::error(__('No file uploaded.', 'ai-post-scheduler'));
		}

		$file = $_FILES['import_file'];
		if (!empty($file['error'])) {
			AIPS_Ajax_Response::error(__('File upload error occurred.', 'ai-post-scheduler'));
		}

		$parsed = $this->importer->parse_file($file['tmp_name']);
		if (is_wp_error($parsed)) {
			AIPS_Ajax_Response::error($parsed->get_error_message());
		}

		// Store parsed payload in a temporary user transient for 1 hour to allow review & selective execution
		// Lowercase so the key survives sanitize_key() in ajax_execute_import().
		$transient_key = 'aips_import_' . get_current_user_id() . '_' . strtolower(wp_generate_password(8, false));
		set_transient($transient_key, $parsed['entities'], HOUR_IN_SECONDS);

		AIPS_Ajax_Response::success(array(
			'import_key' => $transient_key,
			'counts'     => $parsed['counts'],
		));
	}

	/**
	 * Execute selective import.
	 */
	public function ajax_execute_import(): void {
		if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$import_key      = isset($_POST['import_key']) ? sanitize_key($_POST['import_key']) : '';
		$conflict_policy = isset($_POST['conflict_policy']) ? sanitize_key($_POST['conflict_policy']) : 'copy';
		$types_input     = isset($_POST['types']) ? (array) $_POST['types'] : array();
		$types           = array_map('sanitize_key', $types_input);

		if (empty($import_key)) {
			AIPS_Ajax_Response::error(__('Missing import session key.', 'ai-post-scheduler'));
		}

		$entities = get_transient($import_key);
		if (false === $entities || !is_array($entities)) {
			AIPS_Ajax_Response::error(__('Import session expired or invalid. Please re-upload your file.', 'ai-post-scheduler'));
		}

		if (empty($types)) {
			AIPS_Ajax_Response::error(__('Please select at least one entity type to import.', 'ai-post-scheduler'));
		}

		$report = $this->importer->import($entities, $types, $conflict_policy);
		delete_transient($import_key);

		AIPS_Ajax_Response::success(array(
			'report'  => $report,
			'message' => __('Import process completed.', 'ai-post-scheduler'),
		));
	}
}
