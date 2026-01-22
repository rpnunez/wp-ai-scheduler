<?php
namespace AIPS\Tools;

if (!defined('ABSPATH')) {
	exit;
}

interface IDataManagementImport {
	/**
	 * Get the import format name
	 *
	 * @return string
	 */
	public function get_format_name();

	/**
	 * Get the file extension for this format
	 *
	 * @return string
	 */
	public function get_file_extension();

	/**
	 * Validate the uploaded file
	 *
	 * @param array $file The uploaded file data from $_FILES
	 * @return bool|\WP_Error True on success, WP_Error on failure
	 */
	public function validate_file($file);

	/**
	 * Import the data from file
	 *
	 * @param string $file_path Path to the uploaded file
	 * @return bool|\WP_Error True on success, WP_Error on failure
	 */
	public function import($file_path);
}
