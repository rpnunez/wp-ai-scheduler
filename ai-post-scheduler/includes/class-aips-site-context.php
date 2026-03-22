<?php
/**
 * Backward-compatibility shim for site context.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('AIPS_Site_Context', false) && class_exists('AIPS\\Support\\SiteContext')) {
	class_alias('AIPS\\Support\\SiteContext', 'AIPS_Site_Context');
}
