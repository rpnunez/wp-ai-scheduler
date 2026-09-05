<?php
/**
 * History Query Service (Backwards-Compatibility Shim)
 *
 * The two query methods previously defined here (get_history and
 * get_partial_generations) now live on AIPS_History_Repository so that all
 * $wpdb access stays inside the repository layer. This shim keeps the class
 * name resolvable for any external code that instantiated it directly, and
 * proxies both methods through to the repository singleton.
 *
 * @package AI_Post_Scheduler
 * @since 1.5.0
 * @deprecated Use AIPS_History_Repository::instance() instead.
 */

if (!defined('ABSPATH')) {
    die;
}

class AIPS_History_Query_Service {

    /**
     * @param array $args
     * @return array
     */
    public function get_history($args = array()) {
        return AIPS_History_Repository::instance()->get_history($args);
    }

    /**
     * @param array $args
     * @return array
     */
    public function get_partial_generations($args = array()) {
        return AIPS_History_Repository::instance()->get_partial_generations($args);
    }
}
