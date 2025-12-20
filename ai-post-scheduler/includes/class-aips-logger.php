<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Logger {
    
    private $log_file;
    private $enabled;
    private $dir_checked = false;
    
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/aips-logs';
        
        $this->log_file = $log_dir . '/aips-' . date('Y-m-d') . '.log';
        $this->enabled = (bool) get_option('aips_enable_logging', true);
    }

    private function ensure_directory_exists() {
        if ($this->dir_checked) {
            return;
        }

        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/aips-logs';

        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            file_put_contents($log_dir . '/.htaccess', 'deny from all');
            file_put_contents($log_dir . '/index.php', '<?php // Silence is golden');
        }
        
        $this->dir_checked = true;
    }
    
    public function log($message, $level = 'info', $context = array()) {
        if (!$this->enabled) {
            return;
        }

        $this->ensure_directory_exists();
        
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

        // Optimization: Use efficient tail reading for large files
        // Reading only the last chunk of the file avoids scanning the entire file
        // 100KB is usually enough for ~500-1000 lines
        $chunk_size = max(102400, $lines * 500);
        $file_size = filesize($this->log_file);

        if ($file_size > $chunk_size) {
            $fp = fopen($this->log_file, 'r');
            if ($fp) {
                fseek($fp, -$chunk_size, SEEK_END);
                $content = fread($fp, $chunk_size);
                fclose($fp);

                // Explode by newline
                $file_lines = explode(PHP_EOL, $content);

                // The first line is likely partial, so remove it if we have multiple lines
                if (count($file_lines) > 1) {
                    array_shift($file_lines);
                }

                // Filter empty lines
                $logs = array_filter($file_lines, function($line) {
                    return !empty(trim($line));
                });

                // Return last $lines
                return array_slice($logs, -$lines);
            }
        }
        
        // Fallback for small files or if fopen fails
        try {
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
        } catch (Exception $e) {
            return array();
        }
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
