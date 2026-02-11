<?php
/**
 * Backward Compatibility Layer
 * 
 * Provides class aliases for old class names to maintain backward compatibility
 * with third-party code that may reference the old AIPS_* class names.
 * 
 * This file will be maintained for 2-3 versions and then deprecated.
 * 
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

// This file will be populated as classes are migrated
// Format: class_alias('AIPS\\Namespace\\NewClassName', 'AIPS_Old_Class_Name');

// Example:
// class_alias('AIPS\\Repositories\\TemplateRepository', 'AIPS_Template_Repository');
