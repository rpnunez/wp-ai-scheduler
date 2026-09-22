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
        $embedded = (bool) $embedded;

        $active_ai_provider          = AIPS_AI_Provider_Factory::create();
        $ai_provider_label           = $active_ai_provider->get_label();
        $ai_provider_available       = $active_ai_provider->is_available();
        $ai_provider_unavailable_msg = $ai_provider_available ? '' : $active_ai_provider->get_unavailable_reason();

        include AIPS_PLUGIN_DIR . 'templates/admin/system-status.php';
    }

    /**
     * Resolve a service from the container when available.
     *
     * @param string $class_name Service class name.
     * @return object
     */
    private function resolve_service($class_name) {
        $container = AIPS_Container::get_instance();

        return $container->has($class_name)
            ? $container->make($class_name)
            : new $class_name();
    }

    /**
     * Get system info by delegating to diagnostics service.
     *
     * @return array
     */
    public function get_system_info() {
        $diagnostics = $this->resolve_service(AIPS_System_Status_Diagnostics_Service::class);

        return $diagnostics->get_system_info();
    }

    /**
     * Get the grouped Refresh System task metadata for the template.
     *
     * @return array<int, array<string, mixed>>
     */
    private function get_refresh_task_groups() {
        $service = $this->resolve_service(AIPS_System_Diagnostics_Service::class);

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
