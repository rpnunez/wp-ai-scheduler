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
        $current_version = get_option('aips_db_version', '0');
        
        if (version_compare($current_version, AIPS_VERSION, '<')) {
            $instance = new self();
            $instance->run_migrations($current_version);
        }
    }
    
    private function run_migrations($from_version) {
        $migrations = array(
            '1.0' => 'migration-1.0-initial.php',
            '1.1' => 'migration-1.1-add-voices.php',
            '1.2' => 'migration-1.2-add-featured-images.php',
        );
        
        foreach ($migrations as $version => $file) {
            if (version_compare($from_version, $version, '<')) {
                $this->run_migration($version, $file);
            }
        }
        
        update_option('aips_db_version', AIPS_VERSION);
        $this->logger->log('Database upgrade completed to version ' . AIPS_VERSION, 'info');
    }
    
    private function run_migration($version, $file) {
        $migration_file = AIPS_PLUGIN_DIR . 'migrations/' . $file;
        
        if (!file_exists($migration_file)) {
            $this->logger->log('Migration file not found: ' . $migration_file, 'error');
            return false;
        }
        
        try {
            require_once $migration_file;
            $this->logger->log('Migration applied: ' . $version, 'info');
            return true;
        } catch (Exception $e) {
            $this->logger->log('Migration failed for ' . $version . ': ' . $e->getMessage(), 'error');
            return false;
        }
    }
}
?>
