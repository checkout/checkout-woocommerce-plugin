<?php
/**
 * Log Manager class for log rotation, cleanup, and maintenance.
 *
 * @package wc_checkout_com
 */

/**
 * Log Manager class for handling log files lifecycle.
 */
class WC_Checkoutcom_Log_Manager {

    /**
     * Log Manager instance.
     *
     * @var WC_Checkoutcom_Log_Manager
     */
    private static $instance = null;

    /**
     * Log directory path.
     *
     * @var string
     */
    private $log_directory;

    /**
     * Maximum log file size in bytes.
     *
     * @var int
     */
    private $max_file_size;

    /**
     * Maximum number of log files to keep.
     *
     * @var int
     */
    private $max_files;

    /**
     * Log retention period in days.
     *
     * @var int
     */
    private $retention_days;

    /**
     * Get singleton instance.
     *
     * @return WC_Checkoutcom_Log_Manager
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->log_directory = WC_LOG_DIR . 'checkout-com/';
        $this->max_file_size = $this->get_max_file_size();
        $this->max_files = $this->get_max_files();
        $this->retention_days = $this->get_retention_days();
        
        // Ensure log directory exists
        $this->ensure_log_directory();
        
        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('cko_log_cleanup')) {
            wp_schedule_event(time(), 'daily', 'cko_log_cleanup');
        }
        
        // Hook into cleanup action
        add_action('cko_log_cleanup', [$this, 'cleanup_old_logs']);
        
        // Hook into WordPress shutdown for immediate cleanup if needed
        add_action('shutdown', [$this, 'maybe_cleanup_logs']);
    }

    /**
     * Get maximum log file size from settings.
     *
     * @return int
     */
    private function get_max_file_size() {
        $size_mb = WC_Admin_Settings::get_option('cko_log_max_size_mb', 10);
        return $size_mb * 1024 * 1024; // Convert MB to bytes
    }

    /**
     * Get maximum number of log files from settings.
     *
     * @return int
     */
    private function get_max_files() {
        return WC_Admin_Settings::get_option('cko_log_max_files', 5);
    }

    /**
     * Get log retention period from settings.
     *
     * @return int
     */
    private function get_retention_days() {
        return WC_Admin_Settings::get_option('cko_log_retention_days', 30);
    }

    /**
     * Ensure log directory exists and is writable.
     */
    private function ensure_log_directory() {
        if (!file_exists($this->log_directory)) {
            wp_mkdir_p($this->log_directory);
        }
        
        // Create .htaccess to prevent direct access
        $htaccess_file = $this->log_directory . '.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, "Order deny,allow\nDeny from all\n");
        }
        
        // Create index.php to prevent directory listing
        $index_file = $this->log_directory . 'index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, "<?php\n// Silence is golden.\n");
        }
    }

    /**
     * Get current log file path.
     *
     * @param string $log_type Log type (e.g., 'gateway', 'webhook').
     * @return string
     */
    public function get_log_file_path($log_type = 'gateway') {
        $filename = "checkout-com-{$log_type}-" . date('Y-m-d') . '.log';
        return $this->log_directory . $filename;
    }

    /**
     * Check if log file needs rotation.
     *
     * @param string $log_file_path Log file path.
     * @return bool
     */
    public function should_rotate_log($log_file_path) {
        if (!file_exists($log_file_path)) {
            return false;
        }
        
        return filesize($log_file_path) >= $this->max_file_size;
    }

    /**
     * Rotate log file.
     *
     * @param string $log_file_path Log file path.
     * @return bool
     */
    public function rotate_log($log_file_path) {
        if (!$this->should_rotate_log($log_file_path)) {
            return false;
        }
        
        $timestamp = date('Y-m-d-H-i-s');
        $rotated_file = str_replace('.log', "-{$timestamp}.log", $log_file_path);
        
        // Move current log to rotated file
        if (rename($log_file_path, $rotated_file)) {
            // Compress the rotated file
            $this->compress_log_file($rotated_file);
            
            // Clean up old rotated files
            $this->cleanup_rotated_files(dirname($log_file_path));
            
            return true;
        }
        
        return false;
    }

    /**
     * Compress log file using gzip.
     *
     * @param string $file_path File path to compress.
     * @return bool
     */
    private function compress_log_file($file_path) {
        if (!function_exists('gzopen')) {
            return false;
        }
        
        $gz_file = $file_path . '.gz';
        
        $fp_in = fopen($file_path, 'rb');
        $fp_out = gzopen($gz_file, 'wb9');
        
        if (!$fp_in || !$fp_out) {
            return false;
        }
        
        while (!feof($fp_in)) {
            gzwrite($fp_out, fread($fp_in, 1024 * 512));
        }
        
        fclose($fp_in);
        gzclose($fp_out);
        
        // Remove original file after successful compression
        unlink($file_path);
        
        return true;
    }

    /**
     * Clean up old rotated log files.
     *
     * @param string $directory Directory to clean.
     */
    private function cleanup_rotated_files($directory) {
        $files = glob($directory . '/checkout-com-*.log*');
        
        if (empty($files)) {
            return;
        }
        
        // Sort files by modification time (newest first)
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        // Remove files beyond the limit
        if (count($files) > $this->max_files) {
            $files_to_remove = array_slice($files, $this->max_files);
            foreach ($files_to_remove as $file) {
                unlink($file);
            }
        }
    }

    /**
     * Clean up old log files based on retention policy.
     */
    public function cleanup_old_logs() {
        $cutoff_time = time() - ($this->retention_days * 24 * 60 * 60);
        $files = glob($this->log_directory . 'checkout-com-*.log*');
        
        $deleted_count = 0;
        $deleted_size = 0;
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                $file_size = filesize($file);
                if (unlink($file)) {
                    $deleted_count++;
                    $deleted_size += $file_size;
                }
            }
        }
        
        // Log cleanup results
        if ($deleted_count > 0) {
            $logger = WC_Checkoutcom_Enhanced_Logger::get_instance();
            $logger->info('Log cleanup completed', [
                'deleted_files' => $deleted_count,
                'deleted_size_mb' => round($deleted_size / 1024 / 1024, 2),
                'retention_days' => $this->retention_days,
            ]);
        }
        
        return [
            'deleted_files' => $deleted_count,
            'deleted_size' => $deleted_size,
        ];
    }

    /**
     * Maybe cleanup logs on shutdown if needed.
     */
    public function maybe_cleanup_logs() {
        // Only run cleanup occasionally to avoid performance impact
        $last_cleanup = get_transient('cko_last_log_cleanup');
        if (!$last_cleanup || (time() - $last_cleanup) > 3600) { // 1 hour
            $this->cleanup_old_logs();
            set_transient('cko_last_log_cleanup', time(), 3600);
        }
    }

    /**
     * Get log file statistics.
     *
     * @return array
     */
    public function get_log_statistics() {
        $files = glob($this->log_directory . 'checkout-com-*.log*');
        $total_size = 0;
        $file_count = 0;
        $oldest_file = null;
        $newest_file = null;
        
        foreach ($files as $file) {
            $file_size = filesize($file);
            $file_time = filemtime($file);
            
            $total_size += $file_size;
            $file_count++;
            
            if (!$oldest_file || $file_time < $oldest_file['time']) {
                $oldest_file = [
                    'name' => basename($file),
                    'time' => $file_time,
                    'size' => $file_size,
                ];
            }
            
            if (!$newest_file || $file_time > $newest_file['time']) {
                $newest_file = [
                    'name' => basename($file),
                    'time' => $file_time,
                    'size' => $file_size,
                ];
            }
        }
        
        return [
            'total_files' => $file_count,
            'total_size_mb' => round($total_size / 1024 / 1024, 2),
            'oldest_file' => $oldest_file,
            'newest_file' => $newest_file,
            'retention_days' => $this->retention_days,
            'max_files' => $this->max_files,
            'max_size_mb' => round($this->max_file_size / 1024 / 1024, 2),
        ];
    }

    /**
     * Export logs for debugging.
     *
     * @param int $days Number of days to export.
     * @return string
     */
    public function export_logs($days = 7) {
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        $files = glob($this->log_directory . 'checkout-com-*.log*');
        
        $export_data = [
            'export_timestamp' => date('Y-m-d H:i:s'),
            'export_period_days' => $days,
            'files' => [],
        ];
        
        foreach ($files as $file) {
            if (filemtime($file) >= $cutoff_time) {
                $content = file_get_contents($file);
                
                // Decompress if it's a gzipped file
                if (substr($file, -3) === '.gz') {
                    $content = gzdecode($content);
                }
                
                $export_data['files'][] = [
                    'filename' => basename($file),
                    'size' => filesize($file),
                    'modified' => date('Y-m-d H:i:s', filemtime($file)),
                    'content' => $content,
                ];
            }
        }
        
        return json_encode($export_data, JSON_PRETTY_PRINT);
    }

    /**
     * Clear all log files.
     *
     * @return bool
     */
    public function clear_all_logs() {
        $files = glob($this->log_directory . 'checkout-com-*.log*');
        $deleted_count = 0;
        
        foreach ($files as $file) {
            if (unlink($file)) {
                $deleted_count++;
            }
        }
        
        $logger = WC_Checkoutcom_Enhanced_Logger::get_instance();
        $logger->info('All log files cleared', ['deleted_files' => $deleted_count]);
        
        return $deleted_count > 0;
    }

    /**
     * Get log file content for display.
     *
     * @param string $filename Log filename.
     * @param int    $lines    Number of lines to return.
     * @return string
     */
    public function get_log_content($filename, $lines = 100) {
        $file_path = $this->log_directory . $filename;
        
        if (!file_exists($file_path)) {
            return '';
        }
        
        $content = file_get_contents($file_path);
        
        // Decompress if it's a gzipped file
        if (substr($file_path, -3) === '.gz') {
            $content = gzdecode($content);
        }
        
        // Return last N lines
        $content_lines = explode("\n", $content);
        return implode("\n", array_slice($content_lines, -$lines));
    }

    /**
     * Schedule immediate log cleanup.
     */
    public function schedule_cleanup() {
        wp_schedule_single_event(time(), 'cko_log_cleanup');
    }

    /**
     * Unschedule log cleanup.
     */
    public function unschedule_cleanup() {
        wp_clear_scheduled_hook('cko_log_cleanup');
    }
}
