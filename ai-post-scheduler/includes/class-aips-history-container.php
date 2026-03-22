<?php
/**
 * Backward-compatibility shim for history container.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('AIPS_History_Container', false) && class_exists('AIPS\\History\\HistoryContainer')) {
	class_alias('AIPS\\History\\HistoryContainer', 'AIPS_History_Container');
}
