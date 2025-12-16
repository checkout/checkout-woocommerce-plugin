<?php
/**
 * Enhanced Logger class for improved throughput and performance.
 *
 * @package wc_checkout_com
 */

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Enhanced Logger class with async capabilities and structured logging.
 */
class WC_Checkoutcom_Enhanced_Logger implements LoggerInterface {
    
    /**
     * Log levels mapping.
     */
    const LOG_LEVELS = [
        LogLevel::EMERGENCY => 0,
        LogLevel::ALERT     => 1,
        LogLevel::CRITICAL  => 2,
        LogLevel::ERROR     => 3,
        LogLevel::WARNING   => 4,
        LogLevel::NOTICE    => 5,
        LogLevel::INFO      => 6,
        LogLevel::DEBUG     => 7,
    ];

    /**
     * Logger instance.
     *
     * @var WC_Checkoutcom_Enhanced_Logger
     */
    private static $instance = null;

    /**
     * WooCommerce logger instance.
     *
     * @var WC_Logger
     */
    private $wc_logger;

    /**
     * Log buffer for batch processing.
     *
     * @var array
     */
    private $log_buffer = [];

    /**
     * Buffer size limit.
     *
     * @var int
     */
    private $buffer_size = 100;

    /**
     * Current log level threshold.
     *
     * @var int
     */
    private $log_level_threshold;

    /**
     * Context data for all logs.
     *
     * @var array
     */
    private $global_context = [];

    /**
     * Correlation ID for request tracking.
     *
     * @var string
     */
    private $correlation_id;

    /**
     * Performance metrics.
     *
     * @var array
     */
    private $metrics = [
        'logs_written' => 0,
        'logs_buffered' => 0,
        'buffer_flushes' => 0,
        'start_time' => null,
    ];

    /**
     * Get singleton instance.
     *
     * @return WC_Checkoutcom_Enhanced_Logger
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
        $this->wc_logger = wc_get_logger();
        $this->log_level_threshold = $this->get_log_level_threshold();
        $this->correlation_id = $this->generate_correlation_id();
        $this->metrics['start_time'] = microtime(true);
        
        // Set global context
        $this->global_context = [
            'plugin_version' => WC_CHECKOUTCOM_PLUGIN_VERSION ?? 'unknown',
            'wp_version' => get_bloginfo('version'),
            'wc_version' => WC()->version ?? 'unknown',
            'correlation_id' => $this->correlation_id,
            'request_id' => uniqid('req_', true),
        ];

        // Register shutdown hook for buffer flush
        register_shutdown_function([$this, 'flush_buffer']);
        
        // Register buffer flush on WordPress actions
        add_action('wp_footer', [$this, 'flush_buffer']);
        add_action('admin_footer', [$this, 'flush_buffer']);
    }

    /**
     * Get log level threshold from settings.
     *
     * @return int
     */
    private function get_log_level_threshold() {
        $log_level = WC_Admin_Settings::get_option('cko_log_level', 'error');
        
        switch (strtolower($log_level)) {
            case 'debug':
                return self::LOG_LEVELS[LogLevel::DEBUG];
            case 'info':
                return self::LOG_LEVELS[LogLevel::INFO];
            case 'warning':
            case 'warn':
                return self::LOG_LEVELS[LogLevel::WARNING];
            case 'error':
                return self::LOG_LEVELS[LogLevel::ERROR];
            case 'critical':
                return self::LOG_LEVELS[LogLevel::CRITICAL];
            default:
                return self::LOG_LEVELS[LogLevel::ERROR];
        }
    }

    /**
     * Generate correlation ID for request tracking.
     *
     * @return string
     */
    private function generate_correlation_id() {
        return 'cko_' . uniqid() . '_' . substr(md5(microtime()), 0, 8);
    }

    /**
     * Log with an arbitrary level.
     *
     * @param mixed  $level   Log level.
     * @param string $message Log message.
     * @param array  $context Log context.
     */
    public function log($level, $message, array $context = []) {
        $numeric_level = self::LOG_LEVELS[$level] ?? self::LOG_LEVELS[LogLevel::ERROR];
        
        // Skip if below threshold
        if ($numeric_level > $this->log_level_threshold) {
            return;
        }

        $this->write_log($level, $message, $context);
    }

    /**
     * System is unusable.
     *
     * @param string $message Log message.
     * @param array  $context Log context.
     */
    public function emergency($message, array $context = []) {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * @param string $message Log message.
     * @param array  $context Log context.
     */
    public function alert($message, array $context = []) {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Critical conditions.
     *
     * @param string $message Log message.
     * @param array  $context Log context.
     */
    public function critical($message, array $context = []) {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action.
     *
     * @param string $message Log message.
     * @param array  $context Log context.
     */
    public function error($message, array $context = []) {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * @param string $message Log message.
     * @param array  $context Log context.
     */
    public function warning($message, array $context = []) {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Normal but significant condition.
     *
     * @param string $message Log message.
     * @param array  $context Log context.
     */
    public function notice($message, array $context = []) {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Interesting events.
     *
     * @param string $message Log message.
     * @param array  $context Log context.
     */
    public function info($message, array $context = []) {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message Log message.
     * @param array  $context Log context.
     */
    public function debug($message, array $context = []) {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Write log entry.
     *
     * @param string $level   Log level.
     * @param string $message Log message.
     * @param array  $context Log context.
     */
    private function write_log($level, $message, array $context = []) {
        $log_entry = $this->format_log_entry($level, $message, $context);
        
        // Check if we should use async logging
        if ($this->should_use_async_logging($level)) {
            $this->buffer_log($log_entry);
        } else {
            $this->write_immediately($log_entry);
        }
    }

    /**
     * Format log entry with structured data.
     *
     * @param string $level   Log level.
     * @param string $message Log message.
     * @param array  $context Log context.
     * @return array
     */
    private function format_log_entry($level, $message, array $context = []) {
        $timestamp = gmdate('Y-m-d H:i:s');
        $memory_usage = memory_get_usage(true);
        $memory_peak = memory_get_peak_usage(true);
        
        // Merge global context with local context
        $merged_context = array_merge($this->global_context, $context);
        
        // Add performance data
        $merged_context['memory_usage'] = $this->format_bytes($memory_usage);
        $merged_context['memory_peak'] = $this->format_bytes($memory_peak);
        $merged_context['execution_time'] = microtime(true) - $this->metrics['start_time'];
        
        // Add order context if available
        if (isset($context['order_id'])) {
            $merged_context['order_id'] = $context['order_id'];
        }
        
        // Add payment context if available
        if (isset($context['payment_id'])) {
            $merged_context['payment_id'] = $context['payment_id'];
        }

        return [
            'timestamp' => $timestamp,
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $merged_context,
            'formatted_message' => $this->interpolate_message($message, $merged_context),
        ];
    }

    /**
     * Interpolate message with context variables.
     *
     * @param string $message Log message.
     * @param array  $context Log context.
     * @return string
     */
    private function interpolate_message($message, array $context = []) {
        $replace = [];
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }
        return strtr($message, $replace);
    }

    /**
     * Determine if async logging should be used.
     *
     * @param string $level Log level.
     * @return bool
     */
    private function should_use_async_logging($level) {
        // Use immediate logging for critical errors
        $immediate_levels = [LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL];
        return !in_array($level, $immediate_levels);
    }

    /**
     * Buffer log entry for batch processing.
     *
     * @param array $log_entry Log entry.
     */
    private function buffer_log($log_entry) {
        $this->log_buffer[] = $log_entry;
        $this->metrics['logs_buffered']++;
        
        // Flush buffer if it reaches the limit
        if (count($this->log_buffer) >= $this->buffer_size) {
            $this->flush_buffer();
        }
    }

    /**
     * Write log entry immediately.
     *
     * @param array $log_entry Log entry.
     */
    private function write_immediately($log_entry) {
        $this->write_to_wc_logger($log_entry);
        $this->metrics['logs_written']++;
    }

    /**
     * Flush log buffer.
     */
    public function flush_buffer() {
        if (empty($this->log_buffer)) {
            return;
        }

        foreach ($this->log_buffer as $log_entry) {
            $this->write_to_wc_logger($log_entry);
        }
        
        $this->metrics['logs_written'] += count($this->log_buffer);
        $this->metrics['buffer_flushes']++;
        $this->log_buffer = [];
    }

    /**
     * Write to WooCommerce logger.
     *
     * @param array $log_entry Log entry.
     */
    private function write_to_wc_logger($log_entry) {
        $context = ['source' => 'wc_checkoutcom_enhanced_log'];
        
        // Add structured data as JSON
        $context['structured_data'] = json_encode($log_entry['context']);
        
        // Write to WC logger
        $this->wc_logger->log(
            $this->map_to_wc_level($log_entry['level']),
            $log_entry['formatted_message'],
            $context
        );
    }

    /**
     * Map log level to WooCommerce log level.
     *
     * @param string $level Log level.
     * @return string
     */
    private function map_to_wc_level($level) {
        $mapping = [
            'EMERGENCY' => 'emergency',
            'ALERT' => 'alert',
            'CRITICAL' => 'critical',
            'ERROR' => 'error',
            'WARNING' => 'warning',
            'NOTICE' => 'notice',
            'INFO' => 'info',
            'DEBUG' => 'debug',
        ];
        
        return $mapping[$level] ?? 'error';
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
     * Add context to all subsequent logs.
     *
     * @param array $context Additional context.
     */
    public function add_context(array $context) {
        $this->global_context = array_merge($this->global_context, $context);
    }

    /**
     * Get correlation ID.
     *
     * @return string
     */
    public function get_correlation_id() {
        return $this->correlation_id;
    }

    /**
     * Get performance metrics.
     *
     * @return array
     */
    public function get_metrics() {
        return array_merge($this->metrics, [
            'buffer_size' => count($this->log_buffer),
            'uptime' => microtime(true) - $this->metrics['start_time'],
        ]);
    }

    /**
     * Log performance metrics.
     */
    public function log_metrics() {
        $metrics = $this->get_metrics();
        $this->info('Logger performance metrics', ['metrics' => $metrics]);
    }

    /**
     * Create a child logger with additional context.
     *
     * @param array $context Additional context.
     * @return WC_Checkoutcom_Enhanced_Logger
     */
    public function with_context(array $context) {
        $child = clone $this;
        $child->add_context($context);
        return $child;
    }

    /**
     * Log API request/response for debugging.
     *
     * @param string $method HTTP method.
     * @param string $url    Request URL.
     * @param array  $data   Request/response data.
     * @param string $type   Request or response.
     */
    public function log_api_call($method, $url, $data, $type = 'request') {
        $context = [
            'api_call' => true,
            'method' => $method,
            'url' => $url,
            'type' => $type,
            'data_size' => strlen(json_encode($data)),
        ];
        
        $message = sprintf('API %s: %s %s', strtoupper($type), $method, $url);
        $this->debug($message, $context);
    }

    /**
     * Log payment flow events.
     *
     * @param string $event   Event name.
     * @param array  $context Event context.
     */
    public function log_payment_event($event, array $context = []) {
        $context['payment_event'] = true;
        $context['event_name'] = $event;
        
        $this->info("Payment event: {$event}", $context);
    }

    /**
     * Log webhook events.
     *
     * @param string $event   Webhook event type.
     * @param array  $data    Webhook data.
     * @param string $status  Processing status.
     */
    public function log_webhook_event($event, $data, $status = 'received') {
        $context = [
            'webhook_event' => true,
            'event_type' => $event,
            'status' => $status,
            'data_size' => strlen(json_encode($data)),
        ];
        
        if (isset($data['metadata']['order_id'])) {
            $context['order_id'] = $data['metadata']['order_id'];
        }
        
        $message = sprintf('Webhook %s: %s', $status, $event);
        $this->info($message, $context);
    }
}
