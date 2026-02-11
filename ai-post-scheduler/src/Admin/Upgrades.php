<?php

namespace AIPS\Admin;

if (!defined('ABSPATH')) {
    exit;
}


class Upgrades {
    
    private $logger;
    
    public function __construct() {
        $this->logger = new AIPS_Logger();
    }
    
    public static function check_and_run() {
        $current_version = get_option('aips_db_version', '0');
        
        if (version_compare($current_version, AIPS_VERSION, '<')) {
            $instance = new self();
            $instance->run_upgrade($current_version);
        }
    }
    
    private function run_upgrade($from_version) {
        // Use dbDelta to update schema - it handles adding new tables and columns automatically
        // This is the WordPress standard approach for database schema updates
        AIPS_DB_Manager::install_tables();

        update_option('aips_db_version', AIPS_VERSION);
        $this->logger->log('Database upgraded from version ' . $from_version . ' to ' . AIPS_VERSION, 'info');
    }
}
?>
