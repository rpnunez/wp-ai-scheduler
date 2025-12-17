<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Logger {
    
    private $log_file;
    private $enabled;
    
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/aips-logs';
        
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            file_put_contents($log_dir . '/.htaccess', 'deny from all');
            file_put_contents($log_dir . '/index.php', '<?php // Silence is golden');
        }
        
        $this->log_file = $log_dir . '/aips-' . date('Y-m-d') . '.log';
        $this->enabled = (bool) get_option('aips_enable_logging', true);
    }
    
    public function log($message, $level = 'info', $context = array()) {
        if (!$this->enabled) {
            return;
        }
        
        $timestamp = current_time('mysql');
        $level = strtoupper($level);
        
        $log_entry = sprintf(
            "[%s] [%s] %s",
            $timestamp,
            $level,
            $message
        );
        
        if (!empty($context)) {
            $log_entry .= ' | Context: ' . json_encode($context);
        }
        
        $log_entry .= PHP_EOL;
        
        error_log($log_entry, 3, $this->log_file);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AI Post Scheduler] ' . $log_entry);
        }
    }
    
    public function get_logs($lines = 100) {
        if (!file_exists($this->log_file)) {
            return array();
        }
        
        $file = new SplFileObject($this->log_file, 'r');
        $file->seek(PHP_INT_MAX);
        $total_lines = $file->key();
        
        $start = max(0, $total_lines - $lines);
        $file->seek($start);
        
        $logs = array();
        while (!$file->eof()) {
            $line = trim($file->fgets());
            if (!empty($line)) {
                $logs[] = $line;
            }
        }
        
        return $logs;
    }
    
    public function clear_logs() {
        if (file_exists($this->log_file)) {
            unlink($this->log_file);
        }
        return true;
    }
    
    public function get_log_files() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/aips-logs';
        
        if (!is_dir($log_dir)) {
            return array();
        }
        
        $files = glob($log_dir . '/aips-*.log');
        $log_files = array();
        
        foreach ($files as $file) {
            $log_files[] = array(
                'name' => basename($file),
                'size' => size_format(filesize($file)),
                'modified' => date('Y-m-d H:i:s', filemtime($file)),
            );
        }
        
        return $log_files;
    }
}
