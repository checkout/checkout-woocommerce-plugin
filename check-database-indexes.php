<?php
/**
 * Database Index Checker and Optimizer for Checkout.com Flow Integration
 * 
 * This script checks if the required database indexes exist for optimal
 * performance of payment_session_id queries.
 * 
 * Usage:
 *   1. Via WP-CLI: wp eval-file check-database-indexes.php
 *   2. Via Browser: Place in WordPress root and access via browser
 *   3. Via Admin: Add to WordPress admin menu (recommended)
 * 
 * @package checkout-com-unified-payments-api
 * @version 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	// If running via command line (WP-CLI), load WordPress
	if ( php_sapi_name() === 'cli' ) {
		// Try to find WordPress
		$wp_load_paths = array(
			__DIR__ . '/wp-load.php',
			__DIR__ . '/../wp-load.php',
			__DIR__ . '/../../wp-load.php',
		);
		
		foreach ( $wp_load_paths as $path ) {
			if ( file_exists( $path ) ) {
				require_once $path;
				break;
			}
		}
		
		if ( ! defined( 'ABSPATH' ) ) {
			die( 'Error: WordPress not found. Please run this script from WordPress root or via WP-CLI.' );
		}
	} else {
		die( 'Direct access not allowed.' );
	}
}

// Require admin capability for browser access
if ( php_sapi_name() !== 'cli' ) {
	if ( ! is_user_logged_in() ) {
		wp_die( esc_html__( 'You must be logged in to view this page.', 'checkout-com-unified-payments-api' ) );
	}

	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'You do not have permission to view this page.', 'checkout-com-unified-payments-api' ) );
	}
}

// Check if WooCommerce is active
if ( ! class_exists( 'WooCommerce' ) ) {
	die( 'Error: WooCommerce is not active.' );
}

global $wpdb;

/**
 * Get table name for postmeta
 */
$postmeta_table = $wpdb->postmeta;

/**
 * Get table name for posts
 */
$posts_table = $wpdb->posts;

/**
 * Check if index exists on meta_key
 */
function check_meta_key_index( $wpdb, $table_name ) {
	$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table_name} WHERE Key_name = 'meta_key'" );
	return ! empty( $indexes );
}

/**
 * Check if composite index exists on meta_key and meta_value
 */
function check_composite_index( $wpdb, $table_name ) {
	$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table_name} WHERE Key_name LIKE '%cko_payment_session_id%'" );
	return ! empty( $indexes );
}

/**
 * Get all indexes on postmeta table
 */
function get_all_indexes( $wpdb, $table_name ) {
	return $wpdb->get_results( "SHOW INDEX FROM {$table_name}" );
}

/**
 * Check query performance
 */
function test_query_performance( $wpdb, $table_name, $meta_key ) {
	// Get a sample payment_session_id from database
	$sample_value = $wpdb->get_var( $wpdb->prepare(
		"SELECT meta_value FROM {$table_name} WHERE meta_key = %s LIMIT 1",
		$meta_key
	) );
	
	if ( ! $sample_value ) {
		return array(
			'success' => false,
			'message' => 'No sample data found to test query performance.',
		);
	}
	
	// Test query performance
	$start_time = microtime( true );
	$result = $wpdb->get_results( $wpdb->prepare(
		"SELECT post_id FROM {$table_name} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
		$meta_key,
		$sample_value
	) );
	$end_time = microtime( true );
	
	$query_time = ( $end_time - $start_time ) * 1000; // Convert to milliseconds
	
	return array(
		'success' => true,
		'query_time_ms' => round( $query_time, 2 ),
		'result_count' => count( $result ),
	);
}

/**
 * Create composite index if needed
 */
function create_composite_index( $wpdb, $table_name, $meta_key ) {
	$index_name = 'idx_' . str_replace( '_', '', $meta_key );
	
	// Check if index already exists
	$existing = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM information_schema.statistics 
		WHERE table_schema = DATABASE() 
		AND table_name = %s 
		AND index_name = %s",
		$table_name,
		$index_name
	) );
	
	if ( $existing > 0 ) {
		return array(
			'success' => false,
			'message' => 'Index already exists.',
		);
	}
	
	// Create composite index
	$sql = $wpdb->prepare(
		"CREATE INDEX %s ON %s (meta_key, meta_value(191)) WHERE meta_key = %s",
		$index_name,
		$table_name,
		$meta_key
	);
	
	// Note: MySQL doesn't support filtered indexes, so we'll create a regular composite index
	// For better performance, we'll create index on meta_key and meta_value
	$sql = "CREATE INDEX {$index_name} ON {$table_name} (meta_key(191), meta_value(191))";
	
	$result = $wpdb->query( $sql );
	
	if ( $result === false ) {
		return array(
			'success' => false,
			'message' => 'Failed to create index: ' . $wpdb->last_error,
		);
	}
	
	return array(
		'success' => true,
		'message' => 'Index created successfully.',
		'index_name' => $index_name,
	);
}

// Main execution
$meta_key = '_cko_payment_session_id';
$results = array();

// 1. Check if meta_key index exists
$results['meta_key_index'] = check_meta_key_index( $wpdb, $postmeta_table );

// 2. Check if composite index exists
$results['composite_index'] = check_composite_index( $wpdb, $postmeta_table );

// 3. Get all indexes
$results['all_indexes'] = get_all_indexes( $wpdb, $postmeta_table );

// 4. Test query performance
$results['query_performance'] = test_query_performance( $wpdb, $postmeta_table, $meta_key );

// 5. Get table statistics
$results['table_stats'] = array(
	'total_rows' => $wpdb->get_var( "SELECT COUNT(*) FROM {$postmeta_table}" ),
	'meta_key_rows' => $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$postmeta_table} WHERE meta_key = %s",
		$meta_key
	) ),
);

// Output results
if ( php_sapi_name() === 'cli' ) {
	// CLI output
	echo "\n";
	echo "========================================\n";
	echo "Database Index Check - Checkout.com Flow\n";
	echo "========================================\n\n";
	
	echo "Meta Key: {$meta_key}\n";
	echo "Table: {$postmeta_table}\n\n";
	
	echo "1. Meta Key Index Exists: " . ( $results['meta_key_index'] ? 'YES ✅' : 'NO ❌' ) . "\n";
	echo "2. Composite Index Exists: " . ( $results['composite_index'] ? 'YES ✅' : 'NO ❌' ) . "\n";
	
	if ( $results['query_performance']['success'] ) {
		echo "3. Query Performance Test:\n";
		echo "   - Query Time: " . $results['query_performance']['query_time_ms'] . "ms\n";
		echo "   - Result Count: " . $results['query_performance']['result_count'] . "\n";
		
		if ( $results['query_performance']['query_time_ms'] > 20 ) {
			echo "   ⚠️  WARNING: Query time is high (>20ms). Consider adding index.\n";
		} elseif ( $results['query_performance']['query_time_ms'] > 15 ) {
			echo "   ⚠️  Query time is moderate (15-20ms). Index may help.\n";
		} else {
			echo "   ✅ Query time is good (<15ms).\n";
		}
	} else {
		echo "3. Query Performance Test: " . $results['query_performance']['message'] . "\n";
	}
	
	echo "\n4. Table Statistics:\n";
	echo "   - Total Rows: " . number_format( $results['table_stats']['total_rows'] ) . "\n";
	echo "   - Rows with {$meta_key}: " . number_format( $results['table_stats']['meta_key_rows'] ) . "\n";
	
	echo "\n5. Recommendations:\n";
	if ( ! $results['meta_key_index'] ) {
		echo "   ⚠️  WordPress should automatically index meta_key, but it's missing.\n";
		echo "   ⚠️  This may indicate a database issue.\n";
	}
	
	if ( ! $results['composite_index'] && $results['table_stats']['meta_key_rows'] > 100 ) {
		echo "   ✅ Consider creating composite index for better performance.\n";
		echo "   ✅ Run: CREATE INDEX idx_ckopaymentsessionid ON {$postmeta_table} (meta_key(191), meta_value(191));\n";
	} elseif ( ! $results['composite_index'] ) {
		echo "   ℹ️  Composite index not needed yet (low data volume).\n";
	}
	
	if ( $results['query_performance']['success'] && $results['query_performance']['query_time_ms'] > 20 ) {
		echo "   ⚠️  Query performance is slow. Adding index is recommended.\n";
	}
	
	echo "\n";
} else {
	// HTML output for browser
	?>
	<!DOCTYPE html>
	<html>
	<head>
		<title>Database Index Check - Checkout.com Flow</title>
		<style>
			body {
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
				max-width: 1200px;
				margin: 40px auto;
				padding: 20px;
				background: #f5f5f5;
			}
			.container {
				background: white;
				padding: 30px;
				border-radius: 8px;
				box-shadow: 0 2px 4px rgba(0,0,0,0.1);
			}
			h1 {
				color: #333;
				border-bottom: 3px solid #0073aa;
				padding-bottom: 10px;
			}
			.status {
				padding: 10px;
				margin: 10px 0;
				border-radius: 4px;
			}
			.status.success {
				background: #d4edda;
				color: #155724;
				border-left: 4px solid #28a745;
			}
			.status.warning {
				background: #fff3cd;
				color: #856404;
				border-left: 4px solid #ffc107;
			}
			.status.error {
				background: #f8d7da;
				color: #721c24;
				border-left: 4px solid #dc3545;
			}
			table {
				width: 100%;
				border-collapse: collapse;
				margin: 20px 0;
			}
			th, td {
				padding: 12px;
				text-align: left;
				border-bottom: 1px solid #ddd;
			}
			th {
				background: #f8f9fa;
				font-weight: 600;
			}
			.code {
				background: #f4f4f4;
				padding: 2px 6px;
				border-radius: 3px;
				font-family: monospace;
				font-size: 0.9em;
			}
		</style>
	</head>
	<body>
		<div class="container">
			<h1>Database Index Check - Checkout.com Flow</h1>
			
			<h2>Meta Key: <code><?php echo esc_html( $meta_key ); ?></code></h2>
			<p>Table: <code><?php echo esc_html( $postmeta_table ); ?></code></p>
			
			<h3>1. Index Status</h3>
			<div class="status <?php echo $results['meta_key_index'] ? 'success' : 'error'; ?>">
				<strong>Meta Key Index:</strong> <?php echo $results['meta_key_index'] ? '✅ EXISTS' : '❌ MISSING'; ?>
			</div>
			<div class="status <?php echo $results['composite_index'] ? 'success' : 'warning'; ?>">
				<strong>Composite Index:</strong> <?php echo $results['composite_index'] ? '✅ EXISTS' : '⚠️ NOT FOUND'; ?>
			</div>
			
			<h3>2. Query Performance Test</h3>
			<?php if ( $results['query_performance']['success'] ) : ?>
				<table>
					<tr>
						<th>Metric</th>
						<th>Value</th>
						<th>Status</th>
					</tr>
					<tr>
						<td>Query Time</td>
						<td><?php echo esc_html( $results['query_performance']['query_time_ms'] ); ?>ms</td>
						<td>
							<?php
							if ( $results['query_performance']['query_time_ms'] > 20 ) {
								echo '<span class="status error">⚠️ SLOW</span>';
							} elseif ( $results['query_performance']['query_time_ms'] > 15 ) {
								echo '<span class="status warning">⚠️ MODERATE</span>';
							} else {
								echo '<span class="status success">✅ GOOD</span>';
							}
							?>
						</td>
					</tr>
					<tr>
						<td>Results Found</td>
						<td><?php echo esc_html( $results['query_performance']['result_count'] ); ?></td>
						<td>-</td>
					</tr>
				</table>
			<?php else : ?>
				<div class="status warning">
					<?php echo esc_html( $results['query_performance']['message'] ); ?>
				</div>
			<?php endif; ?>
			
			<h3>3. Table Statistics</h3>
			<table>
				<tr>
					<th>Metric</th>
					<th>Value</th>
				</tr>
				<tr>
					<td>Total Rows in postmeta</td>
					<td><?php echo number_format( $results['table_stats']['total_rows'] ); ?></td>
				</tr>
				<tr>
					<td>Rows with <?php echo esc_html( $meta_key ); ?></td>
					<td><?php echo number_format( $results['table_stats']['meta_key_rows'] ); ?></td>
				</tr>
			</table>
			
			<h3>4. Recommendations</h3>
			<ul>
				<?php if ( ! $results['meta_key_index'] ) : ?>
					<li class="status error">
						<strong>⚠️ CRITICAL:</strong> WordPress should automatically index meta_key, but it's missing. 
						This may indicate a database issue. Please check your WordPress installation.
					</li>
				<?php endif; ?>
				
				<?php if ( ! $results['composite_index'] && $results['table_stats']['meta_key_rows'] > 100 ) : ?>
					<li class="status warning">
						<strong>✅ RECOMMENDED:</strong> Consider creating a composite index for better performance 
						with <?php echo number_format( $results['table_stats']['meta_key_rows'] ); ?> rows.
						<br><br>
						<strong>SQL Command:</strong><br>
						<code style="display: block; padding: 10px; background: #f4f4f4; margin-top: 5px;">
							CREATE INDEX idx_ckopaymentsessionid ON <?php echo esc_html( $postmeta_table ); ?> (meta_key(191), meta_value(191));
						</code>
					</li>
				<?php elseif ( ! $results['composite_index'] ) : ?>
					<li class="status success">
						<strong>ℹ️ INFO:</strong> Composite index not needed yet (low data volume: 
						<?php echo number_format( $results['table_stats']['meta_key_rows'] ); ?> rows).
					</li>
				<?php endif; ?>
				
				<?php if ( $results['query_performance']['success'] && $results['query_performance']['query_time_ms'] > 20 ) : ?>
					<li class="status warning">
						<strong>⚠️ PERFORMANCE:</strong> Query time is high (>20ms). Adding an index is recommended 
						to improve performance.
					</li>
				<?php endif; ?>
			</ul>
			
			<h3>5. Existing Indexes</h3>
			<?php if ( ! empty( $results['all_indexes'] ) ) : ?>
				<table>
					<tr>
						<th>Index Name</th>
						<th>Column</th>
						<th>Non-Unique</th>
						<th>Cardinality</th>
					</tr>
					<?php foreach ( $results['all_indexes'] as $index ) : ?>
						<tr>
							<td><?php echo esc_html( $index->Key_name ); ?></td>
							<td><?php echo esc_html( $index->Column_name ); ?></td>
							<td><?php echo esc_html( $index->Non_unique ); ?></td>
							<td><?php echo esc_html( $index->Cardinality ); ?></td>
						</tr>
					<?php endforeach; ?>
				</table>
			<?php else : ?>
				<div class="status warning">No indexes found.</div>
			<?php endif; ?>
		</div>
	</body>
	</html>
	<?php
}



