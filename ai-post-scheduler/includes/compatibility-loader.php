<?php
/**
 * Compatibility Loader
 *
 * Handles backward compatibility for class aliases during the PSR-4 migration.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

// This file will contain class_alias() calls as classes are migrated to src/
// Example:
// class_alias('AIPS\\Repositories\\TemplateRepository', 'AIPS_Template_Repository');
