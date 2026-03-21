<?php
/**
 * Backward-compatibility shim for template processor.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('AIPS_Template_Processor', false) && class_exists('AIPS\\Services\\TemplateProcessor')) {
    class_alias('AIPS\\Services\\TemplateProcessor', 'AIPS_Template_Processor');
}
