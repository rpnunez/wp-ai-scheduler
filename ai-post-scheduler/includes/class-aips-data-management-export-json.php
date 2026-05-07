<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * JSON export implementation (placeholder for future)
 */
class AIPS_Data_Management_Export_JSON extends AIPS_Data_Management_Export {

	/**
	 * @var AIPS_Data_Management_Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Data_Management_Repository|null $repository Repository override for tests.
	 */
	public function __construct($repository = null) {
		if ($repository) {
			$this->repository = $repository;
			return;
		}

		$repository_class = 'AIPS_Data_Management_Repository';
		$this->repository = new $repository_class();
	}
	
	/**
	 * Get the export format name
	 * 
	 * @return string
	 */
	public function get_format_name() {
		return 'JSON';
	}
	
	/**
	 * Get the file extension for this format
	 * 
	 * @return string
	 */
	public function get_file_extension() {
		return 'json';
	}
	
	/**
	 * Get the MIME type for this format
	 * 
	 * @return string
	 */
	public function get_mime_type() {
		return 'application/json';
	}
	
	/**
	 * Export the data as JSON
	 * 
	 * @return string The exported JSON data
	 */
	public function export() {
		$data = array(
			'version' => AIPS_VERSION,
			'exported_at' => gmdate('Y-m-d H:i:s'),
			'tables' => array(),
		);
		
		$tables = $this->repository->get_existing_tables($this->get_tables());

		foreach ($tables as $table_name => $full_table_name) {
			$rows = $this->repository->get_table_rows($full_table_name);
			$data['tables'][$table_name] = $rows;
		}
		
		return wp_json_encode($data, JSON_PRETTY_PRINT);
	}
	
	/**
	 * Perform the export and send to browser
	 */
	public function do_export() {
		$data = $this->export();
		$filename = $this->generate_filename();
		$this->send_download($data, $filename);
	}
}
