<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_System_Status {


    public function render_page($embedded = false) {
        $system_info = $this->get_system_info();
        $data_management = $this->get_data_management();
        $embedded = (bool) $embedded;

        $active_ai_provider          = AIPS_AI_Provider_Factory::create();
        $ai_provider_label           = $active_ai_provider->get_label();
        $ai_provider_available       = $active_ai_provider->is_available();
        $ai_provider_unavailable_msg = $ai_provider_available ? '' : $active_ai_provider->get_unavailable_reason();

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
    /**
     * Get system info by delegating to diagnostics service.
     *
     * @return array
     */
    public function get_system_info() {
        $diagnostics = new AIPS_System_Diagnostics_Service();
        return $diagnostics->get_system_info();
    }
}
