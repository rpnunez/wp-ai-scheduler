<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_System_Status {


    public function render_page($embedded = false) {
        $system_info = $this->get_system_info();
        $refresh_task_groups = $this->get_refresh_task_groups();
        if ( isset( $system_info['database'] ) ) {
            $system_info['database'] = $this->condense_database_checks( $system_info['database'] );
        }
        $data_management = $this->get_data_management();
        $embedded = (bool) $embedded;

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
        $diagnostics = new AIPS_System_Status_Diagnostics_Service();
        return $diagnostics->get_system_info();
    }

    /**
     * Get the grouped Refresh System task metadata for the template.
     *
     * @return array<int, array<string, mixed>>
     */
    private function get_refresh_task_groups() {
        $container = AIPS_Container::get_instance();
        $service   = $container->has(AIPS_System_Diagnostics_Service::class)
        	? $container->make(AIPS_System_Diagnostics_Service::class)
        	: new AIPS_System_Diagnostics_Service();

        return $service->get_refresh_task_groups();
    }

    /**
     * Condense the per-table database checks for display.
     *
     * When every table check passes, the whole list is replaced with a single
     * summary row. When any table fails, only the failing rows are kept, with
     * a trailing summary row for the healthy remainder. Presentation-only —
     * the diagnostics service keeps returning the full per-table data.
     *
     * @param array $checks Database section checks.
     * @return array
     */
    public function condense_database_checks( array $checks ) {
        $total   = count( $checks );
        $failing = array();

        foreach ( $checks as $key => $check ) {
            if ( ! isset( $check['status'] ) || 'ok' !== $check['status'] ) {
                $failing[ $key ] = $check;
            }
        }

        if ( 0 === $total ) {
            return $checks;
        }

        if ( empty( $failing ) ) {
            return array(
                'tables_summary' => array(
                    'label'  => __( 'Plugin Tables', 'ai-post-scheduler' ),
                    'value'  => sprintf( __( 'All %d tables OK', 'ai-post-scheduler' ), $total ),
                    'status' => 'ok',
                ),
            );
        }

        $ok_count = $total - count( $failing );
        $failing['tables_summary'] = array(
            'label'  => __( 'Plugin Tables', 'ai-post-scheduler' ),
            'value'  => sprintf( __( '%1$d of %2$d tables OK', 'ai-post-scheduler' ), $ok_count, $total ),
            'status' => 'warning',
        );

        return $failing;
    }
}
