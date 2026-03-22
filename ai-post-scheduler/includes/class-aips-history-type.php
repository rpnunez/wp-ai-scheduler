<?php
/**
 * Backward-compatibility shim for history type.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('AIPS_History_Type', false) && class_exists('AIPS\\History\\HistoryType')) {
	class_alias('AIPS\\History\\HistoryType', 'AIPS_History_Type');
}
