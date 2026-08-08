<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Standardized admin controller entry for History page rendering.
 *
 * Keeps backward compatibility by extending the legacy AIPS_History class
 * while providing the canonical *_Controller naming.
 */
class AIPS_History_Controller extends AIPS_History implements AIPS_Admin_Controller_Interface {
}

