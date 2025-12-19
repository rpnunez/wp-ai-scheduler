<?php
if (!defined('ABSPATH')) {
    exit;
}

// Delegate to central DB manager
AIPS_DB_Manager::install_tables();
?>
