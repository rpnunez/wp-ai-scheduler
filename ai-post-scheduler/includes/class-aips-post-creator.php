<?php
/**
 * Legacy Post Creator alias.
 *
 * @package AI_Post_Scheduler
 * @since 1.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('AIPS_Post_Manager')) {
	require_once __DIR__ . '/class-aips-post-manager.php';
}

/**
 * @deprecated 1.7.0 Use AIPS_Post_Manager instead.
 */
class AIPS_Post_Creator extends AIPS_Post_Manager {

	/**
	 * Constructor.
	 */
	public function __construct() {
		if (function_exists('_deprecated_class')) {
			_deprecated_class(__CLASS__, '1.7.0', 'AIPS_Post_Manager');
		}
	}
}
        
