<?php
namespace AIPS\Tools;

use AIPS\Helpers\DBHelper;

if (!defined('ABSPATH')) {
	exit;
}

abstract class AbstractDataManagementExport implements IDataManagementExport {

	/**
	 * Get all plugin table names
	 *
	 * @return array
	 */
	protected function get_tables() {
		return DBHelper::get_full_table_names();
	}

	/**
	 * Send the export file to the browser for download
	 *
	 * @param string $data The export data
	 * @param string $filename The filename for the download
	 */
	protected function send_download($data, $filename) {
		header('Content-Type: ' . $this->get_mime_type());
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Content-Length: ' . strlen($data));
		header('Cache-Control: no-cache, must-revalidate');
		header('Pragma: no-cache');
		header('Expires: 0');

		echo $data;
		exit;
	}

	/**
	 * Generate a filename for the export
	 *
	 * @return string
	 */
	protected function generate_filename() {
		$timestamp = gmdate('Y-m-d_H-i-s');
		return 'aips-export_' . $timestamp . '.' . $this->get_file_extension();
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
