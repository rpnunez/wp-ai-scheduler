<?php
/**
 * Backward-compatibility shim for generation logger.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('AIPS_Generation_Logger', false) && class_exists('AIPS\\Logging\\GenerationLogger')) {
    class_alias('AIPS\\Logging\\GenerationLogger', 'AIPS_Generation_Logger');
}
