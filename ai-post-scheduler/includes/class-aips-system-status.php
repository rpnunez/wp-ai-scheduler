<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_System_Status {

    /**
     * Minimum success rate (%) considered healthy for scheduler runs and
     * post-generation outcomes.  At or above this threshold → 'ok'.
     */
    const METRIC_OK_THRESHOLD = 90;

    /**
     * Minimum success rate (%) considered a warning level.
     * Between METRIC_WARN_THRESHOLD and METRIC_OK_THRESHOLD → 'warning'.
     * Below METRIC_WARN_THRESHOLD → 'error'.
     */
    const METRIC_WARN_THRESHOLD = 70;

    /**
     * Maximum image-generation failure rate (%) considered healthy → 'ok'.
     */
    const IMAGE_FAIL_OK_THRESHOLD = 10;

    /**
     * Image-generation failure rate (%) upper bound for 'warning' level.
     * Above this → 'error'.
     */
    const IMAGE_FAIL_WARN_THRESHOLD = 30;

    /**
     * Number of stuck jobs at or above which the status is 'warning'.
     */
    const QUEUE_STUCK_WARN_THRESHOLD = 1;

    /**
     * Number of stuck jobs at or above which the status escalates to 'error'.
     */
    const QUEUE_STUCK_ERROR_THRESHOLD = 5;

    /**
     * Retry-saturation percentage at or above which the status is 'warning' (0–100).
     */
    const QUEUE_RETRY_WARN_THRESHOLD = 20;

    /**
     * Retry-saturation percentage above which the status escalates to 'error'.
     */
    const QUEUE_RETRY_ERROR_THRESHOLD = 50;

    public function render_page() {
        $system_info = $this->get_system_info();
        $data_management = $this->get_data_management();

        if ( $data_management ) {
            $export_formats = $data_management->get_export_formats();
            $import_formats = $data_management->get_import_formats();
        } else {
            $export_formats = array();
            $import_formats = array();
        }

        include AIPS_PLUGIN_DIR . 'templates/admin/system-status.php';
    }

    /**
     * Get the AIPS_Data_Management instance without causing duplicate hook registrations.
     *
     * @return AIPS_Data_Management|null
     */
    private function get_data_management() {
        if ( ! class_exists( 'AIPS_Data_Management' ) ) {
            return null;
        }

        // Prefer a shared/global instance if the plugin exposes one.
        global $aips_data_management;
        if ( isset( $aips_data_management ) && $aips_data_management instanceof AIPS_Data_Management ) {
            return $aips_data_management;
        }

        // Fallback to a singleton accessor if available.
        if ( method_exists( 'AIPS_Data_Management', 'get_instance' ) ) {
            return AIPS_Data_Management::get_instance();
        }

        // As a last resort, create a new instance.
        return new AIPS_Data_Management();
    }
    public function get_system_info() {
        return (new AIPS_System_Diagnostics_Service())->get_system_info();
    }
}
