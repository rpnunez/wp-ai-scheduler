<?php
if (!defined('ABSPATH')) {
	exit;
}

if (class_exists('AIPS_Bulk_Batch_Job_Store') && !class_exists('AIPS_Bulk_Batch_Jobs_Repository', false)) {
	class_alias('AIPS_Bulk_Batch_Job_Store', 'AIPS_Bulk_Batch_Jobs_Repository');
}
