<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Repository alias for bulk batch job persistence.
 *
 * P2 extraction follow-up: prefer injecting this repository name in services.
 */
class AIPS_Bulk_Batch_Jobs_Repository extends AIPS_Bulk_Batch_Job_Store {}
