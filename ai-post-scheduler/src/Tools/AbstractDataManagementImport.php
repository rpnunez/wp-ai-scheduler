<?php
namespace AIPS\Tools;

use AIPS\Helpers\DBHelper;

if (!defined('ABSPATH')) {
	exit;
}

abstract class AbstractDataManagementImport implements IDataManagementImport {

	/**
	 * Get all plugin table names
	 *
	 * @return array
	 */
	protected function get_tables() {
		return DBHelper::get_full_table_names();
	}

	/**
	 * Check if file extension is valid
	 *
	 * @param string $filename
	 * @return bool
	 */
	protected function check_file_extension($filename) {
		$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		return $extension === $this->get_file_extension();
	}
}
