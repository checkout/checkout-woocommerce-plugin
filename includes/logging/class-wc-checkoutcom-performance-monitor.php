<?php
/**
 * Performance Monitor class for tracking and logging performance metrics.
 *
 * @package wc_checkout_com
 */

/**
 * Performance Monitor class for tracking system performance.
 */
class WC_Checkoutcom_Performance_Monitor {

    /**
     * Performance Monitor instance.
     *
     * @var WC_Checkoutcom_Performance_Monitor
     */
    private static $instance = null;

    /**
     * Performance metrics storage.
     *
     * @var array
     */
    private $metrics = [];

    /**
     * Start times for operations.
     *
     * @var array
     */
    private $start_times = [];

    /**
     * Memory usage tracking.
     *
     * @var array
     */
    private $memory_usage = [];

    /**
     * Database query tracking.
     *
     * @var array
     */
    private $db_queries = [];

    /**
     * API call tracking.
     *
     * @var array
     */
    private $api_calls = [];

    /**
     * Get singleton instance.
     *
     * @return WC_Checkoutcom_Performance_Monitor
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
        $this->initialize_tracking();
    }

    /**
     * Initialize performance tracking.
     */
    private function initialize_tracking() {
        // Track initial memory usage
        $this->memory_usage['start'] = memory_get_usage(true);
        $this->memory_usage['peak_start'] = memory_get_peak_usage(true);
        
        // Track initial time
        $this->start_times['request'] = microtime(true);
        
        // Hook into WordPress actions for tracking
        add_action('init', [$this, 'track_wp_init']);
        add_action('wp_loaded', [$this, 'track_wp_loaded']);
        add_action('shutdown', [$this, 'track_shutdown']);
        
        // Track database queries if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_filter('log_query_custom_data', [$this, 'track_db_query'], 10, 5);
        }
    }

    /**
     * Start timing an operation.
     *
     * @param string $operation Operation name.
     * @return void
     */
    public function start_timer($operation) {
        $this->start_times[$operation] = microtime(true);
    }

    /**
     * End timing an operation and record the duration.
     *
     * @param string $operation Operation name.
     * @param array  $context   Additional context.
     * @return float Duration in seconds.
     */
    public function end_timer($operation, $context = []) {
        if (!isset($this->start_times[$operation])) {
            return 0;
        }

        $duration = microtime(true) - $this->start_times[$operation];
        
        $this->metrics['operations'][$operation] = [
            'duration' => $duration,
            'context' => $context,
            'timestamp' => time(),
        ];

        unset($this->start_times[$operation]);
        
        return $duration;
    }

    /**
     * Track memory usage.
     *
     * @param string $label Memory usage label.
     * @return void
     */
    public function track_memory($label) {
        $this->memory_usage[$label] = [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'timestamp' => microtime(true),
        ];
    }

    /**
     * Track API call performance.
     *
     * @param string $method    HTTP method.
     * @param string $url       API URL.
     * @param float  $duration  Request duration.
     * @param int    $status    HTTP status code.
     * @param array  $context   Additional context.
     * @return void
     */
    public function track_api_call($method, $url, $duration, $status, $context = []) {
        $this->api_calls[] = [
            'method' => $method,
            'url' => $url,
            'duration' => $duration,
            'status' => $status,
            'context' => $context,
            'timestamp' => microtime(true),
        ];

        // Keep only last 100 API calls
        if (count($this->api_calls) > 100) {
            $this->api_calls = array_slice($this->api_calls, -100);
        }
    }

    /**
     * Track database query.
     *
     * @param array  $query_data Query data.
     * @param string $query      SQL query.
     * @param float  $query_time Query execution time.
     * @param string $query_callstack Query callstack.
     * @param string $query_start Query start time.
     * @return array
     */
    public function track_db_query($query_data, $query, $query_time, $query_callstack, $query_start) {
        $this->db_queries[] = [
            'query' => $query,
            'duration' => $query_time,
            'callstack' => $query_callstack,
            'timestamp' => $query_start,
        ];

        // Keep only last 50 queries
        if (count($this->db_queries) > 50) {
            $this->db_queries = array_slice($this->db_queries, -50);
        }

        return $query_data;
    }

    /**
     * Track WordPress initialization.
     */
    public function track_wp_init() {
        $this->end_timer('wp_init');
        $this->track_memory('wp_init');
    }

    /**
     * Track WordPress loaded.
     */
    public function track_wp_loaded() {
        $this->end_timer('wp_loaded');
        $this->track_memory('wp_loaded');
    }

    /**
     * Track shutdown performance.
     */
    public function track_shutdown() {
        $this->end_timer('request');
        $this->track_memory('shutdown');
        
        // Log performance summary if enabled
        if ($this->should_log_performance()) {
            $this->log_performance_summary();
        }
    }

    /**
     * Check if performance logging should be enabled.
     *
     * @return bool
     */
    private function should_log_performance() {
        return 'yes' === WC_Admin_Settings::get_option('cko_performance_logging', 'no');
    }

    /**
     * Log performance summary.
     */
    private function log_performance_summary() {
        $summary = $this->get_performance_summary();
        
        if (class_exists('WC_Checkoutcom_Enhanced_Logger')) {
            $logger = WC_Checkoutcom_Enhanced_Logger::get_instance();
            $logger->info('Performance summary', [
                'performance_summary' => true,
                'summary' => $summary,
            ]);
        }
    }

    /**
     * Get performance summary.
     *
     * @return array
     */
    public function get_performance_summary() {
        $total_time = microtime(true) - $this->start_times['request'];
        $memory_peak = memory_get_peak_usage(true);
        $memory_current = memory_get_usage(true);
        
        // Calculate operation statistics
        $operation_stats = [];
        if (isset($this->metrics['operations'])) {
            foreach ($this->metrics['operations'] as $operation => $data) {
                $operation_stats[$operation] = [
                    'duration' => round($data['duration'] * 1000, 2), // Convert to milliseconds
                    'context' => $data['context'],
                ];
            }
        }

        // Calculate API call statistics
        $api_stats = [
            'total_calls' => count($this->api_calls),
            'total_duration' => 0,
            'average_duration' => 0,
            'slowest_call' => null,
            'status_codes' => [],
        ];

        if (!empty($this->api_calls)) {
            $total_duration = 0;
            $slowest_duration = 0;
            
            foreach ($this->api_calls as $call) {
                $total_duration += $call['duration'];
                
                if ($call['duration'] > $slowest_duration) {
                    $slowest_duration = $call['duration'];
                    $api_stats['slowest_call'] = [
                        'url' => $call['url'],
                        'method' => $call['method'],
                        'duration' => round($call['duration'] * 1000, 2),
                    ];
                }
                
                $status = $call['status'];
                $api_stats['status_codes'][$status] = ($api_stats['status_codes'][$status] ?? 0) + 1;
            }
            
            $api_stats['total_duration'] = round($total_duration * 1000, 2);
            $api_stats['average_duration'] = round(($total_duration / count($this->api_calls)) * 1000, 2);
        }

        // Calculate database query statistics
        $db_stats = [
            'total_queries' => count($this->db_queries),
            'total_duration' => 0,
            'average_duration' => 0,
            'slowest_query' => null,
        ];

        if (!empty($this->db_queries)) {
            $total_duration = 0;
            $slowest_duration = 0;
            
            foreach ($this->db_queries as $query) {
                $total_duration += $query['duration'];
                
                if ($query['duration'] > $slowest_duration) {
                    $slowest_duration = $query['duration'];
                    $db_stats['slowest_query'] = [
                        'query' => substr($query['query'], 0, 100) . '...',
                        'duration' => round($query['duration'] * 1000, 2),
                    ];
                }
            }
            
            $db_stats['total_duration'] = round($total_duration * 1000, 2);
            $db_stats['average_duration'] = round(($total_duration / count($this->db_queries)) * 1000, 2);
        }

        return [
            'request_duration_ms' => round($total_time * 1000, 2),
            'memory_usage' => [
                'peak_mb' => round($memory_peak / 1024 / 1024, 2),
                'current_mb' => round($memory_current / 1024 / 1024, 2),
                'peak_usage' => $this->format_bytes($memory_peak),
                'current_usage' => $this->format_bytes($memory_current),
            ],
            'operations' => $operation_stats,
            'api_calls' => $api_stats,
            'database_queries' => $db_stats,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Format bytes to human readable format.
     *
     * @param int $bytes Number of bytes.
     * @return string
     */
    private function format_bytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Get slow operations (above threshold).
     *
     * @param float $threshold_ms Threshold in milliseconds.
     * @return array
     */
    public function get_slow_operations($threshold_ms = 100) {
        $slow_operations = [];
        
        if (isset($this->metrics['operations'])) {
            foreach ($this->metrics['operations'] as $operation => $data) {
                $duration_ms = $data['duration'] * 1000;
                if ($duration_ms > $threshold_ms) {
                    $slow_operations[$operation] = [
                        'duration_ms' => round($duration_ms, 2),
                        'context' => $data['context'],
                    ];
                }
            }
        }
        
        return $slow_operations;
    }

    /**
     * Get memory usage statistics.
     *
     * @return array
     */
    public function get_memory_statistics() {
        return [
            'current' => $this->format_bytes(memory_get_usage(true)),
            'peak' => $this->format_bytes(memory_get_peak_usage(true)),
            'tracked_points' => $this->memory_usage,
        ];
    }

    /**
     * Reset all metrics.
     */
    public function reset_metrics() {
        $this->metrics = [];
        $this->start_times = [];
        $this->memory_usage = [];
        $this->db_queries = [];
        $this->api_calls = [];
        
        // Reinitialize tracking
        $this->initialize_tracking();
    }

    /**
     * Export performance data.
     *
     * @return array
     */
    public function export_performance_data() {
        return [
            'summary' => $this->get_performance_summary(),
            'slow_operations' => $this->get_slow_operations(),
            'memory_statistics' => $this->get_memory_statistics(),
            'api_calls' => $this->api_calls,
            'db_queries' => $this->db_queries,
            'export_timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Check if performance is within acceptable limits.
     *
     * @return array
     */
    public function check_performance_limits() {
        $summary = $this->get_performance_summary();
        $warnings = [];
        
        // Check request duration
        if ($summary['request_duration_ms'] > 5000) { // 5 seconds
            $warnings[] = 'Request duration exceeds 5 seconds';
        }
        
        // Check memory usage
        if ($summary['memory_usage']['peak_mb'] > 128) { // 128 MB
            $warnings[] = 'Peak memory usage exceeds 128 MB';
        }
        
        // Check for slow operations
        $slow_ops = $this->get_slow_operations(1000); // 1 second
        if (!empty($slow_ops)) {
            $warnings[] = 'Slow operations detected: ' . implode(', ', array_keys($slow_ops));
        }
        
        // Check API call performance
        if ($summary['api_calls']['average_duration'] > 2000) { // 2 seconds
            $warnings[] = 'Average API call duration exceeds 2 seconds';
        }
        
        return [
            'within_limits' => empty($warnings),
            'warnings' => $warnings,
            'summary' => $summary,
        ];
    }
}
