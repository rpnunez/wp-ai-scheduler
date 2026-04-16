<?php
if (!defined('ABSPATH')) {
    return;
}

/**
 * AIPS_System_Diagnostics_Scheduler_Provider
 */
class AIPS_System_Diagnostics_Scheduler_Provider implements AIPS_System_Diagnostic_Provider_Interface {

    /**
     * @return array<string, mixed>
     */
    public function get_diagnostics(): array {
        return array(
            'cron'             => $this->check_cron(),
            'scheduler health' => $this->check_scheduler_health(),
        );
    }

















}
