<?php
/**
 * View Webhook Queue Table - Direct Database Access
 * 
 * This script allows you to view the webhook queue table directly.
 * 
 * Usage:
 * 1. Via browser: Place in WordPress root and access via browser
 * 2. Via command line: php view-webhook-queue.php
 * 3. Via WP-CLI: wp eval-file view-webhook-queue.php
 */

// Load WordPress to get database connection
require_once __DIR__ . '/wp-load.php';

global $wpdb;
$table_name = $wpdb->prefix . 'cko_pending_webhooks';

// Check if running from command line
$is_cli = php_sapi_name() === 'cli';

// Require admin capability for browser access
if ( ! $is_cli ) {
	if ( ! is_user_logged_in() ) {
		wp_die( esc_html__( 'You must be logged in to view this page.', 'checkout-com-unified-payments-api' ) );
	}

	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'You do not have permission to view this page.', 'checkout-com-unified-payments-api' ) );
	}
}

if ($is_cli) {
    // Command line output
    echo "========================================\n";
    echo "Webhook Queue Table Viewer\n";
    echo "========================================\n\n";
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    
    if (!$table_exists) {
        echo "❌ Table '$table_name' does not exist.\n";
        exit(1);
    }
    
    // Get statistics
    $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $pending = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE processed_at IS NULL");
    $processed = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE processed_at IS NOT NULL");
    
    echo "Statistics:\n";
    echo "  Total: $total\n";
    echo "  Pending: $pending\n";
    echo "  Processed: $processed\n\n";
    
    // Get records
    $records = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 20");
    
    if (empty($records)) {
        echo "No webhooks found.\n";
        exit(0);
    }
    
    echo "Recent Webhooks (showing latest 20):\n";
    echo str_repeat("=", 120) . "\n";
    
    foreach ($records as $record) {
        echo "ID: {$record->id}\n";
        echo "  Payment ID: {$record->payment_id}\n";
        echo "  Order ID: " . ($record->order_id ?: 'N/A') . "\n";
        echo "  Payment Session ID: " . ($record->payment_session_id ?: 'N/A') . "\n";
        echo "  Type: {$record->webhook_type}\n";
        echo "  Status: " . ($record->processed_at ? 'Processed' : 'Pending') . "\n";
        echo "  Created: {$record->created_at}\n";
        if ($record->processed_at) {
            echo "  Processed: {$record->processed_at}\n";
        }
        
        // Show webhook data preview
        $data = json_decode($record->webhook_data);
        echo "  Webhook Data: " . substr(json_encode($data, JSON_PRETTY_PRINT), 0, 200) . "...\n";
        echo str_repeat("-", 120) . "\n";
    }
    
    echo "\nTo view full webhook data, use SQL query:\n";
    echo "SELECT webhook_data FROM $table_name WHERE id = [ID];\n";
    
} else {
    // Browser output
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Webhook Queue - Direct Access</title>
        <style>
            body { font-family: monospace; margin: 20px; background: #f5f5f5; }
            .container { background: white; padding: 20px; border-radius: 8px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th { background: #333; color: white; padding: 10px; text-align: left; }
            td { padding: 8px; border-bottom: 1px solid #ddd; }
            .pending { color: #f0b849; font-weight: bold; }
            .processed { color: #00a32a; font-weight: bold; }
            .stats { display: flex; gap: 20px; margin: 20px 0; }
            .stat { padding: 15px; background: #f0f0f0; border-radius: 4px; }
            pre { background: #f5f5f5; padding: 10px; overflow-x: auto; font-size: 11px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Webhook Queue Table - Direct Access</h1>
            
            <?php
            // Check if table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            
            if (!$table_exists) {
                echo "<p style='color: red;'>❌ Table '$table_name' does not exist.</p>";
                exit;
            }
            
            // Get statistics
            $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $pending = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE processed_at IS NULL");
            $processed = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE processed_at IS NOT NULL");
            ?>
            
            <div class="stats">
                <div class="stat">
                    <strong>Total:</strong> <?php echo esc_html($total); ?>
                </div>
                <div class="stat">
                    <strong>Pending:</strong> <span class="pending"><?php echo esc_html($pending); ?></span>
                </div>
                <div class="stat">
                    <strong>Processed:</strong> <span class="processed"><?php echo esc_html($processed); ?></span>
                </div>
            </div>
            
            <h2>Recent Webhooks</h2>
            
            <?php
            // Get records
    $limit = isset($_GET['limit']) ? absint($_GET['limit']) : 50;
            $records = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d",
                $limit
            ));
            
            if (empty($records)) {
                echo "<p>No webhooks found.</p>";
            } else {
            ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Payment ID</th>
                            <th>Order ID</th>
                            <th>Session ID</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Processed</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record): ?>
                            <tr>
                                <td><?php echo esc_html($record->id); ?></td>
                                <td><code><?php echo esc_html($record->payment_id); ?></code></td>
                                <td><?php echo esc_html($record->order_id ?: '—'); ?></td>
                                <td><code><?php echo esc_html($record->payment_session_id ?: '—'); ?></code></td>
                                <td><?php echo esc_html($record->webhook_type); ?></td>
                                <td>
                                    <?php if ($record->processed_at): ?>
                                        <span class="processed">Processed</span>
                                    <?php else: ?>
                                        <span class="pending">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($record->created_at); ?></td>
                                <td><?php echo esc_html($record->processed_at ?: '—'); ?></td>
                                <td>
                                    <a href="?view=<?php echo esc_attr($record->id); ?>">View JSON</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if (isset($_GET['view'])): 
                    $view_id = absint($_GET['view']);
                    $webhook = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $view_id));
                    if ($webhook):
                ?>
                    <h3>Full Webhook Data (ID: <?php echo esc_html($view_id); ?>)</h3>
                    <pre><?php echo esc_html(json_encode(json_decode($webhook->webhook_data), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                    <p><a href="?">← Back to list</a></p>
                <?php endif; endif; ?>
                
                <p>
                    <a href="?limit=100">Show 100</a> | 
                    <a href="?limit=200">Show 200</a> | 
                    <a href="?limit=500">Show 500</a>
                </p>
            <?php } ?>
            
            <hr>
            <h3>SQL Queries</h3>
            <p>You can also run these SQL queries directly:</p>
            <pre>
-- View all pending webhooks
SELECT * FROM <?php echo esc_html($table_name); ?> WHERE processed_at IS NULL ORDER BY created_at DESC;

-- View all processed webhooks
SELECT * FROM <?php echo esc_html($table_name); ?> WHERE processed_at IS NOT NULL ORDER BY processed_at DESC;

-- View webhook by payment ID
SELECT * FROM <?php echo esc_html($table_name); ?> WHERE payment_id = 'pay_xxx';

-- View webhook by order ID
SELECT * FROM <?php echo esc_html($table_name); ?> WHERE order_id = '12345';

-- Count by type
SELECT webhook_type, COUNT(*) as count FROM <?php echo esc_html($table_name); ?> GROUP BY webhook_type;
            </pre>
        </div>
    </body>
    </html>
    <?php
}



