<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Upgrades {
    
    private $logger;
    
    public function __construct() {
        $this->logger = new AIPS_Logger();
    }
    
    public static function check_and_run() {
        $current_version = AIPS_Config::get_instance()->get_option('aips_db_version');
        
        if (version_compare($current_version, AIPS_VERSION, '<')) {
            $instance = new self();
            $instance->run_upgrade($current_version);
        }
    }
    
    private function run_upgrade($from_version) {
        // Use dbDelta to update schema - it handles adding new tables and columns automatically
        // This is the WordPress standard approach for database schema updates
        $result = AIPS_DB_Manager::install_tables();

        if (is_wp_error($result)) {
            $notifications = class_exists('AIPS_Notifications') ? new AIPS_Notifications() : null;

            if ($notifications instanceof AIPS_Notifications) {
                $notifications->system_error(array(
                    'title'         => __('Database upgrade failed', 'ai-post-scheduler'),
                    'error_code'    => $result->get_error_code(),
                    'error_message' => $result->get_error_message(),
                    'from_version'  => $from_version,
                    'url'           => admin_url('admin.php?page=aips-status'),
                    'dedupe_key'    => 'db_upgrade_failed_' . sanitize_key((string) $from_version),
                    'dedupe_window' => 1800,
                ));
            }

            return $result;
        }

        update_option('aips_db_version', AIPS_VERSION);
        $this->logger->log('Database upgraded from version ' . $from_version . ' to ' . AIPS_VERSION, 'info');

        return true;
    }
}
?>
