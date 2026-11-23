<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Logger implements AIPS_Logger_Interface {

    /**
     * @var self|null Singleton instance.
     */
    private static $instance = null;

    /**
     * Get the shared singleton instance.
     *
     * @return self
     */
    public static function instance(): self {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private $log_file;
    private $enabled;
    private $dir_checked = false;
    
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/aips-logs';
        
        // SECURITY: Append a random secret to the log filename to prevent
        // unauthorized access on servers where .htaccess is ignored (e.g. Nginx).
        $secret = $this->get_log_secret();

        $this->log_file = $log_dir . '/aips-' . AIPS_DateTime::now()->toDisplay('Y-m-d') . '-' . $secret . '.log';
        $this->enabled = (bool) AIPS_Config::get_instance()->get_option('aips_enable_logging');
    }

    /**
     * Get or generate a persistent secret token for log files.
     * This ensures log filenames are not guessable.
     */
    private function get_log_secret() {
        $secret = AIPS_Config::get_instance()->get_option('aips_log_secret');

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
     * Ensure the log directory exists and has correct security constraints.
     * Logs errors if directory or protection files fail to create.
     */
    private function ensure_directory_exists() {
        if ($this->dir_checked) {
            return;
        }

        // Set early to prevent recursive calls when $this->log() is called below.
        $this->dir_checked = true;

        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/aips-logs';

        if (!file_exists($log_dir)) {
            if (!wp_mkdir_p($log_dir)) {
                // Cannot use $this->log() here — the log directory does not exist yet.
                error_log('AIPS_Logger: Failed to create log directory: ' . $log_dir);
                return;
            }
            if (file_put_contents($log_dir . '/.htaccess', 'deny from all') === false) {
                $this->log('Failed to create .htaccess in log directory: ' . $log_dir, 'warning');
            }
            if (file_put_contents($log_dir . '/index.php', '<?php // Silence is golden') === false) {
                $this->log('Failed to create index.php in log directory: ' . $log_dir, 'warning');
            }
        }
    }
    
    public function log($message, $level = 'info', $context = array()) {
        if (!$this->enabled) {
            return;
        }

        $this->ensure_directory_exists();
        
        $timestamp = AIPS_DateTime::now()->toMysql();
        $level = strtoupper($level);
        
        $log_entry = sprintf(
            "[%s] [%s] %s",
            $timestamp,
            $level,
            $message
        );
        
        if (!empty($context)) {
            $log_entry .= PHP_EOL;
            $log_entry .= 'Context: ' . json_encode($context, JSON_PRETTY_PRINT);
        }
        
        $log_entry .= PHP_EOL;
        
        error_log($log_entry, 3, $this->log_file);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AI Post Scheduler] ' . $log_entry);
        }
    }

    public function warning($message, $context = array()) {
        $this->log($message, 'warning', $context);
    }

    public function error($message, $context = array()) {
        $this->log($message, 'error', $context);
    }

    public function addSeparator($text) {
        $separator = str_repeat('-', 20);
        if (!empty($text)) {
            $separator .= ' ' . $text . ' ' . str_repeat('-', 20);
        }
        $this->log($separator, 'info'); // Use 'info' level for separators
    }
    
    /**
     * Retrieves the most recent log entries.
     *
     * @param int $lines Number of lines to return.
     * @return array Array of log entries.
     */
    public function get_logs($lines = 100) {
        if (!file_exists($this->log_file) || !is_readable($this->log_file)) {
            return array();
        }

        // Performance Fix: Use fseek to read only the end of the file
        // instead of SplFileObject seeking which scans the whole file.
        $fp = fopen($this->log_file, 'r');
        if ($fp === false) {
            return array();
        }

        $chunk_size = 1024 * 100; // Read 100KB from end
        if (fseek($fp, 0, SEEK_END) === -1) {
            fclose($fp);
            return array();
        }

        $filesize = ftell($fp);

        if ($filesize === false || $filesize <= 0) {
            fclose($fp);
            return array();
        }

        // If file is smaller than chunk, read whole file
        $seek_offset = max(0, $filesize - $chunk_size);
        if (fseek($fp, $seek_offset) === -1) {
            fclose($fp);
            return array();
        }
        
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
     * Clear the current log file.
     *
     * @return bool True if successful, false if deletion fails.
     */
    public function clear_logs() {
        if (file_exists($this->log_file)) {
            if (!unlink($this->log_file)) {
                // Cannot use $this->log() here — writing to the file that failed to be deleted is unreliable.
                error_log('AIPS_Logger: Failed to delete log file: ' . $this->log_file);
                return false;
            }
        }
        return true;
    }
    
    /**
     * Gets a list of available log files.
     *
     * @return array List of log files with size, bytes, modified dates, and active status.
     */
    public function get_log_files() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/aips-logs';
        
        if (!is_dir($log_dir) || !is_readable($log_dir)) {
            return array();
        }
        
        $files = glob($log_dir . '/aips-*.log');

        if ($files === false) {
            return array();
        }

        $log_files = array();
        $active_basename = basename($this->log_file);
        
        foreach ($files as $file) {
            if (!is_readable($file)) {
                continue;
            }

            $file_size = filesize($file);
            $file_mtime = filemtime($file);
            $base_name = basename($file);

            $log_files[] = array(
                'name'      => $base_name,
                'bytes'     => $file_size !== false ? (int) $file_size : 0,
                'size'      => $file_size !== false ? size_format($file_size) : size_format(0),
                'modified'  => $file_mtime !== false ? date('Y-m-d H:i:s', $file_mtime) : 'Unknown',
                'timestamp' => $file_mtime !== false ? (int) $file_mtime : 0,
                'is_active' => ($base_name === $active_basename),
            );
        }

        usort($log_files, function ($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
        
        return $log_files;
    }

    /**
     * Validates and resolves a log filename securely within the aips-logs directory.
     * Prevents directory traversal attacks.
     *
     * @param string $filename Base filename to validate.
     * @return string|null Absolute path if valid, null otherwise.
     */
    public function validate_log_filename($filename) {
        if (!is_string($filename) || empty($filename)) {
            return null;
        }

        $clean_name = basename(sanitize_file_name($filename));
        if (!preg_match('/^aips-[a-zA-Z0-9\._-]+\.log$/', $clean_name)) {
            return null;
        }

        $upload_dir = wp_upload_dir();
        $log_dir = wp_normalize_path($upload_dir['basedir'] . '/aips-logs');
        $target_path = wp_normalize_path($log_dir . '/' . $clean_name);

        if (!file_exists($target_path)) {
            return null;
        }

        $real_target = wp_normalize_path((string) realpath($target_path));
        $real_dir    = wp_normalize_path((string) realpath($log_dir));

        if (empty($real_target) || empty($real_dir)) {
            return null;
        }

        if (strpos($real_target, $real_dir . '/') !== 0 && $real_target !== $real_dir) {
            return null;
        }

        return $real_target;
    }

    /**
     * Retrieves parsed and filtered content from a specific log file.
     * Reads up to 2MB from the tail using fseek.
     *
     * @param string $filename Log filename.
     * @param int    $lines    Number of entries to return (default 100, max 2000).
     * @param string $level    Level filter ('all', 'error', 'warning', 'info').
     * @param string $search   Keyword filter.
     * @return array|null Array of log data or null if file is invalid.
     */
    public function get_log_file_content($filename, $lines = 100, $level = 'all', $search = '') {
        $target_path = $this->validate_log_filename($filename);
        if (!$target_path || !is_readable($target_path)) {
            return null;
        }

        $lines = min(2000, max(1, (int) $lines));
        $level = strtolower(trim((string) $level));
        $search = trim((string) $search);

        $fp = fopen($target_path, 'r');
        if ($fp === false) {
            return null;
        }

        $filesize = filesize($target_path);
        if ($filesize === false || $filesize <= 0) {
            fclose($fp);
            return array(
                'file'         => basename($target_path),
                'total_bytes'  => 0,
                'total_lines'  => 0,
                'is_truncated' => false,
                'entries'      => array(),
                'raw_content'  => '',
            );
        }

        $chunk_size = min($filesize, 2 * 1024 * 1024); // Up to 2MB from end
        $seek_offset = max(0, $filesize - $chunk_size);

        if (fseek($fp, $seek_offset) === -1) {
            fclose($fp);
            return null;
        }

        $content = fread($fp, $chunk_size);
        fclose($fp);

        if ($content === false) {
            return null;
        }

        $raw_lines = explode("\n", $content);
        if ($seek_offset > 0 && count($raw_lines) > 0) {
            array_shift($raw_lines); // Discard incomplete leading line
        }

        // Group into logical log entries
        $parsed_entries = array();
        $current_entry  = null;

        foreach ($raw_lines as $raw_line) {
            $trimmed = rtrim($raw_line, "\r");
            if ('' === trim($trimmed)) {
                continue;
            }

            // Standard AIPS log pattern: [YYYY-MM-DD HH:MM:SS] [LEVEL] Message
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})\]\s*\[([A-Z]+)\]\s*(.*)$/', $trimmed, $matches)) {
                if ($current_entry !== null) {
                    $parsed_entries[] = $current_entry;
                }
                $current_entry = array(
                    'timestamp' => $matches[1],
                    'level'     => strtoupper($matches[2]),
                    'message'   => $matches[3],
                    'context'   => '',
                    'raw'       => $trimmed,
                );
            } elseif ($current_entry !== null) {
                // Continuation line (e.g. Context: {...} or multiline trace)
                if (strpos(trim($trimmed), 'Context:') === 0) {
                    $current_entry['context'] .= substr(trim($trimmed), 8);
                } else {
                    $current_entry['context'] .= ($current_entry['context'] !== '' ? "\n" : '') . $trimmed;
                }
                $current_entry['raw'] .= "\n" . $trimmed;
            } else {
                // Unformatted / separator line before any matched header
                $parsed_entries[] = array(
                    'timestamp' => '',
                    'level'     => 'INFO',
                    'message'   => $trimmed,
                    'context'   => '',
                    'raw'       => $trimmed,
                );
            }
        }

        if ($current_entry !== null) {
            $parsed_entries[] = $current_entry;
        }

        // Apply level and search filters
        $filtered = array();
        foreach ($parsed_entries as $entry) {
            if ('all' !== $level && !empty($level)) {
                if (strtolower($entry['level']) !== $level) {
                    continue;
                }
            }

            if ('' !== $search) {
                $haystack = $entry['message'] . ' ' . $entry['context'] . ' ' . $entry['level'] . ' ' . $entry['timestamp'];
                if (stripos($haystack, $search) === false) {
                    continue;
                }
            }

            $filtered[] = $entry;
        }

        $is_truncated = ($seek_offset > 0) || (count($filtered) > $lines);
        if (count($filtered) > $lines) {
            $filtered = array_slice($filtered, -$lines);
        }

        // Reconstruct raw preview for the raw toggle view
        $raw_preview = '';
        foreach ($filtered as $f_entry) {
            $raw_preview .= $f_entry['raw'] . "\n";
        }

        return array(
            'file'         => basename($target_path),
            'total_bytes'  => (int) $filesize,
            'total_lines'  => count($filtered),
            'is_truncated' => $is_truncated,
            'entries'      => array_values($filtered),
            'raw_content'  => rtrim($raw_preview, "\n"),
        );
    }

    /**
     * Empties/truncates a specific log file to 0 bytes.
     *
     * @param string $filename Log filename.
     * @return bool True on success, false on failure.
     */
    public function empty_log_file($filename) {
        $target_path = $this->validate_log_filename($filename);
        if (!$target_path) {
            return false;
        }

        $fp = fopen($target_path, 'w');
        if ($fp === false) {
            return false;
        }
        fclose($fp);

        return true;
    }

    /**
     * Deletes a specific log file.
     *
     * @param string $filename Log filename.
     * @return bool True on success, false on failure.
     */
    public function delete_log_file($filename) {
        $target_path = $this->validate_log_filename($filename);
        if (!$target_path) {
            return false;
        }

        return @unlink($target_path);
    }
}
