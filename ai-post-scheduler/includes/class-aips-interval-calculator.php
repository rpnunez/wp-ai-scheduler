<?php
/**
 * Backward-compatibility shim for interval calculator.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('AIPS_Interval_Calculator', false) && class_exists('AIPS\\Services\\IntervalCalculator')) {
    class_alias('AIPS\\Services\\IntervalCalculator', 'AIPS_Interval_Calculator');
}
