<?php
if (!defined('ABSPATH')) {
    return;
}

/**
 * AIPS_System_Diagnostics_Environment_Provider
 */
class AIPS_System_Diagnostics_Environment_Provider implements AIPS_System_Diagnostic_Provider_Interface {

    /**
     * @return array<string, mixed>
     */
    public function get_diagnostics(): array {
        return array(
            'environment' => $this->check_environment(),
            'plugin'      => $this->check_plugin(),
            'database'    => $this->check_database(),
            'filesystem'  => $this->check_filesystem(),
        );
    }

















}
