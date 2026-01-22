<?php
namespace AIPS\Tools;

if (!defined('ABSPATH')) {
	exit;
}

interface IDataManagementExport {
	/**
	 * Get the export format name
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
	 * Get the MIME type for this format
	 *
	 * @return string
	 */
	public function get_mime_type();

	/**
	 * Export the data
	 *
	 * @return string The exported data as a string
	 */
	public function export();
}
