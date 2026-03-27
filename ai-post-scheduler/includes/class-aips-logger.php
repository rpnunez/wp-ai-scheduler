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
        
        // SECURITY: Append a random secret to the log filename to prevent
        // unauthorized access on servers where .htaccess is ignored (e.g. Nginx).
        $secret = $this->get_log_secret();

        $this->log_file = $log_dir . '/aips-' . date('Y-m-d') . '-' . $secret . '.log';
        $this->enabled = (bool) get_option('aips_enable_logging', true);
    }

    /**
     * Get or generate a persistent secret token for log files.
     * This ensures log filenames are not guessable.
     */
    private function get_log_secret() {
        $secret = get_option('aips_log_secret');

        if (empty($secret)) {
            // Generate a secure random string
            if (function_exists('wp_generate_password')) {
                $secret = wp_generate_password(12, false);
            } else {
                $secret = bin2hex(random_bytes(6));
            }
            update_option('aips_log_secret', $secret);
        }

        return $secret;
    }

    /**
     * Ensures the log directory exists and is writable.
     * Disables logging if directory creation fails to prevent endless errors.
     *
     * @return void
     */
    private function ensure_directory_exists() {
        if ($this->dir_checked) {
            return;
        }

        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/aips-logs';

        if (!file_exists($log_dir)) {
            if (!wp_mkdir_p($log_dir)) {
                $this->enabled = false;
                $this->dir_checked = true;
                return;
            }
            if (is_writable($log_dir)) {
                file_put_contents($log_dir . '/.htaccess', 'deny from all');
                file_put_contents($log_dir . '/index.php', '<?php // Silence is golden');
            }
        } elseif (!is_writable($log_dir)) {
            $this->enabled = false;
        }
        
        $this->dir_checked = true;
    }
    
    /**
     * Writes a message to the log file.
     *
     * @param string $message The message to log.
     * @param string $level   The log severity level (e.g., info, warning, error).
     * @param array  $context Optional context array to append as JSON.
     * @return void
     */
    public function log($message, $level = 'info', $context = array()) {
        if (!$this->enabled) {
            return;
        }

        $this->ensure_directory_exists();
        
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
        
        // Ensure file is writable before writing
        if (!file_exists($this->log_file) || is_writable($this->log_file)) {
            $result = error_log($log_entry, 3, $this->log_file);
            if (!$result) {
                $this->enabled = false;
            }
        } else {
            $this->enabled = false;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AI Post Scheduler] ' . $log_entry);
        }
    }

    /**
     * Logs a warning message.
     *
     * @param string $message The warning message.
     * @param array  $context Optional context data.
     * @return void
     */
    public function warning($message, $context = array()) {
        $this->log($message, 'warning', $context);
    }

    /**
     * Logs an error message.
     *
     * @param string $message The error message.
     * @param array  $context Optional context data.
     * @return void
     */
    public function error($message, $context = array()) {
        $this->log($message, 'error', $context);
    }
    
    /**
     * Retrieves the most recent log lines.
     * Uses fseek for O(1) tail read performance on large files.
     *
     * @param int $lines Number of lines to retrieve.
     * @return array Array of log lines, or empty array on failure.
     */
    public function get_logs($lines = 100) {
        if (!file_exists($this->log_file) || !is_readable($this->log_file)) {
            return array();
        }

        // Performance Fix: Use fseek to read only the end of the file
        // instead of SplFileObject seeking which scans the whole file.
        $fp = fopen($this->log_file, 'r');
        if (!$fp) {
            return array();
        }

        $chunk_size = 1024 * 100; // Read 100KB from end
        fseek($fp, 0, SEEK_END);
        $filesize = ftell($fp);

        if ($filesize === false || $filesize <= 0) {
            fclose($fp);
            return array();
        }

        // If file is smaller than chunk, read whole file
        $seek_offset = max(0, $filesize - $chunk_size);
        fseek($fp, $seek_offset);
        
        $content = '';
        // If we sought to a non-zero position, discard the first partial line
        // unless we are exactly at the start (offset 0).
        // Actually, reading a chunk might start in middle of line.
        // We will read, then explode, and if offset > 0, discard first element.
        
        $content = fread($fp, $chunk_size);
        fclose($fp);

        if ($content === false) {
            return array();
        }

        $file_lines = explode("\n", $content);

        // If we started from middle of file (offset > 0), the first line is likely partial.
        // However, standard text files end with newline.
        // If we read the last chunk, the last char is likely newline (resulting in empty string at end of array).
        // Let's clean up empty lines first.

        // Remove empty lines (especially the trailing one if file ends with newline)
        $file_lines = array_filter($file_lines, function($line) {
            return trim($line) !== '';
        });

        // If we read a partial chunk (offset > 0), discard the first line as it might be incomplete
        if ($seek_offset > 0 && count($file_lines) > 0) {
            array_shift($file_lines);
        }
        
        // Return only requested number of lines, from the end
        if (count($file_lines) > $lines) {
            $file_lines = array_slice($file_lines, -$lines);
        }
        
        return array_values($file_lines);
    }
    
    /**
     * Clears the current log file.
     *
     * @return bool True on success, false otherwise.
     */
    public function clear_logs() {
        if (!file_exists($this->log_file)) {
            return true;
        }

        if (is_writable($this->log_file)) {
            return unlink($this->log_file);
        }

        return false;
    }
    
    /**
     * Gets a list of all existing log files.
     *
     * @return array List of log files with their metadata.
     */
    public function get_log_files() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/aips-logs';
        
        if (!is_dir($log_dir) || !is_readable($log_dir)) {
            return array();
        }
        
        $files = glob($log_dir . '/aips-*.log');
        if (!is_array($files)) {
            return array();
        }

        $log_files = array();
        
        foreach ($files as $file) {
            if (!is_readable($file)) {
                continue;
            }
            $size = filesize($file);
            $log_files[] = array(
                'name' => basename($file),
                'size' => $size !== false ? size_format($size) : 'Unknown',
                'modified' => date('Y-m-d H:i:s', filemtime($file)),
            );
        }
        
        return $log_files;
    }
}
