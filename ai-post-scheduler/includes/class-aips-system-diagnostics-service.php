<?php
if (!defined('ABSPATH')) {
    return;
}

/**
 * AIPS_System_Diagnostics_Service
 *
 * Aggregates diagnostic data from multiple system diagnostic providers.
 */
class AIPS_System_Diagnostics_Service {

    /**
     * @var AIPS_System_Diagnostic_Provider_Interface[]
     */
    private $providers = [];

    /**
     * Constructor. Initializes the default diagnostic providers.
     */
    public function __construct() {
        $this->providers = array(
            new AIPS_System_Diagnostics_Environment_Provider(),
            new AIPS_System_Diagnostics_Scheduler_Provider(),
            new AIPS_System_Diagnostics_Queue_Provider(),
            new AIPS_System_Diagnostics_Logs_Provider(),
        );
    }

    /**
     * Add a custom provider.
     *
     * @param AIPS_System_Diagnostic_Provider_Interface $provider
     */
    public function add_provider(AIPS_System_Diagnostic_Provider_Interface $provider) {
        $this->providers[] = $provider;
    }

    /**
     * Aggregates system info from all registered providers.
     *
     * @return array<string, mixed>
     */
    public function get_system_info(): array {
        $system_info = array();
        foreach ($this->providers as $provider) {
            $system_info = array_merge($system_info, $provider->get_diagnostics());
        }

        $ordered_info = array();
        $expected_keys = array(
            'environment', 'plugin', 'database', 'filesystem',
            'cron', 'scheduler health', 'queue health',
            'generation metrics', 'resilience', 'notifications', 'logs'
        );
        foreach ($expected_keys as $key) {
            if (isset($system_info[$key])) {
                $ordered_info[$key] = $system_info[$key];
            }
        }

        return $ordered_info;
    }
}