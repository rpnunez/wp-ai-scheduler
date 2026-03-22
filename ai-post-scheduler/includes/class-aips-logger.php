<?php
/**
 * Backward-compatibility shim for logger.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('AIPS_Logger', false) && class_exists('AIPS\\Support\\Logger')) {
    class_alias('AIPS\\Support\\Logger', 'AIPS_Logger');
}
