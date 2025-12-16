<?php
/**
 * Logging Settings class for enhanced logging configuration.
 *
 * @package wc_checkout_com
 */

/**
 * Logging Settings class for admin configuration.
 */
class WC_Checkoutcom_Logging_Settings {

    /**
     * Initialize logging settings.
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_logging_menu']);
        add_action('admin_init', [__CLASS__, 'register_logging_settings']);
        add_action('wp_ajax_cko_export_logs', [__CLASS__, 'export_logs']);
        add_action('wp_ajax_cko_clear_logs', [__CLASS__, 'clear_logs']);
        add_action('wp_ajax_cko_get_log_stats', [__CLASS__, 'get_log_stats']);
    }

    /**
     * Add logging menu to admin.
     */
    public static function add_logging_menu() {
        add_submenu_page(
            'woocommerce',
            __('Checkout.com Logging', 'checkout-com-unified-payments-api'),
            __('Checkout.com Logging', 'checkout-com-unified-payments-api'),
            'manage_woocommerce',
            'checkout-com-logging',
            [__CLASS__, 'logging_settings_page']
        );
    }

    /**
     * Register logging settings.
     */
    public static function register_logging_settings() {
        register_setting('cko_logging_settings', 'cko_log_level');
        register_setting('cko_logging_settings', 'cko_log_max_size_mb');
        register_setting('cko_logging_settings', 'cko_log_max_files');
        register_setting('cko_logging_settings', 'cko_log_retention_days');
        register_setting('cko_logging_settings', 'cko_performance_logging');
        register_setting('cko_logging_settings', 'cko_async_logging');
        register_setting('cko_logging_settings', 'cko_log_buffer_size');
    }

    /**
     * Display logging settings page.
     */
    public static function logging_settings_page() {
        if (isset($_POST['submit'])) {
            self::save_logging_settings();
        }

        $log_manager = WC_Checkoutcom_Log_Manager::get_instance();
        $log_stats = $log_manager->get_log_statistics();
        ?>
        <div class="wrap">
            <h1><?php _e('Checkout.com Enhanced Logging', 'checkout-com-unified-payments-api'); ?></h1>
            
            <div class="notice notice-info">
                <p><?php _e('Enhanced logging provides better performance, structured data, and improved debugging capabilities.', 'checkout-com-unified-payments-api'); ?></p>
            </div>

            <div class="cko-logging-dashboard">
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Log Statistics', 'checkout-com-unified-payments-api'); ?></h2>
                    <div class="inside">
                        <table class="widefat">
                            <tr>
                                <td><strong><?php _e('Total Log Files:', 'checkout-com-unified-payments-api'); ?></strong></td>
                                <td><?php echo esc_html($log_stats['total_files']); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Total Size:', 'checkout-com-unified-payments-api'); ?></strong></td>
                                <td><?php echo esc_html($log_stats['total_size_mb']); ?> MB</td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Oldest File:', 'checkout-com-unified-payments-api'); ?></strong></td>
                                <td><?php echo esc_html($log_stats['oldest_file']['name'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Newest File:', 'checkout-com-unified-payments-api'); ?></strong></td>
                                <td><?php echo esc_html($log_stats['newest_file']['name'] ?? 'N/A'); ?></td>
                            </tr>
                        </table>
                        
                        <p>
                            <button type="button" class="button" id="refresh-stats"><?php _e('Refresh Stats', 'checkout-com-unified-payments-api'); ?></button>
                            <button type="button" class="button" id="export-logs"><?php _e('Export Logs', 'checkout-com-unified-payments-api'); ?></button>
                            <button type="button" class="button button-secondary" id="clear-logs"><?php _e('Clear All Logs', 'checkout-com-unified-payments-api'); ?></button>
                        </p>
                    </div>
                </div>

                <form method="post" action="">
                    <?php wp_nonce_field('cko_logging_settings', 'cko_logging_nonce'); ?>
                    
                    <div class="postbox">
                        <h2 class="hndle"><?php _e('Logging Configuration', 'checkout-com-unified-payments-api'); ?></h2>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Log Level', 'checkout-com-unified-payments-api'); ?></th>
                                    <td>
                                        <select name="cko_log_level">
                                            <?php
                                            $log_levels = [
                                                'debug' => __('Debug (All messages)', 'checkout-com-unified-payments-api'),
                                                'info' => __('Info (Informational messages)', 'checkout-com-unified-payments-api'),
                                                'warning' => __('Warning (Warning messages)', 'checkout-com-unified-payments-api'),
                                                'error' => __('Error (Error messages only)', 'checkout-com-unified-payments-api'),
                                                'critical' => __('Critical (Critical errors only)', 'checkout-com-unified-payments-api'),
                                            ];
                                            
                                            $current_level = get_option('cko_log_level', 'error');
                                            foreach ($log_levels as $value => $label) {
                                                printf(
                                                    '<option value="%s" %s>%s</option>',
                                                    esc_attr($value),
                                                    selected($current_level, $value, false),
                                                    esc_html($label)
                                                );
                                            }
                                            ?>
                                        </select>
                                        <p class="description"><?php _e('Set the minimum log level to record. Lower levels include higher levels.', 'checkout-com-unified-payments-api'); ?></p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row"><?php _e('Max Log File Size (MB)', 'checkout-com-unified-payments-api'); ?></th>
                                    <td>
                                        <input type="number" name="cko_log_max_size_mb" value="<?php echo esc_attr(get_option('cko_log_max_size_mb', 10)); ?>" min="1" max="100" />
                                        <p class="description"><?php _e('Maximum size for individual log files before rotation.', 'checkout-com-unified-payments-api'); ?></p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row"><?php _e('Max Log Files', 'checkout-com-unified-payments-api'); ?></th>
                                    <td>
                                        <input type="number" name="cko_log_max_files" value="<?php echo esc_attr(get_option('cko_log_max_files', 5)); ?>" min="1" max="50" />
                                        <p class="description"><?php _e('Maximum number of rotated log files to keep.', 'checkout-com-unified-payments-api'); ?></p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row"><?php _e('Log Retention (Days)', 'checkout-com-unified-payments-api'); ?></th>
                                    <td>
                                        <input type="number" name="cko_log_retention_days" value="<?php echo esc_attr(get_option('cko_log_retention_days', 30)); ?>" min="1" max="365" />
                                        <p class="description"><?php _e('Number of days to keep log files before automatic deletion.', 'checkout-com-unified-payments-api'); ?></p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row"><?php _e('Async Logging', 'checkout-com-unified-payments-api'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="cko_async_logging" value="yes" <?php checked(get_option('cko_async_logging', 'yes'), 'yes'); ?> />
                                            <?php _e('Enable asynchronous logging for better performance', 'checkout-com-unified-payments-api'); ?>
                                        </label>
                                        <p class="description"><?php _e('Buffer log entries and write them in batches to reduce I/O blocking.', 'checkout-com-unified-payments-api'); ?></p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row"><?php _e('Log Buffer Size', 'checkout-com-unified-payments-api'); ?></th>
                                    <td>
                                        <input type="number" name="cko_log_buffer_size" value="<?php echo esc_attr(get_option('cko_log_buffer_size', 100)); ?>" min="10" max="1000" />
                                        <p class="description"><?php _e('Number of log entries to buffer before writing to disk.', 'checkout-com-unified-payments-api'); ?></p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row"><?php _e('Performance Logging', 'checkout-com-unified-payments-api'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="cko_performance_logging" value="yes" <?php checked(get_option('cko_performance_logging', 'no'), 'yes'); ?> />
                                            <?php _e('Enable performance monitoring and logging', 'checkout-com-unified-payments-api'); ?>
                                        </label>
                                        <p class="description"><?php _e('Track and log performance metrics for debugging and optimization.', 'checkout-com-unified-payments-api'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            
                            <?php submit_button(__('Save Settings', 'checkout-com-unified-payments-api')); ?>
                        </div>
                    </div>
                </form>

                <?php if (class_exists('WC_Checkoutcom_Performance_Monitor')): ?>
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Performance Monitor', 'checkout-com-unified-payments-api'); ?></h2>
                    <div class="inside">
                        <div id="performance-stats">
                            <p><?php _e('Loading performance statistics...', 'checkout-com-unified-payments-api'); ?></p>
                        </div>
                        <p>
                            <button type="button" class="button" id="refresh-performance"><?php _e('Refresh Performance Stats', 'checkout-com-unified-payments-api'); ?></button>
                            <button type="button" class="button" id="export-performance"><?php _e('Export Performance Data', 'checkout-com-unified-payments-api'); ?></button>
                        </p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Refresh stats
            $('#refresh-stats').on('click', function() {
                location.reload();
            });

            // Export logs
            $('#export-logs').on('click', function() {
                if (confirm('<?php _e('This will download a JSON file with recent log data. Continue?', 'checkout-com-unified-payments-api'); ?>')) {
                    window.location.href = ajaxurl + '?action=cko_export_logs&nonce=' + '<?php echo wp_create_nonce('cko_export_logs'); ?>';
                }
            });

            // Clear logs
            $('#clear-logs').on('click', function() {
                if (confirm('<?php _e('This will permanently delete all log files. This action cannot be undone. Continue?', 'checkout-com-unified-payments-api'); ?>')) {
                    $.post(ajaxurl, {
                        action: 'cko_clear_logs',
                        nonce: '<?php echo wp_create_nonce('cko_clear_logs'); ?>'
                    }, function(response) {
                        if (response.success) {
                            alert('<?php _e('Logs cleared successfully.', 'checkout-com-unified-payments-api'); ?>');
                            location.reload();
                        } else {
                            alert('<?php _e('Error clearing logs: ', 'checkout-com-unified-payments-api'); ?>' + response.data);
                        }
                    });
                }
            });

            // Refresh performance stats
            $('#refresh-performance').on('click', function() {
                $.post(ajaxurl, {
                    action: 'cko_get_performance_stats',
                    nonce: '<?php echo wp_create_nonce('cko_performance_stats'); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#performance-stats').html('<pre>' + JSON.stringify(response.data, null, 2) + '</pre>');
                    } else {
                        $('#performance-stats').html('<p><?php _e('Error loading performance stats.', 'checkout-com-unified-payments-api'); ?></p>');
                    }
                });
            });

            // Load initial performance stats
            $('#refresh-performance').trigger('click');
        });
        </script>

        <style>
        .cko-logging-dashboard .postbox {
            margin-bottom: 20px;
        }
        .cko-logging-dashboard .form-table th {
            width: 200px;
        }
        .cko-logging-dashboard .inside {
            padding: 20px;
        }
        #performance-stats pre {
            background: #f1f1f1;
            padding: 10px;
            border-radius: 4px;
            max-height: 400px;
            overflow-y: auto;
        }
        </style>
        <?php
    }

    /**
     * Save logging settings.
     */
    private static function save_logging_settings() {
        if (!wp_verify_nonce($_POST['cko_logging_nonce'], 'cko_logging_settings')) {
            wp_die(__('Security check failed.', 'checkout-com-unified-payments-api'));
        }

        $settings = [
            'cko_log_level' => sanitize_text_field($_POST['cko_log_level']),
            'cko_log_max_size_mb' => intval($_POST['cko_log_max_size_mb']),
            'cko_log_max_files' => intval($_POST['cko_log_max_files']),
            'cko_log_retention_days' => intval($_POST['cko_log_retention_days']),
            'cko_async_logging' => isset($_POST['cko_async_logging']) ? 'yes' : 'no',
            'cko_log_buffer_size' => intval($_POST['cko_log_buffer_size']),
            'cko_performance_logging' => isset($_POST['cko_performance_logging']) ? 'yes' : 'no',
        ];

        foreach ($settings as $key => $value) {
            update_option($key, $value);
        }

        echo '<div class="notice notice-success"><p>' . __('Settings saved successfully.', 'checkout-com-unified-payments-api') . '</p></div>';
    }

    /**
     * Export logs via AJAX.
     */
    public static function export_logs() {
        if (!wp_verify_nonce($_GET['nonce'], 'cko_export_logs')) {
            wp_die(__('Security check failed.', 'checkout-com-unified-payments-api'));
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'checkout-com-unified-payments-api'));
        }

        $log_manager = WC_Checkoutcom_Log_Manager::get_instance();
        $export_data = $log_manager->export_logs(7); // Export last 7 days

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="checkout-com-logs-' . date('Y-m-d') . '.json"');
        echo $export_data;
        exit;
    }

    /**
     * Clear logs via AJAX.
     */
    public static function clear_logs() {
        if (!wp_verify_nonce($_POST['nonce'], 'cko_clear_logs')) {
            wp_send_json_error(__('Security check failed.', 'checkout-com-unified-payments-api'));
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Insufficient permissions.', 'checkout-com-unified-payments-api'));
        }

        $log_manager = WC_Checkoutcom_Log_Manager::get_instance();
        $result = $log_manager->clear_all_logs();

        if ($result) {
            wp_send_json_success(__('Logs cleared successfully.', 'checkout-com-unified-payments-api'));
        } else {
            wp_send_json_error(__('Failed to clear logs.', 'checkout-com-unified-payments-api'));
        }
    }

    /**
     * Get log statistics via AJAX.
     */
    public static function get_log_stats() {
        if (!wp_verify_nonce($_POST['nonce'], 'cko_log_stats')) {
            wp_send_json_error(__('Security check failed.', 'checkout-com-unified-payments-api'));
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Insufficient permissions.', 'checkout-com-unified-payments-api'));
        }

        $log_manager = WC_Checkoutcom_Log_Manager::get_instance();
        $stats = $log_manager->get_log_statistics();

        wp_send_json_success($stats);
    }
}
