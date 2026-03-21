<?php
/**
 * Backward-compatibility shim for config.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('AIPS_Config', false) && class_exists('AIPS\\Support\\Config')) {
    class_alias('AIPS\\Support\\Config', 'AIPS_Config');
}
