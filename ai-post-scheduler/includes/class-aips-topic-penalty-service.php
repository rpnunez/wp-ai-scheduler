<?php
/**
 * Backward-compatibility shim for topic penalty service.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('AIPS_Topic_Penalty_Service', false) && class_exists('AIPS\\Services\\TopicPenaltyService')) {
	class_alias('AIPS\\Services\\TopicPenaltyService', 'AIPS_Topic_Penalty_Service');
}
