<?php
/**
 * Diagnostic script to check domain association file status
 * 
 * Place this file in your WordPress root directory and access it via:
 * https://yourdomain.com/check-domain-association-file.php
 * 
 * Remove this file after troubleshooting for security.
 */

// Prevent direct access warnings
if ( ! defined( 'ABSPATH' ) ) {
	// Load WordPress if not loaded
	if ( file_exists( __DIR__ . '/wp-load.php' ) ) {
		require_once __DIR__ . '/wp-load.php';
	} else {
		die( 'WordPress not found. Please place this file in your WordPress root directory.' );
	}
}

header( 'Content-Type: text/html; charset=utf-8' );
?>
<!DOCTYPE html>
<html>
<head>
	<title>Domain Association File Diagnostic</title>
	<style>
		body { font-family: Arial, sans-serif; margin: 20px; }
		.success { color: green; }
		.error { color: red; }
		.warning { color: orange; }
		.info { background: #f0f0f0; padding: 15px; margin: 10px 0; border-left: 4px solid #0073aa; }
		.code { background: #f5f5f5; padding: 10px; font-family: monospace; margin: 10px 0; }
	</style>
</head>
<body>
	<h1>Apple Pay Domain Association File Diagnostic</h1>
	
	<?php
	$well_known_dir = ABSPATH . '.well-known';
	$file_path = $well_known_dir . '/apple-developer-merchantid-domain-association';
	$file_url = home_url( '/.well-known/apple-developer-merchantid-domain-association' );
	
	// Check 1: Directory exists
	echo '<div class="info">';
	echo '<h2>1. Directory Check</h2>';
	if ( file_exists( $well_known_dir ) ) {
		echo '<p class="success">✓ .well-known directory exists at: <code>' . esc_html( $well_known_dir ) . '</code></p>';
		echo '<p>Directory permissions: ' . substr( sprintf( '%o', fileperms( $well_known_dir ) ), -4 ) . '</p>';
	} else {
		echo '<p class="error">✗ .well-known directory does NOT exist at: <code>' . esc_html( $well_known_dir ) . '</code></p>';
		echo '<p class="warning">Try creating it manually or re-upload the domain association file via WordPress admin.</p>';
	}
	echo '</div>';
	
	// Check 2: File exists
	echo '<div class="info">';
	echo '<h2>2. File Check</h2>';
	if ( file_exists( $file_path ) ) {
		echo '<p class="success">✓ Domain association file exists at: <code>' . esc_html( $file_path ) . '</code></p>';
		echo '<p>File size: ' . size_format( filesize( $file_path ) ) . '</p>';
		echo '<p>File permissions: ' . substr( sprintf( '%o', fileperms( $file_path ) ), -4 ) . ' (should be 0644 or 644)</p>';
		echo '<p>File readable: ' . ( is_readable( $file_path ) ? '<span class="success">Yes</span>' : '<span class="error">No</span>' ) . '</p>';
		
		// Show first few lines of file
		$file_content = file_get_contents( $file_path );
		if ( $file_content ) {
			echo '<p>File content preview (first 200 chars):</p>';
			echo '<div class="code">' . esc_html( substr( $file_content, 0, 200 ) ) . '...</div>';
		}
	} else {
		echo '<p class="error">✗ Domain association file does NOT exist at: <code>' . esc_html( $file_path ) . '</code></p>';
		echo '<p class="warning">Please upload the domain association file via WordPress admin (Apple Pay settings).</p>';
	}
	echo '</div>';
	
	// Check 3: File URL accessibility
	echo '<div class="info">';
	echo '<h2>3. URL Accessibility Check</h2>';
	echo '<p>Expected URL: <a href="' . esc_url( $file_url ) . '" target="_blank">' . esc_html( $file_url ) . '</a></p>';
	
	// Try to access the file via HTTP
	$response = wp_remote_get( $file_url, array( 'timeout' => 10 ) );
	if ( ! is_wp_error( $response ) ) {
		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		
		if ( 200 === $status_code ) {
			echo '<p class="success">✓ File is accessible via HTTP (Status: 200 OK)</p>';
			echo '<p>Response length: ' . strlen( $body ) . ' bytes</p>';
		} else {
			echo '<p class="error">✗ File returned HTTP status: ' . $status_code . '</p>';
			if ( 404 === $status_code ) {
				echo '<p class="warning">404 Not Found - WordPress rewrite rules may be blocking access to .well-known directory.</p>';
			} elseif ( 403 === $status_code ) {
				echo '<p class="warning">403 Forbidden - File permissions or server configuration may be blocking access.</p>';
			}
		}
	} else {
		echo '<p class="error">✗ Could not access file via HTTP: ' . esc_html( $response->get_error_message() ) . '</p>';
	}
	echo '</div>';
	
	// Check 4: WordPress rewrite rules
	echo '<div class="info">';
	echo '<h2>4. WordPress Rewrite Rules Check</h2>';
	$rewrite_rules = get_option( 'rewrite_rules' );
	if ( is_array( $rewrite_rules ) ) {
		$has_well_known_rule = false;
		foreach ( $rewrite_rules as $pattern => $rewrite ) {
			if ( strpos( $pattern, 'well-known' ) !== false || strpos( $rewrite, 'well-known' ) !== false ) {
				$has_well_known_rule = true;
				break;
			}
		}
		if ( $has_well_known_rule ) {
			echo '<p class="success">✓ Found .well-known in rewrite rules</p>';
		} else {
			echo '<p class="warning">⚠ No specific .well-known rule found in rewrite rules.</p>';
			echo '<p>WordPress may be redirecting .well-known requests to index.php.</p>';
		}
	}
	echo '</div>';
	
	// Check 5: .htaccess file
	echo '<div class="info">';
	echo '<h2>5. .htaccess Check</h2>';
	$htaccess_file = ABSPATH . '.htaccess';
	if ( file_exists( $htaccess_file ) ) {
		echo '<p class="success">✓ .htaccess file exists</p>';
		$htaccess_content = file_get_contents( $htaccess_file );
		if ( strpos( $htaccess_content, 'well-known' ) !== false ) {
			echo '<p class="success">✓ Found .well-known reference in .htaccess</p>';
		} else {
			echo '<p class="warning">⚠ No .well-known rule found in .htaccess</p>';
			echo '<p>You may need to add a rule to allow .well-known directory access.</p>';
		}
	} else {
		echo '<p class="warning">⚠ .htaccess file does not exist (this is normal for Nginx or if using default permalinks)</p>';
	}
	echo '</div>';
	
	// Recommendations
	echo '<div class="info">';
	echo '<h2>Recommendations</h2>';
	if ( ! file_exists( $file_path ) ) {
		echo '<p><strong>Action Required:</strong> Upload the domain association file via WordPress admin (WooCommerce → Settings → Payments → Apple Pay → Domain Association Setup)</p>';
	}
	
	if ( file_exists( $file_path ) && ! is_readable( $file_path ) ) {
		echo '<p><strong>Action Required:</strong> Fix file permissions. Run: <code>chmod 644 ' . esc_html( $file_path ) . '</code></p>';
	}
	
	if ( file_exists( $htaccess_file ) && strpos( file_get_contents( $htaccess_file ), 'well-known' ) === false ) {
		echo '<p><strong>Action Required:</strong> Add this rule to your .htaccess file (before WordPress rules):</p>';
		echo '<div class="code"># Allow .well-known directory<br>';
		echo 'RewriteRule ^\.well-known/ - [L]</div>';
	}
	
	echo '<p><strong>Note:</strong> After making changes, flush rewrite rules: WordPress Admin → Settings → Permalinks → Save Changes</p>';
	echo '</div>';
	
	// Test file access
	echo '<div class="info">';
	echo '<h2>Test File Access</h2>';
	echo '<p>Click this link to test direct file access: <a href="' . esc_url( $file_url ) . '" target="_blank">' . esc_html( $file_url ) . '</a></p>';
	echo '<p>If you see a 404 or redirect, WordPress rewrite rules are likely blocking access.</p>';
	echo '</div>';
	?>
	
	<hr>
	<p><small>This diagnostic script should be removed after troubleshooting for security.</small></p>
</body>
</html>





