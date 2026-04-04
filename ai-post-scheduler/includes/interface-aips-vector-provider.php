<?php
/**
 * Vector Provider Interface
 *
 * Defines a common interface for vector retrieval providers.
 *
 * @package AI_Post_Scheduler
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
	exit;
}

interface AIPS_Vector_Provider {

	/**
	 * Get the provider name.
	 *
	 * @return string
	 */
	public function get_name();

	/**
	 * Determine if provider is available.
	 *
	 * @return bool
	 */
	public function is_available();

	/**
	 * Upsert vectors into the provider namespace.
	 *
	 * @param string $namespace Namespace to store vectors in.
	 * @param array  $vectors   Vector records.
	 * @return bool|WP_Error
	 */
	public function upsert($namespace, $vectors);

	/**
	 * Query nearest neighbors from the provider.
	 *
	 * @param string $namespace Namespace to query.
	 * @param array  $vector    Query vector.
	 * @param array  $options   Query options.
	 * @return array|WP_Error
	 */
	public function query($namespace, $vector, $options = array());
}
