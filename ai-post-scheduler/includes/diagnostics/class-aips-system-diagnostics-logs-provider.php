<?php
if (!defined('ABSPATH')) {
    return;
}

/**
 * AIPS_System_Diagnostics_Logs_Provider
 */
class AIPS_System_Diagnostics_Logs_Provider implements AIPS_System_Diagnostic_Provider_Interface {

    /**
     * @return array<string, mixed>
     */
    public function get_diagnostics(): array {
        return array(
            'notifications' => $this->check_notifications(),
            'logs'          => $this->check_logs(),
        );
    }

















}
